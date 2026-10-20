<?php

namespace Src\Echeances\Domaine;

use Database\Factories\IntervalleSaisonnierFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Echeances\Moteur\IntervalleSaisonnier as SaisonDuMoteur;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une saison d'une règle R1 (RG-25) : de `mois_debut` à `mois_fin`, tous les
 * `intervalle_jours` ; 0 = hors saison.
 *
 * @property string $id
 * @property string $regle_id
 * @property int $mois_debut
 * @property int $mois_fin
 * @property int $intervalle_jours
 */
class IntervalleSaisonnier extends Model
{
    /** @use HasFactory<IntervalleSaisonnierFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'intervalles_saisonniers';

    protected $guarded = ['id'];

    /** @return BelongsTo<Regle, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(Regle::class);
    }

    public function versDomaine(): SaisonDuMoteur
    {
        return new SaisonDuMoteur($this->mois_debut, $this->mois_fin, $this->intervalle_jours);
    }

    protected static function newFactory(): IntervalleSaisonnierFactory
    {
        return IntervalleSaisonnierFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mois_debut' => 'integer', 'mois_fin' => 'integer', 'intervalle_jours' => 'integer'];
    }
}
