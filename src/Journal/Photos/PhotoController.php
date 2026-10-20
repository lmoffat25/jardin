<?php

namespace Src\Journal\Photos;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * `POST /photos` (V115–V118, écrans T4, T5, B6), ouvert au membre (D-49) :
 * une photo multipart sous l'identifiant du client, rattachée à une plante ou
 * à une intervention. Rend l'état stocké, identique au premier envoi et aux
 * renvois.
 */
final class PhotoController
{
    /** 20 Mo, en Ko comme le veut la règle `max` d'un fichier. */
    private const POIDS_MAX_KO = 20 * 1024;

    private const COTE_MAX_PX = 12000;

    public function attacher(Request $request, AttacherPhoto $attacher): JsonResponse
    {
        /** @var array{id: string, plante_id?: string|null, intervention_id?: string|null, prise_le?: string|null} $valide */
        $valide = $request->validate([
            'id' => ['required', 'uuid'],
            'fichier' => [
                'bail',
                'required',
                'file',
                // Le type lu dans le contenu, jamais celui qu'annonce le client.
                function (string $attribut, mixed $valeur, Closure $echec): void {
                    if (! $valeur instanceof UploadedFile || AttacherPhoto::typeDe($valeur) === null) {
                        $echec('Une photo JPEG, PNG ou WebP.');
                    }
                },
                'max:'.self::POIDS_MAX_KO,
                'dimensions:max_width='.self::COTE_MAX_PX.',max_height='.self::COTE_MAX_PX,
            ],
            'plante_id' => ['nullable', 'uuid', 'required_without:intervention_id', 'prohibits:intervention_id'],
            'intervention_id' => ['nullable', 'uuid'],
            'prise_le' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'fichier.required' => 'Choisissez une photo.',
            'fichier.file' => 'Une photo JPEG, PNG ou WebP.',
            'fichier.max' => 'Une photo pèse 20 Mo au plus.',
            'fichier.dimensions' => 'Une photo mesure 12 000 px de côté au plus.',
            'plante_id.required_without' => 'Une photo se joint à une plante ou à une intervention.',
            'plante_id.prohibits' => 'Une photo se joint à une plante ou à une intervention, pas aux deux.',
            'prise_le.date_format' => 'Date invalide : AAAA-MM-JJ.',
        ]);

        $fichier = $request->file('fichier');
        if (! $fichier instanceof UploadedFile) {
            throw new LogicException('POST /photos validé sans fichier.');
        }

        $photo = $attacher->executer($valide, $fichier);

        return response()->json([
            'id' => $photo->id,
            'url' => "/photos/{$photo->id}/1200",
            'derivees_pretes' => $photo->derivees_pretes,
        ]);
    }
}
