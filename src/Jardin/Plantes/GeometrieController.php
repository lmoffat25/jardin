<?php

namespace Src\Jardin\Plantes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Src\Jardin\Domaine\GeoJson;
use Src\Jardin\Domaine\Geometrie;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Localisation\EnregistrerPosition;

/**
 * Poser ou tracer une plante (V107, RG-14, D-59) : un point, une ligne ou un
 * polygone GeoJSON, tracé sur le plan (B13, source `plan`, par défaut) ou
 * saisi en coordonnées (B6, source `manuel`). Réservé au propriétaire. Des
 * coordonnées saisies sur une plante posée sur le plan sont refusées, pas
 * ignorées (Q-UX-23) : la plante se déplace sur le plan.
 */
final class GeometrieController
{
    public function enregistrer(Request $request, string $plante, EnregistrerPosition $position): JsonResponse|RedirectResponse
    {
        $plante = Str::isUuid($plante) ? Plante::query()->findOrFail($plante) : abort(404);
        Gate::authorize('modifier', $plante);

        /** @var array{type: string, coordinates: array<mixed>, source?: string} $saisie */
        $saisie = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(GeoJson::FORMES))],
            'coordinates' => ['required', 'array'],
            'source' => ['sometimes', Rule::in(['plan', 'manuel'])],
        ], [
            'type.required' => 'Choisissez un point, une ligne ou un polygone.',
            'type.in' => 'Type de géométrie inconnu.',
            'coordinates.required' => 'Donnez les coordonnées.',
            'source.in' => 'Une géométrie se trace sur le plan ou se saisit.',
        ]);
        $erreur = GeoJson::erreur($saisie['type'], $saisie['coordinates']);
        if ($erreur !== null) {
            throw ValidationException::withMessages(['coordinates' => $erreur]);
        }
        $geojson = ['type' => $saisie['type'], 'coordinates' => $saisie['coordinates']];
        $plan = ($saisie['source'] ?? 'plan') === 'plan';
        if (! $plan && Geometrie::query()->where('plante_id', $plante->id)->where('source', 'plan')->exists()) {
            throw ValidationException::withMessages(['source' => 'Cette plante est posée sur le plan : déplacez-la sur le plan.']);
        }

        $geometrie = DB::transaction(function () use ($plante, $geojson, $plan, $position): Geometrie {
            $plan ? $position->depuisPlan($plante, $geojson) : $position->depuisSaisie($plante, $geojson);

            return Geometrie::query()->where('plante_id', $plante->id)->sole();
        });

        return $request->wantsJson()
            ? response()->json(['id' => $plante->id, 'geometrie' => [...$geometrie->coordonnees, 'source' => $geometrie->source]])
            : back();
    }
}
