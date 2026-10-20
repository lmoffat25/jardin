<?php

namespace Src\Jardin\Localisation;

use Src\Jardin\Domaine\GeoJson;
use Src\Jardin\Domaine\Geometrie;
use Src\Jardin\Domaine\Plante;
use Src\Support\Horloge;

/**
 * La seule écriture de `geometries`, où s'applique RG-48 (S18, D-17) : une
 * source ne remplace qu'une source au plus aussi fiable, `plan` > `manuel` >
 * `gps`. Un tracé sur le plan remplace toujours ; des coordonnées saisies
 * remplacent tout sauf le plan ; un relevé du téléphone (RG-15), à la création
 * d'une plante ou à une intervention, ne remplace qu'un relevé `gps` moins
 * précis. L'intervention, elle, garde toujours sa position.
 *
 * @phpstan-import-type Coordonnees from GeoJson
 *
 * @phpstan-type Position array{lng: float|int, lat: float|int, precision_m?: float|int|null}
 */
final class EnregistrerPosition
{
    /** `geometries.precision_m` est un `smallint`. */
    private const PRECISION_MAX = 32767;

    /** La fiabilité de chaque source : la plus haute gagne. */
    private const RANG = ['gps' => 1, 'manuel' => 2, 'plan' => 3];

    public function __construct(private readonly Horloge $horloge) {}

    /** @param  Coordonnees  $geojson  validé par `GeoJson` */
    public function depuisPlan(Plante $plante, array $geojson): void
    {
        $this->ecrire($plante, $geojson, 'plan', null);
    }

    /** @param  Coordonnees  $geojson  validé par `GeoJson` */
    public function depuisSaisie(Plante $plante, array $geojson): void
    {
        $this->ecrire($plante, $geojson, 'manuel', null);
    }

    /** @param  Position  $position */
    public function depuisCreation(Plante $plante, array $position): void
    {
        $this->depuisTelephone($plante, $position);
    }

    /** @param  Position  $position */
    public function depuisIntervention(Plante $plante, array $position): void
    {
        $this->depuisTelephone($plante, $position);
    }

    /** @param  Position  $position */
    private function depuisTelephone(Plante $plante, array $position): void
    {
        $precision = isset($position['precision_m']) ? (int) min(round($position['precision_m']), self::PRECISION_MAX) : null;

        $this->ecrire($plante, ['type' => 'Point', 'coordinates' => [$position['lng'], $position['lat']]], 'gps', $precision);
    }

    /** @param  Coordonnees  $geojson */
    private function ecrire(Plante $plante, array $geojson, string $source, ?int $precision): void
    {
        $actuelle = Geometrie::query()->where('plante_id', $plante->id)->lockForUpdate()->first();
        if ($actuelle !== null && ! self::remplacable($actuelle, $source, $precision)) {
            return;
        }

        Geometrie::query()->updateOrCreate(['plante_id' => $plante->id], [
            'forme' => GeoJson::FORMES[$geojson['type']],
            'coordonnees' => $geojson,
            'precision_m' => $precision,
            'source' => $source,
            // En UTC : Eloquent écrit l'heure sans son fuseau, que la base lit en UTC.
            'relevee_le' => $this->horloge->maintenant()->setTimezone(new \DateTimeZone('UTC')),
        ]);
    }

    /**
     * Une source plus fiable remplace, une moins fiable jamais. Entre deux
     * relevés `gps`, le nouveau ne gagne que strictement plus précis ; sans
     * précision connue, l'ancien cède à tout relevé qui en a une, le nouveau à
     * aucun. Entre deux `plan` ou deux `manuel`, le dernier geste gagne.
     */
    private static function remplacable(Geometrie $actuelle, string $source, ?int $precision): bool
    {
        $ecart = self::RANG[$source] <=> self::RANG[$actuelle->source];
        if ($ecart !== 0 || $source !== 'gps') {
            return $ecart >= 0;
        }
        if ($precision === null) {
            return false;
        }

        return $actuelle->precision_m === null || $precision < $actuelle->precision_m;
    }
}
