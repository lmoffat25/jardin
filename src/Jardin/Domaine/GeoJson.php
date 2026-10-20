<?php

namespace Src\Jardin\Domaine;

/**
 * La géométrie d'une plante en GeoJSON WGS 84 (RG-14, S17) : un point, une
 * ligne d'au moins deux points, ou un polygone fermé d'un seul anneau (V1)
 * d'au moins quatre points. Une position est `[longitude, latitude]`, deux
 * nombres, sans altitude.
 *
 * Son point représentatif sert aux distances (D-26) : le point ; le sommet
 * médian d'une ligne ; le centre des sommets d'un polygone, le sommet de
 * fermeture compté une fois.
 *
 * @phpstan-type Position array{0: float|int, 1: float|int}
 * @phpstan-type Coordonnees array{type: string, coordinates: array<mixed>}
 */
final class GeoJson
{
    /** Le type GeoJSON et la forme de `geometries.forme`. */
    public const FORMES = ['Point' => 'point', 'LineString' => 'ligne', 'Polygon' => 'polygone'];

    /** Ce qui ne va pas dans `coordinates` pour ce type, ou null. */
    public static function erreur(string $type, mixed $coordonnees): ?string
    {
        return match ($type) {
            'Point' => self::position($coordonnees) ? null : 'Un point est une longitude et une latitude en WGS 84.',
            'LineString' => self::positions($coordonnees, 2) ? null : 'Une ligne a au moins deux points en WGS 84.',
            'Polygon' => self::anneau($coordonnees),
            default => 'Type de géométrie inconnu.',
        };
    }

    /**
     * Le point représentatif d'une géométrie valide, `[lng, lat]`.
     *
     * @param  Coordonnees  $geojson
     * @return array{0: float, 1: float}
     */
    public static function pointRepresentatif(array $geojson): array
    {
        $c = $geojson['coordinates'];
        if ($geojson['type'] === 'LineString') {
            /** @var list<Position> $c */
            return self::flottants($c[intdiv(count($c), 2)]);
        }
        if ($geojson['type'] === 'Polygon') {
            /** @var array{0: list<Position>} $c */
            return self::centre(array_slice($c[0], 0, -1));
        }

        /** @var Position $c */
        return self::flottants($c);
    }

    private static function anneau(mixed $coordonnees): ?string
    {
        if (! is_array($coordonnees) || ! array_is_list($coordonnees) || count($coordonnees) !== 1) {
            return 'Un polygone a un seul contour.';
        }
        $anneau = $coordonnees[0];
        if (! self::positions($anneau, 4)) {
            return 'Un polygone a au moins quatre points en WGS 84.';
        }

        /** @var list<Position> $anneau */
        return $anneau[0] == $anneau[count($anneau) - 1] ? null : 'Un polygone se referme sur son premier point.';
    }

    private static function positions(mixed $liste, int $minimum): bool
    {
        if (! is_array($liste) || ! array_is_list($liste) || count($liste) < $minimum) {
            return false;
        }
        foreach ($liste as $p) {
            if (! self::position($p)) {
                return false;
            }
        }

        return true;
    }

    private static function position(mixed $p): bool
    {
        return is_array($p) && array_is_list($p) && count($p) === 2
            && (is_int($p[0]) || is_float($p[0])) && (is_int($p[1]) || is_float($p[1]))
            && $p[0] >= -180 && $p[0] <= 180 && $p[1] >= -90 && $p[1] <= 90;
    }

    /**
     * @param  list<Position>  $sommets
     * @return array{0: float, 1: float}
     */
    private static function centre(array $sommets): array
    {
        $n = count($sommets);

        return [array_sum(array_column($sommets, 0)) / $n, array_sum(array_column($sommets, 1)) / $n];
    }

    /**
     * @param  Position  $p
     * @return array{0: float, 1: float}
     */
    private static function flottants(array $p): array
    {
        return [(float) $p[0], (float) $p[1]];
    }
}
