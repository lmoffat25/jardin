<?php

namespace Src\Jardin\Plan;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Quotas\CalculerOccupation;
use Src\Jardin\Domaine\FondDePlan;
use Src\Journal\Photos\AttacherPhoto;
use Src\Support\Jardin\JardinCourant;

/**
 * Importe une image calée et en fait le fond actif du jardin courant (V120,
 * CDC §6 lot 4, RG-47). Les points de calage sont dans l'image ; le quota se
 * vérifie avant toute écriture, l'image comptant comme une photo (chapitre 4
 * §3 bis, D-60). L'image est rangée sous `photos/{jardin}/plans/`, dans le
 * dossier du jardin, pour partir avec lui (D6). Le calage est stocké tel
 * quel : la transformation affine est en partie B.
 *
 * @phpstan-type Point array{x: int|float, y: int|float, lng: int|float, lat: int|float}
 */
final class ImporterFondDePlan
{
    public const QUOTA_ATTEINT = 'Quota atteint : ce jardin n\'a plus de place pour ce plan.';

    public function __construct(
        private readonly JardinCourant $jardinCourant,
        private readonly CalculerOccupation $occupation,
    ) {}

    /**
     * @param  list<Point>  $points
     *
     * @throws ValidationException
     */
    public function executer(UploadedFile $fichier, array $points): FondDePlan
    {
        // 1. Chaque point dans l'image.
        $chemin = $fichier->getRealPath();
        $dimensions = getimagesize($chemin);
        if ($dimensions === false) {
            throw ValidationException::withMessages(['fichier' => 'Un plan en JPEG, PNG ou WebP.']);
        }
        [$largeur, $hauteur] = $dimensions;
        $erreurs = [];
        foreach ($points as $i => $point) {
            if ($point['x'] > $largeur) {
                $erreurs["points.{$i}.x"] = "Ce point sort de l'image ({$largeur} px de large).";
            }
            if ($point['y'] > $hauteur) {
                $erreurs["points.{$i}.y"] = "Ce point sort de l'image ({$hauteur} px de haut).";
            }
        }
        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }

        // 2. Le quota, avant d'écrire quoi que ce soit.
        $jardinId = $this->jardinCourant->id();
        $tailleKo = (int) ceil(((int) $fichier->getSize()) / 1024);
        $quotaKo = Jardin::query()->findOrFail($jardinId)->quota_photos_mo * 1024;
        if ($this->occupation->pourJardin() + $tailleKo > $quotaKo) {
            throw ValidationException::withMessages(['fichier' => self::QUOTA_ATTEINT]);
        }

        // 3. L'image sous son empreinte, puis l'ancien fond éteint avant le
        // nouveau allumé : l'index partiel n'en tolère qu'un actif.
        $extension = AttacherPhoto::EXTENSIONS[AttacherPhoto::typeDe($fichier) ?? throw new LogicException('Type de plan non validé.')];
        $dossier = "photos/{$jardinId}/plans";
        $image = Storage::disk('local')->putFileAs($dossier, $fichier, hash_file('sha256', $chemin).".{$extension}");
        if ($image === false) {
            throw new LogicException("Écriture impossible du plan dans {$dossier}.");
        }

        return DB::transaction(function () use ($image, $points): FondDePlan {
            FondDePlan::query()->where('actif', true)->update(['actif' => false]);

            return FondDePlan::query()->create([
                'nature' => 'image',
                'fichier' => $image,
                'calage' => $points,
                'actif' => true,
            ]);
        });
    }
}
