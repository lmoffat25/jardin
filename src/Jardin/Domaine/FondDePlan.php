<?php

namespace Src\Jardin\Domaine;

use Carbon\CarbonImmutable;
use Database\Factories\FondDePlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Un fond de plan du jardin (CDC §6 lot 4, chapitre 4 §3 bis) : une image
 * calée par deux ou trois points, ou les orthophotos IGN. Un seul est actif
 * par jardin (index partiel `fonds_de_plan_un_actif`). Aucune fonction n'en
 * dépend (RG-47).
 *
 * @property string $id
 * @property 'image'|'orthophoto' $nature
 * @property string|null $fichier
 * @property list<array{x: int|float, y: int|float, lng: float, lat: float}>|null $calage
 * @property bool $actif
 * @property CarbonImmutable $cree_le
 */
class FondDePlan extends Model
{
    /** @use HasFactory<FondDePlanFactory> */
    use AppartientAuJardin, HasFactory, HasUuids;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'fonds_de_plan';

    protected $guarded = [];

    protected static function newFactory(): FondDePlanFactory
    {
        return FondDePlanFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'calage' => 'array',
            'actif' => 'boolean',
            'cree_le' => 'immutable_datetime',
        ];
    }
}
