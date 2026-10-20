<?php

namespace Src\Jardin\Localisation;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /tournee` (V131, écran T7), en JSON comme toute action de terrain
 * (D-21). La position est facultative : sans elle (localisation refusée), la
 * tournée se range par zone. Rien n'est écrit (D-65).
 */
final class TourneeController
{
    public function proposer(Request $request, Tournee $tournee): JsonResponse
    {
        /** @var array{lng?: float|int|string|null, lat?: float|int|string|null} $saisie */
        $saisie = $request->validate([
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
        ]);
        $ici = isset($saisie['lng'], $saisie['lat']) ? ['lng' => (float) $saisie['lng'], 'lat' => (float) $saisie['lat']] : null;

        return response()->json(['tournee' => $tournee->pour($ici)]);
    }
}
