<?php

namespace Src\Echeances\Domaine;

use Carbon\CarbonImmutable;
use Database\Factories\EcheanceMaterialiseeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une occurrence close, écrite en table `echeances` (D3, S10) : `manquee`,
 * `suspendue` ou `soldee`. Les échéances à venir ne s'écrivent pas, elles se
 * calculent ; leur identité est `(regle_id, due_le)` (S16).
 *
 * @property string $id
 * @property string $regle_id
 * @property string $plante_id
 * @property CarbonImmutable $due_le
 * @property int $decalage_climatique_applique_j
 * @property string $etat
 * @property string $explication
 */
class EcheanceMaterialisee extends Model
{
    /** @use HasFactory<EcheanceMaterialiseeFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public const CREATED_AT = 'materialisee_le';

    public const UPDATED_AT = null;

    protected $table = 'echeances';

    protected $guarded = ['id'];

    /** @return BelongsTo<Regle, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(Regle::class);
    }

    /** @return BelongsTo<Plante, $this> */
    public function plante(): BelongsTo
    {
        return $this->belongsTo(Plante::class);
    }

    protected static function newFactory(): EcheanceMaterialiseeFactory
    {
        return EcheanceMaterialiseeFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_le' => 'immutable_date',
            'fenetre_debut' => 'immutable_date',
            'fenetre_fin' => 'immutable_date',
            'optimal_debut' => 'immutable_date',
            'optimal_fin' => 'immutable_date',
            'decalage_climatique_applique_j' => 'integer',
            'materialisee_le' => 'immutable_datetime',
        ];
    }
}
