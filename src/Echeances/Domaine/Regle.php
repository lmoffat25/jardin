<?php

namespace Src\Echeances\Domaine;

use Carbon\CarbonImmutable;
use Database\Factories\RegleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Echeances\Moteur\IntervalleSaisonnier as SaisonDuMoteur;
use Src\Echeances\Moteur\Regle as RegleDuMoteur;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une version de règle d'entretien d'une plante (CDC §3.6) ; une seule active
 * par plante et par type (S15). Le pont la traduit pour le moteur (A5).
 *
 * @property string $id
 * @property string $plante_id
 * @property int $type_intervention_id
 * @property string $type_regle
 * @property int|null $intervalle_jours
 * @property int|null $periodicite_annees
 * @property string $confiance
 * @property string $origine
 * @property bool $est_active
 * @property int $version
 * @property CarbonImmutable|null $remplacee_le
 * @property CarbonImmutable $creee_le
 * @property-read Plante $plante
 * @property-read TypeIntervention $typeIntervention
 * @property-read Collection<int, Fenetre> $fenetres
 * @property-read Collection<int, IntervalleSaisonnier> $intervalles
 */
class Regle extends Model
{
    /** @use HasFactory<RegleFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public const CREATED_AT = 'creee_le';

    public const UPDATED_AT = null;

    protected $table = 'regles';

    protected $guarded = ['id'];

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

    /** @return HasMany<Fenetre, $this> */
    public function fenetres(): HasMany
    {
        return $this->hasMany(Fenetre::class);
    }

    /** @return HasMany<IntervalleSaisonnier, $this> */
    public function intervalles(): HasMany
    {
        return $this->hasMany(IntervalleSaisonnier::class);
    }

    /**
     * La règle telle que le moteur la lit ; fenêtres et saisons déjà chargées.
     * La date d'effet est le jour, à Paris, de la création de cette version.
     * Une R1 sans saison déclarée vaut toute l'année à son intervalle (D-98).
     */
    public function versDomaine(): RegleDuMoteur
    {
        $saisons = array_values($this->intervalles->map->versDomaine()->all());
        if ($this->type_regle === 'R1' && $saisons === []) {
            $saisons = [new SaisonDuMoteur(1, 12, (int) $this->intervalle_jours)];
        }

        return new RegleDuMoteur(
            id: $this->id,
            planteId: $this->plante_id,
            typeId: $this->type_intervention_id,
            typeRegle: $this->type_regle,
            periodiciteAnnees: $this->periodicite_annees ?? 1,
            fenetres: array_values($this->fenetres->map->versDomaine()->all()),
            intervalles: $saisons,
            confiance: $this->confiance,
            origine: $this->origine,
            effetLe: $this->creee_le->setTimezone('Europe/Paris')->toDateTimeImmutable(),
        );
    }

    protected static function newFactory(): RegleFactory
    {
        return RegleFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type_intervention_id' => 'integer',
            'regle_reference_id' => 'integer',
            'intervalle_jours' => 'integer',
            'periodicite_annees' => 'integer',
            'est_active' => 'boolean',
            'version' => 'integer',
            'remplacee_le' => 'immutable_datetime',
            'creee_le' => 'immutable_datetime',
        ];
    }
}
