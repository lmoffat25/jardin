<?php

namespace Src\Bibliotheque\Domaine;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Une version d'une règle de référence (CDC §5, RG-24, RG-31, D-39) : la clé
 * stable du fichier, ses paramètres (fenêtres, butoirs, intervalles,
 * périodicité) et ses sources. Une seule version en vigueur par clé
 * (`remplacee_le` nul). Référentiel écrit par le seul chargement de la
 * bibliothèque ; le modèle refuse toute autre écriture.
 *
 * @property int $id
 * @property int $espece_id
 * @property string|null $variante
 * @property int $type_intervention_id
 * @property string $type_regle
 * @property array<string, mixed> $parametres
 * @property string $cle
 * @property string $confiance
 * @property int $version
 * @property CarbonImmutable|null $remplacee_le
 */
class RegleReference extends Model
{
    public $timestamps = false;

    protected $table = 'regles_reference';

    protected $guarded = [];

    /** @return BelongsTo<Espece, $this> */
    public function espece(): BelongsTo
    {
        return $this->belongsTo(Espece::class);
    }

    /** @return BelongsTo<TypeIntervention, $this> */
    public function typeIntervention(): BelongsTo
    {
        return $this->belongsTo(TypeIntervention::class);
    }

    /** @return HasMany<SourceRegle, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(SourceRegle::class);
    }

    protected static function booted(): void
    {
        $refuser = static fn (): never => throw new LogicException('Les règles de référence sont en lecture seule : le chargement de la bibliothèque les écrit.');
        static::saving($refuser);
        static::deleting($refuser);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'espece_id' => 'integer',
            'type_intervention_id' => 'integer',
            'parametres' => 'array',
            'version' => 'integer',
            'remplacee_le' => 'immutable_datetime',
        ];
    }
}
