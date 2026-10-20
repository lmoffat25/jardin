<?php

namespace Src\Journal\Historique;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;

/**
 * L'historique d'une plante, chargé à la demande par sa fiche (V78) : ses
 * interventions, la plus récente d'abord, 20 par page.
 */
final class HistoriqueController
{
    private const PAR_PAGE = 20;

    public function __invoke(Request $request, string $plante): JsonResponse
    {
        // Par le scope du jardin courant : une plante de B est introuvable (D1).
        $plante = Str::isUuid($plante) ? Plante::query()->findOrFail($plante) : abort(404);
        $page = max(1, $request->integer('page', 1));

        // Une ligne de plus que la page : elle dit s'il en reste une suivante.
        $interventions = Intervention::query()
            ->with('typeIntervention')
            ->where('plante_id', $plante->id)
            ->orderByDesc('faite_le')
            ->orderByDesc('saisie_le')
            ->orderBy('id')
            ->forPage($page, self::PAR_PAGE)
            ->limit(self::PAR_PAGE + 1)
            ->get();

        return response()->json([
            'interventions' => $interventions->take(self::PAR_PAGE)->map(function (Intervention $i): array {
                $type = $i->typeIntervention ?? throw new LogicException("Intervention {$i->id} sans type.");

                return [
                    'id' => $i->id,
                    'faite_le' => $i->faite_le->format('Y-m-d'),
                    'type' => ['id' => $type->id, 'libelle' => $type->libelle],
                    'duree_min' => $i->getAttribute('duree_min'),
                    'note' => $i->getAttribute('note'),
                ];
            })->values()->all(),
            'page' => $page,
            'suivante' => $interventions->count() > self::PAR_PAGE,
        ]);
    }
}
