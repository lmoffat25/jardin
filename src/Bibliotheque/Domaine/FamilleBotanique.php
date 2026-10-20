<?php

namespace Src\Bibliotheque\Domaine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Une famille botanique (chapitre 4 §3.3) : référentiel partagé par tous les
 * jardins, écrit par le seul chargement de la bibliothèque
 * (`bibliotheque:charger`, par le constructeur de requêtes). Le modèle refuse
 * toute autre écriture.
 *
 * @property int $id
 * @property string $nom
 * @property int|null $delai_retour_ans
 */
class FamilleBotanique extends Model
{
    public $timestamps = false;

    protected $table = 'familles_botaniques';

    protected $guarded = [];

    /** @return HasMany<Espece, $this> */
    public function especes(): HasMany
    {
        return $this->hasMany(Espece::class, 'famille_id');
    }

    protected static function booted(): void
    {
        $refuser = static fn (): never => throw new LogicException('Les familles botaniques sont en lecture seule : le chargement de la bibliothèque les écrit.');
        static::saving($refuser);
        static::deleting($refuser);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['id' => 'integer', 'delai_retour_ans' => 'integer'];
    }
}
