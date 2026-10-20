<?php

namespace Src\Jardin\Localisation;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /plantes/proches` (V111, écran T3), en JSON comme toute action de
 * terrain (D-21). La position est facultative : sans elle (localisation
 * refusée), seules la zone courante et la recherche répondent. La précision
 * est acceptée sans servir au tri (Q8). Une zone de B ne rend rien.
 */
final class ProchesController
{
    public function lister(Request $request, PlantesProches $proches): JsonResponse
    {
        /** @var array{lng?: float|int|string|null, lat?: float|int|string|null, q?: string|null, zone?: string|null} $saisie */
        $saisie = $request->validate([
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'precision_m' => ['nullable', 'numeric', 'min:0'],
            'q' => ['nullable', 'string', 'max:120'],
            'zone' => ['nullable', 'uuid'],
        ]);
        $ici = isset($saisie['lng'], $saisie['lat']) ? ['lng' => (float) $saisie['lng'], 'lat' => (float) $saisie['lat']] : null;

        return response()->json($proches->pour($ici, $saisie['zone'] ?? null, $saisie['q'] ?? ''));
    }
}
