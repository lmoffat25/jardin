<?php

namespace Src\Echeances\Solde;

use Illuminate\Database\Eloquent\Collection as Modeles;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LogicException;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Regle;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;

/**
 * Une intervention solde l'échéance qu'elle accomplit (RG-05, H9) : écrite
 * `soldee` avec son `intervention_id`, elle devient close et n'est plus
 * proposée (D-05). Le moteur dit laquelle, à la date de l'intervention, avec
 * l'historique qui la précède : la fenêtre qui contient la date (R2), ou la
 * date due si elle est échue ce jour-là (R1). Aucune : rien n'est écrit (V54).
 * Jamais deux soldées pour une même occurrence (V100, S16).
 *
 * Une manquée déjà écrite reste soldable : la date fait foi (RG-05), une
 * saisie rétroactive la fait passer à `soldee` (D-105).
 */
final class SolderEcheance
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    /**
     * Le solde générique d'une saisie : pour chaque intervention, l'échéance
     * de la règle active (plante, type) qui l'attendait à sa date.
     *
     * @param  Collection<int, Intervention>  $interventions  du jardin courant
     */
    public function pourInterventions(Collection $interventions): void
    {
        if ($interventions->isEmpty()) {
            return;
        }
        $regles = $this->regles(
            $interventions->map(fn (Intervention $i): string => $i->plante_id)->unique()->all(),
            $interventions->map(fn (Intervention $i): int => $i->type_intervention_id)->unique()->all(),
        )
            ->keyBy(fn (Regle $r) => $r->plante_id.'|'.$r->type_intervention_id);
        $decalage = $this->decalage();

        foreach ($interventions as $intervention) {
            $regle = $regles->get($intervention->plante_id.'|'.$intervention->type_intervention_id);
            if ($regle === null) {
                continue;
            }
            $faiteLe = $intervention->faite_le->toDateTimeImmutable();
            $echeance = $this->accomplie($this->calculer($regle, $faiteLe, $decalage, $intervention->id), $faiteLe);
            if ($echeance !== null) {
                $this->ecrire($regle, $echeance, $intervention);
            }
        }
    }

    /**
     * Le couple que le moteur rend aujourd'hui pour cette règle, manquée ou à
     * venir (D-57) ; null s'il ne le rend pas. Une manquée déjà écrite est
     * rendue : elle peut encore être soldée.
     */
    public function echeanceDuCouple(Regle $regle, \DateTimeImmutable $dueLe, \DateTimeImmutable $aujourdhui, ?string $sauf = null): ?Echeance
    {
        foreach ($this->calculer($regle, $aujourdhui, $this->decalage(), $sauf) as $echeance) {
            if ($echeance->dueLe->format('Y-m-d') === $dueLe->format('Y-m-d')) {
                return $echeance;
            }
        }

        return null;
    }

    /**
     * « Fait » depuis une échéance (D-57) : solde exactement le couple reçu,
     * calculé à la date de l'intervention sans elle.
     */
    public function couple(Intervention $intervention, string $regleId, \DateTimeImmutable $dueLe): void
    {
        $regle = Regle::query()->with(['fenetres', 'intervalles', 'plante', 'typeIntervention'])->findOrFail($regleId);
        $echeance = $this->echeanceDuCouple($regle, $dueLe, $intervention->faite_le->toDateTimeImmutable(), $intervention->id)
            ?? throw new LogicException("Couple ({$regleId}, {$dueLe->format('Y-m-d')}) que le moteur ne rend pas.");

        $this->ecrire($regle, $echeance, $intervention);
    }

    /**
     * Les échéances de la règle au $jour, avec les interventions faites ce
     * jour-là ou avant, sauf $sauf, et les closes hors manquées.
     *
     * @return list<Echeance>
     */
    private function calculer(Regle $regle, \DateTimeImmutable $jour, Decalage $decalage, ?string $sauf): array
    {
        $derniere = Intervention::query()
            ->where('plante_id', $regle->plante_id)
            ->where('type_intervention_id', $regle->type_intervention_id)
            ->where('faite_le', '<=', $jour->format('Y-m-d'))
            ->when($sauf !== null, fn ($q) => $q->whereKeyNot($sauf))
            ->max('faite_le');
        $closes = EcheanceMaterialisee::query()
            ->where('regle_id', $regle->id)
            ->where('etat', '!=', 'manquee')
            ->get(['regle_id', 'due_le', 'decalage_climatique_applique_j']);

        $historique = Historique::depuis(
            is_string($derniere) ? [['plante_id' => $regle->plante_id, 'type_id' => $regle->type_intervention_id, 'fait_le' => new \DateTimeImmutable($derniere)]] : [],
            array_values($closes->map(fn (EcheanceMaterialisee $e) => [
                'regle_id' => $e->regle_id,
                'due_le' => $e->due_le->toDateTimeImmutable(),
                'decalage_j' => $e->decalage_climatique_applique_j,
            ])->all()),
        );

        return Moteur::calculer([$regle->versDomaine()], $historique, $decalage, $jour);
    }

    /**
     * Celle que l'intervention du $jour accomplit : la dernière rendue, si sa
     * période contient le jour (R2) ou si elle est due ce jour-là (R1).
     *
     * @param  list<Echeance>  $echeances
     */
    private function accomplie(array $echeances, \DateTimeImmutable $jour): ?Echeance
    {
        $prochaine = $echeances[count($echeances) - 1] ?? null;
        if ($prochaine === null) {
            return null;
        }
        $jour = $jour->format('Y-m-d');
        $accomplie = $prochaine->explication->typeRegle === 'R1'
            ? $prochaine->dueLe->format('Y-m-d') <= $jour
            : $prochaine->periode->debut->format('Y-m-d') <= $jour && $jour <= $prochaine->periode->fin->format('Y-m-d');

        return $accomplie ? $prochaine : null;
    }

    /**
     * Écrit l'occurrence `soldee`, ou fait passer sa manquée à `soldee`. Une
     * occurrence déjà soldée ou suspendue ne change pas (S16).
     */
    private function ecrire(Regle $regle, Echeance $echeance, Intervention $intervention): void
    {
        $periode = $echeance->periode;
        // insertOrIgnore passe outre le modèle : le jardin courant s'écrit ici (D1).
        $ecrites = EcheanceMaterialisee::query()->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'jardin_id' => $this->jardinCourant->id(),
            'regle_id' => $regle->id,
            'plante_id' => $regle->plante_id,
            'due_le' => $echeance->dueLe->format('Y-m-d'),
            'fenetre_debut' => $periode->debut->format('Y-m-d'),
            'fenetre_fin' => $periode->fin->format('Y-m-d'),
            'optimal_debut' => $periode->optimalDebut?->format('Y-m-d'),
            'optimal_fin' => $periode->optimalFin?->format('Y-m-d'),
            'decalage_climatique_applique_j' => $echeance->explication->decalageJours,
            'etat' => 'soldee',
            'intervention_id' => $intervention->id,
            'explication' => $echeance->explication->enPhrase($regle->typeIntervention->libelle, $regle->plante->nom),
        ]);

        if ($ecrites === 0) {
            EcheanceMaterialisee::query()
                ->where('regle_id', $regle->id)
                ->where('due_le', $echeance->dueLe->format('Y-m-d'))
                ->where('etat', 'manquee')
                ->update(['etat' => 'soldee', 'intervention_id' => $intervention->id]);
        }
    }

    /**
     * Les règles actives de ces plantes pour ces types, avec ce que le moteur
     * et la phrase lisent.
     *
     * @param  array<int, string>  $plantes
     * @param  array<int, int>  $types
     * @return Modeles<int, Regle>
     */
    private function regles(array $plantes, array $types): Modeles
    {
        return Regle::query()
            ->where('est_active', true)
            ->whereIn('plante_id', $plantes)
            ->whereIn('type_intervention_id', $types)
            ->with(['fenetres', 'intervalles', 'plante', 'typeIntervention'])
            ->get();
    }

    private function decalage(): Decalage
    {
        $jardin = Jardin::query()->with('profilClimatique')->findOrFail($this->jardinCourant->id());
        $profil = $jardin->profilClimatique ?? throw new LogicException("Jardin {$jardin->id} sans profil climatique.");

        return Decalage::jours($profil->decalage_jours);
    }
}
