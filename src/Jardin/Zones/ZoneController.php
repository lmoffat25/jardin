<?php

namespace Src\Jardin\Zones;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;

/**
 * Les zones du jardin courant (CDC §3.2, parcours P0, écran B3, V82) : la
 * liste des zones actives par nom (D-65), créer, renommer. Un nom est unique
 * dans le jardin, zones archivées comprises (contrainte `(jardin_id, nom)`).
 */
final class ZoneController
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    public function lister(): Response
    {
        $zones = Zone::query()
            ->whereNull('archivee_le')
            ->orderBy('nom')
            ->get(['id', 'nom'])
            ->map(fn (Zone $z) => ['id' => $z->id, 'nom' => $z->nom]);

        return Inertia::render('Bureau/Zones', ['zones' => $zones]);
    }

    public function creer(Request $request): JsonResponse|RedirectResponse
    {
        $zone = Zone::create(['nom' => $this->nom($request)]);

        return $request->wantsJson()
            ? response()->json(['id' => $zone->id, 'nom' => $zone->nom], 201)
            : back();
    }

    public function renommer(Request $request, string $zone): JsonResponse|RedirectResponse
    {
        // Par le filtre du jardin courant : une zone de B est introuvable (D1).
        $zone = Str::isUuid($zone) ? Zone::query()->findOrFail($zone) : abort(404);
        $zone->update(['nom' => $this->nom($request, $zone->id)]);

        return $request->wantsJson()
            ? response()->json(['id' => $zone->id, 'nom' => $zone->nom])
            : back();
    }

    private function nom(Request $request, ?string $ignorer = null): string
    {
        /** @var array{nom: string} $saisie */
        $saisie = $request->validate(
            ['nom' => [
                'required', 'string', 'max:120',
                Rule::unique('zones', 'nom')->where('jardin_id', $this->jardinCourant->id())->ignore($ignorer),
            ]],
            [
                'nom.required' => 'Donnez un nom à la zone.',
                'nom.max' => 'Le nom d\'une zone tient en 120 caractères.',
                'nom.unique' => 'Une zone de ce jardin porte déjà ce nom.',
            ],
        );

        return $saisie['nom'];
    }
}
