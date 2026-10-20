<?php

namespace Src\Echeances\Solde;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Regle;
use Src\Echeances\Moteur\Etat;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;

/**
 * Sauter une occurrence (CDC §4.3–4.4, V105, écran B6) : l'échéance
 * `(regle_id, due_le)` que le moteur rend aujourd'hui pour cette règle est
 * écrite `suspendue`, avec son motif ; close, elle n'est plus proposée et la
 * suivante l'est (D-05, D-89). Recommencer rend la suspension écrite, sans en
 * écrire une seconde (S16).
 *
 * Une manquée reste manquée : elle n'est jamais effacée (RG-07). Une R1 ne se
 * saute pas : le moteur ne lit pas les closes d'une R1, la même échéance
 * reviendrait (Q-UX-22, D-106).
 */
final class SuspendreEcheance
{
    public const MANQUEE = 'Une échéance manquée reste manquée : elle ne se saute pas.';

    public const R1 = 'Une échéance à intervalle ne se saute pas : faites-la ou corrigez la règle.';

    public function __construct(
        private readonly SolderEcheance $solder,
        private readonly Horloge $horloge,
        private readonly JardinCourant $jardinCourant,
    ) {}

    public function executer(Regle $regle, \DateTimeImmutable $dueLe, string $motif): EcheanceMaterialisee
    {
        return DB::transaction(function () use ($regle, $dueLe, $motif): EcheanceMaterialisee {
            $deja = $this->ecrite($regle, $dueLe);
            if ($deja?->etat === 'suspendue') {
                return $deja;
            }
            if (! $regle->est_active) {
                throw ValidationException::withMessages(['regle_id' => FaitController::REGLE_INACTIVE]);
            }
            if ($regle->type_regle === 'R1') {
                throw ValidationException::withMessages(['regle_id' => self::R1]);
            }
            $echeance = $this->solder->echeanceDuCouple($regle, $dueLe, $this->horloge->aujourdhui())
                ?? throw ValidationException::withMessages(['due_le' => FaitController::PAS_A_FAIRE]);
            if ($echeance->etat === Etat::Manquee) {
                throw ValidationException::withMessages(['due_le' => self::MANQUEE]);
            }

            $periode = $echeance->periode;
            // insertOrIgnore passe outre le modèle : le jardin courant s'écrit ici (D1).
            EcheanceMaterialisee::query()->insertOrIgnore([
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
                'etat' => 'suspendue',
                'motif_suspension' => $motif,
                'explication' => $echeance->explication->enPhrase($regle->typeIntervention->libelle, $regle->plante->nom),
            ]);

            return $this->ecrite($regle, $dueLe) ?? throw new \LogicException("Suspension de ({$regle->id}, {$dueLe->format('Y-m-d')}) introuvable après écriture.");
        });
    }

    private function ecrite(Regle $regle, \DateTimeImmutable $dueLe): ?EcheanceMaterialisee
    {
        return EcheanceMaterialisee::query()
            ->where('regle_id', $regle->id)
            ->where('due_le', $dueLe->format('Y-m-d'))
            ->first();
    }
}
