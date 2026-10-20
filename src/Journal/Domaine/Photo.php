<?php

namespace Src\Journal\Domaine;

use Carbon\CarbonImmutable;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une photo d'une plante (RG-35) ou d'une intervention (RG-38). L'id vient du
 * client (H5) ; l'original est rangé sous son empreinte (A21), ses dérivées
 * WebP à côté, une fois produites (A19).
 *
 * @property string $id
 * @property string|null $plante_id
 * @property string|null $intervention_id
 * @property string $empreinte
 * @property string $chemin_original
 * @property bool $derivees_pretes
 * @property int $taille_ko
 * @property CarbonImmutable $televersee_le
 */
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    /** Les tailles servies : les deux dérivées (côté le plus long), et l'original. */
    public const TAILLES = ['160', '1200', 'original'];

    public const CREATED_AT = 'televersee_le';

    public const UPDATED_AT = null;

    protected $table = 'photos';

    protected $guarded = [];

    /** Le chemin d'une dérivée, à côté de l'original : `{empreinte}-{taille}.webp`. */
    public function cheminDerivee(string $taille): string
    {
        return dirname($this->chemin_original)."/{$this->empreinte}-{$taille}.webp";
    }

    protected static function newFactory(): PhotoFactory
    {
        return PhotoFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'derivees_pretes' => 'boolean',
            'taille_ko' => 'integer',
            'largeur_px' => 'integer',
            'hauteur_px' => 'integer',
            'prise_le' => 'immutable_date',
            'televersee_le' => 'immutable_datetime',
        ];
    }
}
