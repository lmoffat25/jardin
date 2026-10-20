<?php

namespace Src\Comptes\Domaine;

use Database\Factories\JardinFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Jardin\Domaine\Zone;

/**
 * Le jardin lui-même, référentiel du compte : il n'est pas filtré par le
 * jardin courant, c'est lui qui le définit (liste du test V34).
 *
 * @property string $id
 * @property string $proprietaire_id
 * @property string $nom
 * @property int $profil_climatique_id
 */
class Jardin extends Model
{
    /** @use HasFactory<JardinFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'jardins';

    protected $guarded = ['id'];

    /** @return HasMany<Zone, $this> */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    protected static function newFactory(): JardinFactory
    {
        return JardinFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'profil_climatique_id' => 'integer',
            'altitude_m' => 'integer',
            'surface_m2' => 'integer',
            'quota_photos_mo' => 'integer',
            'cree_le' => 'immutable_datetime',
        ];
    }
}
