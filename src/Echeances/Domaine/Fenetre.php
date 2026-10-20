<?php

namespace Src\Echeances\Domaine;

use Database\Factories\FenetreFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Echeances\Moteur\Fenetre as FenetreDuMoteur;
use Src\Echeances\Moteur\JourMois;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Une fenêtre d'une règle R2 (RG-36), en jours/mois du climat de référence
 * (S4), avec son optimale et ses butoirs facultatifs (RG-44).
 *
 * @property string $id
 * @property string $regle_id
 * @property int $debut_jour
 * @property int $debut_mois
 * @property int $fin_jour
 * @property int $fin_mois
 * @property int|null $optimal_debut_jour
 * @property int|null $optimal_debut_mois
 * @property int|null $optimal_fin_jour
 * @property int|null $optimal_fin_mois
 * @property int|null $butoir_debut_jour
 * @property int|null $butoir_debut_mois
 * @property int|null $butoir_fin_jour
 * @property int|null $butoir_fin_mois
 * @property string|null $libelle
 * @property int $ordre
 */
class Fenetre extends Model
{
    /** @use HasFactory<FenetreFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'fenetres';

    protected $guarded = ['id'];

    /** @return BelongsTo<Regle, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(Regle::class);
    }

    public function versDomaine(): FenetreDuMoteur
    {
        return new FenetreDuMoteur(
            debut: new JourMois($this->debut_jour, $this->debut_mois),
            fin: new JourMois($this->fin_jour, $this->fin_mois),
            optimalDebut: self::jourMois($this->optimal_debut_jour, $this->optimal_debut_mois),
            optimalFin: self::jourMois($this->optimal_fin_jour, $this->optimal_fin_mois),
            butoirDebut: self::jourMois($this->butoir_debut_jour, $this->butoir_debut_mois),
            butoirFin: self::jourMois($this->butoir_fin_jour, $this->butoir_fin_mois),
            libelle: $this->libelle,
            ordre: $this->ordre,
        );
    }

    protected static function newFactory(): FenetreFactory
    {
        return FenetreFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        $entiers = ['debut', 'fin', 'optimal_debut', 'optimal_fin', 'butoir_debut', 'butoir_fin'];

        return array_fill_keys([...array_map(fn ($b) => "{$b}_jour", $entiers), ...array_map(fn ($b) => "{$b}_mois", $entiers), 'ordre'], 'integer');
    }

    private static function jourMois(?int $jour, ?int $mois): ?JourMois
    {
        return $jour === null || $mois === null ? null : new JourMois($jour, $mois);
    }
}
