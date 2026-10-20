<?php

namespace Src\Bibliotheque\Domaine;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Une source d'une règle de référence (B1, B2, D-39) : la page consultée, son
 * libellé et la date de consultation. Référentiel écrit par le seul
 * chargement de la bibliothèque ; le modèle refuse toute autre écriture.
 *
 * @property int $id
 * @property int $regle_reference_id
 * @property string $url
 * @property string|null $libelle
 * @property CarbonImmutable $consultee_le
 */
class SourceRegle extends Model
{
    public $timestamps = false;

    protected $table = 'sources_regle';

    protected $guarded = [];

    /** @return BelongsTo<RegleReference, $this> */
    public function regleReference(): BelongsTo
    {
        return $this->belongsTo(RegleReference::class);
    }

    protected static function booted(): void
    {
        $refuser = static fn (): never => throw new LogicException('Les sources des règles sont en lecture seule : le chargement de la bibliothèque les écrit.');
        static::saving($refuser);
        static::deleting($refuser);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['id' => 'integer', 'regle_reference_id' => 'integer', 'consultee_le' => 'immutable_date'];
    }
}
