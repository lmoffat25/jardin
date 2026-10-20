<?php

namespace Src\Jardin\Localisation;

use Src\Jardin\Domaine\Geometrie;
use Src\Jardin\Domaine\Plante;
use Src\Support\Horloge;

/**
 * La position du téléphone, relevée à une intervention (RG-15), ne défait
 * jamais le plan (RG-48, S18, D-17) : elle crée le point d'une plante qui n'en
 * a pas, ou remplace un relevé `gps` moins précis ; une position `plan` ou
 * `manuel` ne bouge pas. L'intervention, elle, garde toujours la sienne.
 *
 * @phpstan-type Position array{lng: float|int, lat: float|int, precision_m?: float|int|null}
 */
final class EnregistrerPosition
{
    /** `geometries.precision_m` est un `smallint`. */
    private const PRECISION_MAX = 32767;

    public function __construct(private readonly Horloge $horloge) {}

    /** @param  Position  $position */
    public function depuisIntervention(Plante $plante, array $position): void
    {
        $precision = isset($position['precision_m']) ? (int) min(round($position['precision_m']), self::PRECISION_MAX) : null;
        $actuelle = Geometrie::query()->where('plante_id', $plante->id)->lockForUpdate()->first();
        if ($actuelle !== null && ! self::remplacable($actuelle, $precision)) {
            return;
        }

        Geometrie::query()->updateOrCreate(['plante_id' => $plante->id], [
            'forme' => 'point',
            'coordonnees' => ['type' => 'Point', 'coordinates' => [$position['lng'], $position['lat']]],
            'precision_m' => $precision,
            'source' => 'gps',
            'relevee_le' => $this->horloge->maintenant(),
        ]);
    }

    /**
     * Un relevé `gps` cède à un relevé strictement plus précis ; sans précision
     * connue, l'ancien cède à tout relevé qui en a une, le nouveau à aucun.
     */
    private static function remplacable(Geometrie $actuelle, ?int $precision): bool
    {
        if ($actuelle->source !== 'gps' || $precision === null) {
            return false;
        }

        return $actuelle->precision_m === null || $precision < $actuelle->precision_m;
    }
}
