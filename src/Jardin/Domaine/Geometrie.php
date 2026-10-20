<?php

namespace Src\Jardin\Domaine;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * La position d'une plante (CDC §3.7) : un point, une ligne ou un polygone en
 * GeoJSON WGS 84, jamais requêté (S6, S17), une seule par plante. Sa source dit
 * qui l'emporte (RG-48, S18) : `plan` > `manuel` > `gps`.
 *
 * @property string $id
 * @property string $plante_id
 * @property string $forme
 * @property array{type: string, coordinates: array<mixed>} $coordonnees
 * @property int|null $precision_m
 * @property string $source
 * @property CarbonImmutable $relevee_le
 */
class Geometrie extends Model
{
    use AppartientAuJardin, HasUuids;

    public $timestamps = false;

    protected $table = 'geometries';

    protected $guarded = ['id'];

    /** @return BelongsTo<Plante, $this> */
    public function plante(): BelongsTo
    {
        return $this->belongsTo(Plante::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'coordonnees' => 'array',
            'precision_m' => 'integer',
            'relevee_le' => 'immutable_datetime',
        ];
    }
}
