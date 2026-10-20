<?php

namespace Src\Jardin\Plantes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;
use Src\Journal\Domaine\Photo;

/**
 * `PUT /plantes/{plante}/couverture {photo_id}` (V119, RG-35, critère 14,
 * écran B6) : une photo de la plante, ou d'une de ses interventions, devient
 * sa couverture. Réservé au propriétaire, comme toute modification de la
 * plante. Plante et photo sont lues sous le filtre : depuis A, la plante de B
 * est introuvable, et la photo de B n'est pas une photo de la plante.
 */
final class CouvertureController
{
    public const PAS_DE_CETTE_PLANTE = 'Cette photo n\'est pas une photo de cette plante.';

    /**
     * Les adresses d'une couverture : la miniature et l'image d'affichage, ou
     * l'original tant que les dérivées ne sont pas prêtes (chapitre 3 §12).
     *
     * @return array{photo_id: string, miniature: string, affichage: string}|null
     */
    public static function adresses(?Photo $photo): ?array
    {
        if ($photo === null) {
            return null;
        }
        $base = "/photos/{$photo->id}/";

        return [
            'photo_id' => $photo->id,
            'miniature' => $base.($photo->derivees_pretes ? '160' : 'original'),
            'affichage' => $base.($photo->derivees_pretes ? '1200' : 'original'),
        ];
    }

    public function choisir(Request $request, string $plante): JsonResponse|RedirectResponse
    {
        $plante = Str::isUuid($plante) ? Plante::query()->findOrFail($plante) : abort(404);
        Gate::authorize('modifier', $plante);

        /** @var array{photo_id: string} $saisie */
        $saisie = $request->validate(
            ['photo_id' => ['required', 'uuid']],
            ['photo_id.required' => 'Choisissez une photo.', 'photo_id.uuid' => self::PAS_DE_CETTE_PLANTE],
        );

        $photo = Photo::query()
            ->whereKey($saisie['photo_id'])
            ->where(fn ($r) => $r
                ->where('plante_id', $plante->id)
                ->orWhereIn('intervention_id', Intervention::query()->select('id')->where('plante_id', $plante->id)))
            ->first();
        if ($photo === null) {
            throw ValidationException::withMessages(['photo_id' => self::PAS_DE_CETTE_PLANTE]);
        }

        $plante->update(['photo_couverture_id' => $photo->id]);
        $adresses = self::adresses($photo);

        return $request->wantsJson()
            ? response()->json(['id' => $plante->id, 'photo_couverture_id' => $photo->id, 'miniature' => $adresses['miniature'] ?? null])
            : redirect("/plantes/{$plante->id}");
    }
}
