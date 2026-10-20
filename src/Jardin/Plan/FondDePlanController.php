<?php

namespace Src\Jardin\Plan;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Src\Jardin\Domaine\FondDePlan;
use Src\Journal\Photos\AttacherPhoto;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le fond de plan du jardin courant (V120, écran B13) : son import par le
 * propriétaire, la lecture du fond actif et de son image par le membre. Tout
 * passe par le filtre du jardin : le fond de B est introuvable depuis A.
 *
 * @phpstan-import-type Point from ImporterFondDePlan
 */
final class FondDePlanController
{
    /** Comme une photo : 20 Mo, en Ko, et 12 000 px de côté (11a). */
    private const POIDS_MAX_KO = 20 * 1024;

    private const COTE_MAX_PX = 12000;

    public function importer(Request $request, ImporterFondDePlan $importer): JsonResponse|RedirectResponse
    {
        /** @var array{points: list<array{x: string|int|float, y: string|int|float, lng: string|int|float, lat: string|int|float}>} $valide */
        $valide = $request->validate([
            'fichier' => [
                'bail',
                'required',
                'file',
                // Le type lu dans le contenu, jamais celui qu'annonce le client.
                function (string $attribut, mixed $valeur, Closure $echec): void {
                    if (! $valeur instanceof UploadedFile || AttacherPhoto::typeDe($valeur) === null) {
                        $echec('Un plan en JPEG, PNG ou WebP.');
                    }
                },
                'max:'.self::POIDS_MAX_KO,
                'dimensions:max_width='.self::COTE_MAX_PX.',max_height='.self::COTE_MAX_PX,
            ],
            'points' => ['required', 'array', 'list', 'between:2,3'],
            'points.*' => ['required', 'array:x,y,lng,lat'],
            'points.*.x' => ['required', 'numeric', 'min:0'],
            'points.*.y' => ['required', 'numeric', 'min:0'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
        ], [
            'fichier.required' => 'Choisissez l\'image du plan.',
            'fichier.file' => 'Un plan en JPEG, PNG ou WebP.',
            'fichier.max' => 'Un plan pèse 20 Mo au plus.',
            'fichier.dimensions' => 'Un plan mesure 12 000 px de côté au plus.',
            'points.required' => 'Calez le plan par deux ou trois points.',
            'points.between' => 'Calez le plan par deux ou trois points.',
            'points.*.x.min' => 'Ce point sort de l\'image.',
            'points.*.y.min' => 'Ce point sort de l\'image.',
            'points.*.lng.between' => 'Longitude entre -180 et 180.',
            'points.*.lat.between' => 'Latitude entre -90 et 90.',
        ]);

        $fichier = $request->file('fichier');
        if (! $fichier instanceof UploadedFile) {
            throw new LogicException('POST /fonds-de-plan validé sans fichier.');
        }

        // Un formulaire envoie des chaînes : « 400 » redevient 400, « 4.835 » 4.835.
        $nombre = fn (string|int|float $v): int|float => is_numeric($v) ? $v + 0 : 0;
        $points = array_map(fn (array $p): array => [
            'x' => $nombre($p['x']), 'y' => $nombre($p['y']), 'lng' => $nombre($p['lng']), 'lat' => $nombre($p['lat']),
        ], $valide['points']);

        $fond = $importer->executer($fichier, $points);

        return $request->wantsJson()
            ? response()->json(['id' => $fond->id, ...self::decrire($fond)], 201)
            : back();
    }

    /** Le fond actif, ou un fond vide : la carte s'affiche alors sur un fond neutre (RG-47). */
    public function actif(): JsonResponse
    {
        $fond = FondDePlan::query()->where('actif', true)->first();

        return response()->json($fond === null ? ['nature' => null, 'url' => null, 'calage' => null] : self::decrire($fond));
    }

    /** L'image d'un fond, par le filtre ; son contenu ne change jamais. */
    public function image(string $fond): StreamedResponse
    {
        $fond = Str::isUuid($fond) ? FondDePlan::query()->findOrFail($fond) : abort(404);
        if ($fond->fichier === null) {
            abort(404);
        }

        return Storage::disk('local')->response($fond->fichier, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /** @return array{nature: string, url: string|null, calage: list<Point>|null} */
    private static function decrire(FondDePlan $fond): array
    {
        return [
            'nature' => $fond->nature,
            'url' => $fond->fichier === null ? null : "/fonds-de-plan/{$fond->id}/image",
            'calage' => $fond->calage,
        ];
    }
}
