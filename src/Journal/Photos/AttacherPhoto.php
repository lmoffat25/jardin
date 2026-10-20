<?php

namespace Src\Journal\Photos;

use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Quotas\CalculerOccupation;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;
use Src\Journal\Domaine\Photo;
use Src\Journal\Saisie\Introuvable;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;

/**
 * Joint une photo à une plante ou à une intervention du jardin courant
 * (V115, RG-35, RG-38). Idempotente comme une saisie (H5, D-15) : un renvoi
 * de l'identifiant ne vérifie ni n'écrit rien, il rend l'état stocké. Le
 * quota se vérifie avant toute écriture (V117) ; l'original est rangé sous
 * `photos/{jardin}/{annee}/{empreinte}.{ext}` sur le disque privé (A21), et
 * ses dérivées partent en file (A19, V116). La photo est une mutation à part
 * de l'intervention (H10) : son échec ne touche pas au fait.
 */
final class AttacherPhoto
{
    public const QUOTA_ATTEINT = 'Quota atteint : ce jardin n\'a plus de place pour les photos.';

    public const INTERVENTION_INTROUVABLE = 'Intervention introuvable dans ce jardin.';

    public const ID_PRIS = 'Identifiant de photo déjà pris.';

    /** Les types acceptés, et l'extension sous laquelle l'original est rangé. */
    public const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private readonly JardinCourant $jardinCourant,
        private readonly Horloge $horloge,
        private readonly CalculerOccupation $occupation,
    ) {}

    /**
     * Le type de l'image, lu dans son contenu ; `null` hors des types acceptés.
     *
     * @return key-of<self::EXTENSIONS>|null
     */
    public static function typeDe(UploadedFile $fichier): ?string
    {
        $type = (new finfo(FILEINFO_MIME_TYPE))->file($fichier->getRealPath());

        return is_string($type) && array_key_exists($type, self::EXTENSIONS) ? $type : null;
    }

    /**
     * @param  array{id: string, plante_id?: string|null, intervention_id?: string|null, prise_le?: string|null}  $donnees
     *
     * @throws ValidationException
     */
    public function executer(array $donnees, UploadedFile $fichier): Photo
    {
        // 1. Un renvoi rend ce qui est stocké, sans rien vérifier ni écrire.
        $stockee = Photo::query()->find($donnees['id']);
        if ($stockee !== null) {
            return $stockee;
        }
        if (Photo::query()->withoutGlobalScopes()->whereKey($donnees['id'])->exists()) {
            throw ValidationException::withMessages(['id' => self::ID_PRIS]);
        }

        // 2. Le rattachement, lu sous le filtre : ce que B possède est introuvable.
        $planteId = $donnees['plante_id'] ?? null;
        $interventionId = $donnees['intervention_id'] ?? null;
        if ($planteId !== null && ! Plante::query()->whereKey($planteId)->exists()) {
            throw ValidationException::withMessages(['plante_id' => Introuvable::MESSAGE]);
        }
        if ($interventionId !== null && ! Intervention::query()->whereKey($interventionId)->exists()) {
            throw ValidationException::withMessages(['intervention_id' => self::INTERVENTION_INTROUVABLE]);
        }

        // 3. Le quota, avant d'écrire quoi que ce soit.
        $jardinId = $this->jardinCourant->id();
        $tailleKo = (int) ceil(((int) $fichier->getSize()) / 1024);
        $quotaKo = Jardin::query()->findOrFail($jardinId)->quota_photos_mo * 1024;
        if ($this->occupation->pourJardin() + $tailleKo > $quotaKo) {
            throw ValidationException::withMessages(['fichier' => self::QUOTA_ATTEINT]);
        }

        // 4. L'original sous son empreinte, puis la ligne, puis la file.
        $chemin = $fichier->getRealPath();
        $empreinte = hash_file('sha256', $chemin);
        $dimensions = getimagesize($chemin);
        $extension = self::EXTENSIONS[self::typeDe($fichier) ?? throw new LogicException('Type de photo non validé.')];
        $dossier = "photos/{$jardinId}/{$this->horloge->maintenant()->format('Y')}";
        $original = Storage::disk('local')->putFileAs($dossier, $fichier, "{$empreinte}.{$extension}");
        if ($original === false) {
            throw new LogicException("Écriture impossible de la photo dans {$dossier}.");
        }

        Photo::query()->insertOrIgnore([
            'id' => $donnees['id'],
            'jardin_id' => $jardinId,
            'plante_id' => $planteId,
            'intervention_id' => $interventionId,
            'empreinte' => $empreinte,
            'chemin_original' => $original,
            'taille_ko' => $tailleKo,
            'largeur_px' => $dimensions === false ? null : $dimensions[0],
            'hauteur_px' => $dimensions === false ? null : $dimensions[1],
            'prise_le' => $donnees['prise_le'] ?? null,
            'televersee_le' => $this->horloge->maintenant(),
        ]);
        CompresserPhoto::dispatch($donnees['id'], $jardinId);

        return Photo::query()->findOrFail($donnees['id']);
    }
}
