<?php

namespace Src\Journal\Domaine;

use Carbon\CarbonImmutable;
use Database\Factories\InterventionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une intervention faite sur une plante (CDC §3.8), datée du jour civil où
 * elle a eu lieu (S3). L'id peut venir du client (S11, H5).
 *
 * @property string $id
 * @property string $plante_id
 * @property int $type_intervention_id
 * @property string $auteur_id
 * @property CarbonImmutable $faite_le
 */
class Intervention extends Model
{
    /** @use HasFactory<InterventionFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public const CREATED_AT = 'saisie_le';

    public const UPDATED_AT = null;

    protected $table = 'interventions';

    protected $guarded = [];

    /** @return BelongsTo<Plante, $this> */
    public function plante(): BelongsTo
    {
        return $this->belongsTo(Plante::class);
    }

    /** @return BelongsTo<TypeIntervention, $this> */
    public function typeIntervention(): BelongsTo
    {
        return $this->belongsTo(TypeIntervention::class);
    }

    protected static function newFactory(): InterventionFactory
    {
        return InterventionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type_intervention_id' => 'integer',
            'faite_le' => 'immutable_date',
            'duree_min' => 'integer',
            'quantite' => 'decimal:2',
            'saisie_le' => 'immutable_datetime',
            'synchronisee_le' => 'immutable_datetime',
        ];
    }
}
