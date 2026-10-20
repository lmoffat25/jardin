<?php

namespace Src\Journal\Photos;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use LogicException;
use Src\Journal\Domaine\Photo;
use Src\Support\Jardin\JobDeJardin;

/**
 * Les dérivées WebP d'une photo, hors de la requête (A19, V116) : 160 px et
 * 1 200 px sur le côté le plus long, jamais agrandies, rangées sous
 * l'empreinte (A21), puis `derivees_pretes`. Porte son jardin (A20). Trois
 * essais ; en échec, l'original reste servi (`ServirPhoto`).
 */
final class CompresserPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, JobDeJardin, Queueable;

    /** Côté le plus long de chaque dérivée, en pixels. */
    public const TAILLES = [160, 1200];

    private const QUALITE = 80;

    public int $tries = 3;

    public function __construct(public readonly string $photoId, string $jardinId)
    {
        $this->jardinId = $jardinId;
    }

    public function handle(): void
    {
        $photo = Photo::query()->findOrFail($this->photoId);
        $disque = Storage::disk('local');
        $original = $disque->get($photo->chemin_original)
            ?? throw new LogicException("Original absent : {$photo->chemin_original}.");

        $images = ImageManager::gd();
        foreach (self::TAILLES as $taille) {
            $derivee = $images->read($original)->scaleDown($taille, $taille)->toWebp(self::QUALITE);
            $disque->put($photo->cheminDerivee((string) $taille), (string) $derivee);
        }

        $photo->derivees_pretes = true;
        $photo->save();
    }
}
