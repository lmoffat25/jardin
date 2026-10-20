<?php

namespace Src\Comptes\Domaine;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Un profil climatique (CDC §5.4) : référentiel partagé par tous les jardins,
 * écrit par le seul jeu de référence. Le modèle refuse toute écriture.
 *
 * @property int $id
 * @property string $libelle
 * @property int $decalage_jours
 * @property int $ordre_affichage
 */
class ProfilClimatique extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'profils_climatiques';

    protected $keyType = 'int';

    protected $guarded = [];

    protected static function booted(): void
    {
        $refuser = static fn (): never => throw new LogicException('Les profils climatiques sont en lecture seule : le jeu de référence les écrit.');
        static::saving($refuser);
        static::deleting($refuser);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'decalage_jours' => 'integer',
            'ordre_affichage' => 'integer',
        ];
    }
}
