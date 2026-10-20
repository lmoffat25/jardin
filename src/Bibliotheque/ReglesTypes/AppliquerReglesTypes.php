<?php

namespace Src\Bibliotheque\ReglesTypes;

use Illuminate\Validation\ValidationException;
use LogicException;
use Src\Bibliotheque\Chargement\LireBibliotheque;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\Domaine\RegleReference;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;

/**
 * Copie sur une plante neuve les règles de référence en vigueur de son espèce
 * (RG-39, RG-09, RG-51, V83, V84) : les règles communes et celles de la
 * variante de la plante, sauf les écartées. Chaque copie est une règle
 * version 1 active, liée à sa référence, avec ses fenêtres et ses saisons ;
 * une valeur ajustée remplace celle de la référence, et la règle devient
 * `ajustee` (D-51). La plante fait foi : la référence n'est plus relue.
 *
 * Une clé inconnue, un ajustement qui ne s'applique pas au type de la règle,
 * une fenêtre qui n'existe pas lèvent une `ValidationException` : l'appelant
 * est dans la transaction de création, rien ne reste écrit.
 *
 * @phpstan-import-type Parametres from LireBibliotheque
 * @phpstan-import-type FenetreLue from LireBibliotheque
 * @phpstan-import-type IntervalleLu from LireBibliotheque
 *
 * @phpstan-type Ajustement array{periodicite_annees?: int, intervalle_jours?: int, fenetres?: array<int, array{debut?: string, fin?: string}>, intervalles?: list<IntervalleLu>}
 */
final class AppliquerReglesTypes
{
    /** Ce qu'un ajustement peut changer, par type de règle. */
    private const AJUSTABLES = [
        'R1' => ['intervalle_jours', 'intervalles', 'fenetres'],
        'R2' => ['periodicite_annees', 'fenetres'],
    ];

    /**
     * @param  list<string>  $ecartees  clés des règles de référence à ne pas copier
     * @param  array<string, Ajustement>  $ajustements  par clé de règle de référence
     */
    public function executer(Plante $plante, Espece $espece, array $ecartees, array $ajustements): void
    {
        $references = $espece->reglesReference()
            ->whereNull('remplacee_le')
            ->where(fn ($w) => $w->whereNull('variante')->orWhere('variante', $plante->getAttribute('variante')))
            ->orderBy('cle')
            ->get()
            ->keyBy('cle');

        $inconnues = array_diff($ecartees, $references->keys()->all());
        if ($inconnues !== []) {
            throw ValidationException::withMessages(['regles_ecartees' => 'Règle inconnue pour cette espèce : '.implode(', ', $inconnues).'.']);
        }
        $horsCopie = array_diff(array_keys($ajustements), array_diff($references->keys()->all(), $ecartees));
        if ($horsCopie !== []) {
            throw ValidationException::withMessages(['ajustements' => 'Ajustement d\'une règle qui n\'est pas copiée : '.implode(', ', $horsCopie).'.']);
        }

        foreach ($references as $cle => $reference) {
            /** @var RegleReference $reference */
            if (! in_array($cle, $ecartees, true)) {
                $this->copier($plante, $reference, $ajustements[$cle] ?? []);
            }
        }
    }

    /** @param  Ajustement  $ajustement */
    private function copier(Plante $plante, RegleReference $reference, array $ajustement): void
    {
        $champ = "ajustements.{$reference->cle}";
        $refuses = array_diff(array_keys($ajustement), self::AJUSTABLES[$reference->type_regle]);
        if ($refuses !== []) {
            throw ValidationException::withMessages([$champ => implode(', ', $refuses)." ne s'applique pas à une règle {$reference->type_regle}."]);
        }

        /** @var Parametres $p */
        $p = $reference->parametres;
        $fenetres = $this->fenetres($p['fenetres'], $ajustement['fenetres'] ?? [], $champ);
        $intervalles = $ajustement['intervalles'] ?? $p['intervalles'];
        if (isset($ajustement['intervalles'])) {
            self::verifierSaisons($intervalles, "{$champ}.intervalles");
        }

        $regle = Regle::create([
            'plante_id' => $plante->id,
            'type_intervention_id' => $reference->type_intervention_id,
            'regle_reference_id' => $reference->id,
            'type_regle' => $reference->type_regle,
            'intervalle_jours' => $reference->type_regle === 'R1' ? ($ajustement['intervalle_jours'] ?? self::intervalleDeRepli($intervalles, $reference)) : null,
            'periodicite_annees' => $reference->type_regle === 'R2' ? ($ajustement['periodicite_annees'] ?? $p['periodicite_annees']) : null,
            'confiance' => $reference->confiance,
            'origine' => $ajustement === [] ? 'bibliotheque' : 'ajustee',
            'est_active' => true,
            'version' => 1,
        ]);

        foreach ($fenetres as $ordre => $f) {
            $colonnes = ['regle_id' => $regle->id, 'ordre' => $ordre + 1, 'libelle' => $f['libelle']];
            foreach (['debut' => $f['debut'], 'fin' => $f['fin'], 'optimal_debut' => $f['optimal'][0] ?? null, 'optimal_fin' => $f['optimal'][1] ?? null,
                'butoir_debut' => $f['butoirs'][0] ?? null, 'butoir_fin' => $f['butoirs'][1] ?? null] as $borne => $jourMois) {
                [$colonnes["{$borne}_jour"], $colonnes["{$borne}_mois"]] = self::jourMois($jourMois);
            }
            Fenetre::create($colonnes);
        }
        foreach ($intervalles as $i) {
            IntervalleSaisonnier::create(['regle_id' => $regle->id, 'mois_debut' => $i['mois_debut'], 'mois_fin' => $i['mois_fin'], 'intervalle_jours' => $i['jours']]);
        }
    }

    /**
     * Les fenêtres de la référence, bornes ajustées. Une fenêtre aux bornes
     * déplacées perd l'optimale de la référence, qui ne valait que pour les
     * bornes d'origine ; ses butoirs restent (D-101).
     *
     * @param  list<FenetreLue>  $fenetres
     * @param  array<int, array{debut?: string, fin?: string}>  $ajustees  par ordre, à partir de 1
     * @return list<FenetreLue>
     */
    private function fenetres(array $fenetres, array $ajustees, string $champ): array
    {
        $inconnues = array_diff(array_keys($ajustees), range(1, max(1, count($fenetres))));
        if ($inconnues !== [] || ($fenetres === [] && $ajustees !== [])) {
            throw ValidationException::withMessages(["{$champ}.fenetres" => 'Fenêtre inconnue : '.implode(', ', array_keys($ajustees)).'.']);
        }
        foreach ($ajustees as $ordre => $bornes) {
            $f = $fenetres[$ordre - 1];
            $fenetres[$ordre - 1] = [...$f, 'debut' => $bornes['debut'] ?? $f['debut'], 'fin' => $bornes['fin'] ?? $f['fin'], 'optimal' => null];
        }

        return $fenetres;
    }

    /**
     * Le moteur refuse deux saisons qui partagent un mois (D-90) ; une R1
     * garde au moins une saison active.
     *
     * @param  list<IntervalleLu>  $intervalles
     */
    private static function verifierSaisons(array $intervalles, string $champ): void
    {
        $couverts = [];
        foreach ($intervalles as $i) {
            for ($m = $i['mois_debut']; ; $m = $m % 12 + 1) {
                if (isset($couverts[$m])) {
                    throw ValidationException::withMessages([$champ => "Le mois {$m} est dans deux saisons."]);
                }
                $couverts[$m] = true;
                if ($m === $i['mois_fin']) {
                    break;
                }
            }
        }
        if (array_filter(array_column($intervalles, 'jours')) === []) {
            throw ValidationException::withMessages([$champ => 'Au moins une saison porte un intervalle.']);
        }
    }

    /**
     * L'intervalle d'une R1 hors de ses saisons (D-98) : le plus court
     * intervalle non nul de ses saisons (D-101).
     *
     * @param  list<IntervalleLu>  $intervalles
     */
    private static function intervalleDeRepli(array $intervalles, RegleReference $reference): int
    {
        $actifs = array_filter(array_column($intervalles, 'jours'), fn (int $j) => $j > 0);
        if ($actifs === []) {
            throw new LogicException("Règle R1 {$reference->cle} sans saison active.");
        }

        return min($actifs);
    }

    /** @return array{int|null, int|null} « JJ/MM » => [jour, mois] */
    private static function jourMois(?string $jourMois): array
    {
        if ($jourMois === null) {
            return [null, null];
        }
        [$jour, $mois] = explode('/', $jourMois);

        return [(int) $jour, (int) $mois];
    }
}
