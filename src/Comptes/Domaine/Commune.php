<?php

namespace Src\Comptes\Domaine;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Une commune du référentiel climatique (RG-52, D-66, D-86) : référence
 * partagée par tous les jardins, écrite par le seul import
 * (`jardin:importer-communes`, par `upsert`). Le modèle refuse toute autre
 * écriture.
 *
 * @property string $code_insee
 * @property string $nom
 * @property string $nom_recherche
 * @property string $codes_postaux séparés d'une espace
 * @property int $altitude_m
 * @property string $station
 * @property float $ecart_c
 * @property int $decalage_jours
 * @property int $profil_propose_id
 */
class Commune extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'communes';

    protected $primaryKey = 'code_insee';

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        $refuser = static fn (): never => throw new LogicException('Les communes sont en lecture seule : l\'import les écrit.');
        static::saving($refuser);
        static::deleting($refuser);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'altitude_m' => 'integer',
            'ecart_c' => 'float',
            'decalage_jours' => 'integer',
            'profil_propose_id' => 'integer',
        ];
    }
}
