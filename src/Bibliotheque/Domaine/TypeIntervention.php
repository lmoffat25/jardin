<?php

namespace Src\Bibliotheque\Domaine;

use Illuminate\Database\Eloquent\Model;

/**
 * Un type d'intervention (CDC §3.7) : fourni quand `jardin_id` est nul
 * (jeu de référence), propre à un jardin sinon. Sans le trait de jardin : les
 * fournis n'en ont pas. Le filtre des types visibles d'un jardin est posé au
 * 7c (D-55) ; d'ici là, un lecteur des fournis écrit
 * `withoutGlobalScopes()->whereNull('jardin_id')` (D-40).
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['id' => 'integer', 'demande_quantite' => 'boolean', 'demande_produit' => 'boolean'];
    }
}
