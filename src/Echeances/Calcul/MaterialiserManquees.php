<?php

namespace Src\Echeances\Calcul;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Moteur\Etat;
use Src\Support\Jardin\JardinCourant;

/**
 * Une manquée s'écrit une fois, au calcul qui la constate (RG-07, D3) : avec sa
 * période, le décalage appliqué et sa phrase, pour l'expliquer après coup même
 * si le profil change (RG-06). Close, elle n'est plus rendue par le moteur
 * (D-05). Aucun recalcul planifié : c'est le calcul d'un écran qui l'écrit
 * (chapitre 3 §10).
 */
final class MaterialiserManquees
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    /**
     * @param  Collection<int, EcheanceCalculee>  $calculees  le calcul du jardin courant
     * @return int le nombre de lignes écrites
     */
    public function executer(Collection $calculees): int
    {
        $lignes = $calculees
            ->filter(fn (EcheanceCalculee $e) => $e->echeance->etat === Etat::Manquee)
            ->map(function (EcheanceCalculee $e): array {
                $periode = $e->echeance->periode;

                return [
                    'id' => (string) Str::uuid(),
                    // insertOrIgnore passe outre le modèle : le jardin courant s'écrit ici (D1).
                    'jardin_id' => $this->jardinCourant->id(),
                    'regle_id' => $e->regle->id,
                    'plante_id' => $e->plante->id,
                    'due_le' => $e->echeance->dueLe->format('Y-m-d'),
                    'fenetre_debut' => $periode->debut->format('Y-m-d'),
                    'fenetre_fin' => $periode->fin->format('Y-m-d'),
                    'optimal_debut' => $periode->optimalDebut?->format('Y-m-d'),
                    'optimal_fin' => $periode->optimalFin?->format('Y-m-d'),
                    'decalage_climatique_applique_j' => $e->echeance->explication->decalageJours,
                    'etat' => Etat::Manquee->value,
                    'explication' => $e->echeance->explication->enPhrase($e->type->libelle, $e->plante->nom),
                ];
            })
            ->values()
            ->all();

        // Idempotent par l'identité de l'occurrence, (regle_id, due_le) (S16).
        return $lignes === [] ? 0 : EcheanceMaterialisee::query()->insertOrIgnore($lignes);
    }
}
