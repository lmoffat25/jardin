<?php

namespace Src\Jardin\Plantes;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\TableauDeBord\RegrouperEcheances;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

/**
 * La fiche d'une plante (B6 au bureau, T5 au terrain) : la plante, les
 * échéances à venir de ses règles et ses manquées (RG-07, critère 6, V78).
 * Son historique se charge à part (`HistoriqueController`).
 */
final class PlanteController
{
    public function __construct(
        private readonly RegrouperEcheances $regrouper,
        private readonly JardinCourant $jardinCourant,
    ) {}

    public function afficher(Request $request, string $plante): Response
    {
        // Par le scope du jardin courant : une plante de B est introuvable (D1).
        $plante = Str::isUuid($plante) ? Plante::query()->with('zone')->findOrFail($plante) : abort(404);
        $zone = $plante->zone ?? throw new LogicException("Plante {$plante->id} sans zone.");
        $jardin = Jardin::query()->findOrFail($this->jardinCourant->id());

        $echeances = $this->regrouper->calculer($jardin)
            ->filter(fn (EcheanceCalculee $e) => $e->plante->id === $plante->id)
            ->map(RegrouperEcheances::item(...))
            ->values()
            ->all();

        $manquees = EcheanceMaterialisee::query()
            ->with('regle.typeIntervention')
            ->where('plante_id', $plante->id)
            ->where('etat', 'manquee')
            ->orderByDesc('due_le')
            ->get()
            ->map(function (EcheanceMaterialisee $m): array {
                $type = $m->regle->typeIntervention ?? throw new LogicException("Échéance {$m->id} sans règle.");

                return [
                    'cle' => ['regle_id' => $m->regle_id, 'due_le' => $m->due_le->format('Y-m-d')],
                    'type' => ['id' => $type->id, 'libelle' => $type->libelle],
                    'periode' => [
                        'debut' => self::jour($m->getAttribute('fenetre_debut')),
                        'fin' => self::jour($m->getAttribute('fenetre_fin')),
                        'optimal_debut' => self::jour($m->getAttribute('optimal_debut')),
                        'optimal_fin' => self::jour($m->getAttribute('optimal_fin')),
                    ],
                    'explication' => $m->explication,
                ];
            })
            ->values()
            ->all();

        return Inertia::render($request->query('contexte') === 'terrain' ? 'Terrain/FichePlante' : 'Bureau/FichePlante', [
            'plante' => [
                'id' => $plante->id,
                'nom' => $plante->nom,
                'code_court' => $plante->code_court,
                'zone' => ['id' => $zone->id, 'nom' => $zone->nom],
            ],
            'echeances' => $echeances,
            'manquees' => $manquees,
        ]);
    }

    private static function jour(mixed $date): ?string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : null;
    }
}
