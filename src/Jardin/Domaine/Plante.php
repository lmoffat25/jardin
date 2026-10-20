<?php

namespace Src\Jardin\Domaine;

use Database\Factories\PlanteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une plante du jardin (CDC §3.3), désignée par son code court (RG-13).
 *
 * @property string $id
 * @property string $zone_id
 * @property string $code_court
 * @property string $nom
 */
class Plante extends Model
{
    /** @use HasFactory<PlanteFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'plantes';

    protected $guarded = ['id'];

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    protected static function newFactory(): PlanteFactory
    {
        return PlanteFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'espece_id' => 'integer',
            'plantee_le' => 'immutable_date',
            'age_estime_ans' => 'integer',
            'fin_le' => 'immutable_date',
            'archivee_le' => 'immutable_datetime',
            'cree_le' => 'immutable_datetime',
        ];
    }
}
