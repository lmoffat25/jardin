<?php

namespace Src\Bibliotheque\Domaine;

use Illuminate\Database\Eloquent\Model;
use Src\Bibliotheque\TypesIntervention\TypesVisibles;

/**
 * Un type d'intervention (CDC §3.7) : fourni quand `jardin_id` est nul
 * (jeu de référence), propre à un jardin sinon. Sans le trait de jardin : les
 * fournis n'en ont pas. Ses lectures passent par le filtre `TypesVisibles`
 * (D-55) : les fournis et ceux du jardin courant. Un lecteur des seuls
 * fournis hors jardin écrit `withoutGlobalScopes()->whereNull('jardin_id')`
 * (D-40).
 *
 * @property int $id
 * @property string|null $jardin_id
 * @property string $libelle
 * @property string $famille
 * @property bool $demande_quantite
 * @property bool $demande_produit
 */
class TypeIntervention extends Model
{
    public $timestamps = false;

    protected $table = 'types_intervention';

    protected $guarded = ['id'];

    /** Les familles, en code court (D-78) : une liste de codes, pas de types (RG-03). */
    public const FAMILLES = ['taille', 'traitement', 'sol', 'eau', 'entretien', 'cycle', 'recolte'];

    protected static function booted(): void
    {
        static::addGlobalScope(new TypesVisibles);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['id' => 'integer', 'demande_quantite' => 'boolean', 'demande_produit' => 'boolean'];
    }
}
