<?php

namespace Src\Echeances\Reglages;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Support\Jardin\JardinCourant;

/**
 * Corriger une règle crée sa version suivante, sans réécrire le passé (RG-08,
 * D10, V102, V103) : v1 devient inactive, datée de son remplacement, **avant**
 * l'insertion de v2 (l'index unique partiel n'admet qu'une active par plante
 * et par type) ; v2 copie v1, `version + 1`, garde sa référence, prend les
 * valeurs corrigées et devient `ajustee` (D-51). Aucune colonne d'auteur
 * (D-58). V1 et ses fenêtres ne changent pas au-delà de `est_active` et
 * `remplacee_le`.
 *
 * Les manquées de v1 que le moteur rend encore s'écrivent sous v1 avant
 * qu'elle cesse d'être calculée : inactive, elle ne le serait plus, et elles
 * disparaîtraient en silence (RG-07, D-106).
 *
 * @phpstan-import-type FenetreSaisie from ValiderFenetres
 *
 * @phpstan-type Saison array{mois_debut: int, mois_fin: int, jours: int}
 * @phpstan-type Correction array{periodicite_annees?: int, intervalle_jours?: int, intervalles?: list<Saison>, fenetres?: list<FenetreSaisie>}
 */
final class ModifierRegle
{
    public const REMPLACEE = 'Cette version de la règle a déjà été remplacée.';

    public function __construct(
        private readonly CalculEcheances $calcul,
        private readonly MaterialiserManquees $materialiser,
        private readonly JardinCourant $jardinCourant,
    ) {}

    /**
     * @param  Correction  $correction  validée par l'appelant
     * @return Regle la nouvelle version
     */
    public function executer(Regle $regle, array $correction): Regle
    {
        return DB::transaction(function () use ($regle, $correction): Regle {
            $v1 = Regle::query()->with(['fenetres', 'intervalles'])->lockForUpdate()->findOrFail($regle->id);
            if (! $v1->est_active) {
                throw ValidationException::withMessages(['regle' => self::REMPLACEE]);
            }

            $this->materialiser->executer($this->calculer()->filter(fn (EcheanceCalculee $e) => $e->regle->id === $v1->id));

            $v1->update(['est_active' => false, 'remplacee_le' => now()]);

            $v2 = Regle::create([
                'plante_id' => $v1->plante_id,
                'type_intervention_id' => $v1->type_intervention_id,
                'regle_reference_id' => $v1->getAttribute('regle_reference_id'),
                'type_regle' => $v1->type_regle,
                'intervalle_jours' => $v1->type_regle === 'R1' ? $this->intervalleJours($v1, $correction) : null,
                'periodicite_annees' => $v1->type_regle === 'R2' ? ($correction['periodicite_annees'] ?? $v1->periodicite_annees) : null,
                'confiance' => $v1->confiance,
                'origine' => 'ajustee',
                'est_active' => true,
                'version' => $v1->version + 1,
            ]);

            $fenetres = isset($correction['fenetres'])
                ? array_map(fn (array $f, int $i) => ValiderFenetres::colonnes($f, $i + 1), $correction['fenetres'], array_keys($correction['fenetres']))
                : $v1->fenetres->map(fn (Fenetre $f) => array_diff_key($f->getAttributes(), ['id' => 0, 'regle_id' => 0, 'jardin_id' => 0]))->all();
            foreach ($fenetres as $colonnes) {
                Fenetre::create([...$colonnes, 'regle_id' => $v2->id]);
            }

            $saisons = isset($correction['intervalles'])
                ? array_map(fn (array $s) => ['mois_debut' => $s['mois_debut'], 'mois_fin' => $s['mois_fin'], 'intervalle_jours' => $s['jours']], $correction['intervalles'])
                : $v1->intervalles->map(fn (IntervalleSaisonnier $s) => ['mois_debut' => $s->mois_debut, 'mois_fin' => $s->mois_fin, 'intervalle_jours' => $s->intervalle_jours])->all();
            foreach ($saisons as $colonnes) {
                IntervalleSaisonnier::create([...$colonnes, 'regle_id' => $v2->id]);
            }

            return $v2;
        });
    }

    /** La prochaine échéance de la règle, recalculée ; null pour la règle d'une plante archivée. */
    public function prochaine(Regle $regle): ?EcheanceCalculee
    {
        return $this->calculer()->filter(fn (EcheanceCalculee $e) => $e->regle->id === $regle->id)->last();
    }

    /**
     * L'intervalle d'une R1 hors de ses saisons : celui corrigé ; sinon, des
     * saisons corrigées, le plus court non nul (D-98, D-101) ; sinon celui de v1.
     *
     * @param  Correction  $correction
     */
    private function intervalleJours(Regle $v1, array $correction): ?int
    {
        if (isset($correction['intervalle_jours'])) {
            return $correction['intervalle_jours'];
        }
        if (isset($correction['intervalles'])) {
            $actifs = array_filter(array_column($correction['intervalles'], 'jours'));

            return $actifs === [] ? $v1->intervalle_jours : min($actifs);
        }

        return $v1->intervalle_jours;
    }

    /** @return Collection<int, EcheanceCalculee> */
    private function calculer(): Collection
    {
        return $this->calcul->pourJardin(Jardin::query()->with('profilClimatique')->findOrFail($this->jardinCourant->id()));
    }
}
