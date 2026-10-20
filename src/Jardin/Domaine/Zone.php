<?php

namespace Src\Jardin\Domaine;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une zone du jardin (CDC §3.2).
 *
 * @property string $id
 * @property string $nom
 * @property string|null $zone_parente_id
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'zones';

    protected $guarded = ['id'];

    /** @return HasMany<Plante, $this> */
    public function plantes(): HasMany
    {
        return $this->hasMany(Plante::class);
    }

    protected static function newFactory(): ZoneFactory
    {
        return ZoneFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archivee_le' => 'immutable_datetime',
            'cree_le' => 'immutable_datetime',
        ];
    }
}
