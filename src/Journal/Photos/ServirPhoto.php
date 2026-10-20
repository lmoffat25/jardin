<?php

namespace Src\Journal\Photos;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Journal\Domaine\Photo;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /photos/{photo}/{taille}` : une photo du jardin courant, par le filtre
 * (une photo de B est introuvable). Tant que ses dérivées ne sont pas prêtes,
 * l'original est servi pour toutes les tailles (chapitre 3 §12) ; une fois
 * prêtes, l'adresse ne change plus de contenu et se met en cache pour de bon.
 */
final class ServirPhoto
{
    public function __invoke(string $photo, string $taille): StreamedResponse
    {
        $photo = Str::isUuid($photo) ? Photo::query()->findOrFail($photo) : abort(404);
        $derivee = $taille !== 'original' && $photo->derivees_pretes;
        $chemin = $derivee ? $photo->cheminDerivee($taille) : $photo->chemin_original;
        $definitive = $derivee || $taille === 'original';

        return Storage::disk('local')->response($chemin, null, [
            'Cache-Control' => $definitive ? 'private, max-age=31536000, immutable' : 'private, no-cache',
        ]);
    }
}
