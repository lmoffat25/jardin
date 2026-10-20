<?php

namespace Src\Jardin\Localisation;

use Illuminate\Database\Eloquent\Builder;
use Src\Jardin\Domaine\GeoJson;
use Src\Jardin\Domaine\Plante;

/**
 * Les plantes à proposer sur le terrain (critère 9, RG-15, Q8, écran T3) : les
 * dix plus proches d'une position, par distance haversine au point
 * représentatif de leur géométrie (D-26), puis les autres plantes de la zone
 * courante, par nom. Plantes actives du jardin courant seulement. La recherche
 * prend le nom (sans casse, `%` et `_` à la lettre) ou le code court exact.
 * Rien n'est jamais présélectionné, même une candidate seule : le relevé à
 * 10 m sous les arbres ne départage pas deux voisins (Q8).
 *
 * @phpstan-import-type Coordonnees from GeoJson
 *
 * @phpstan-type Proche array{id: string, nom: string, code_court: string, distance_m: int}
 * @phpstan-type DeLaZone array{id: string, nom: string, code_court: string}
 */
final class PlantesProches
{
    /** Le nombre de candidates par proximité (Q8). */
    private const NOMBRE = 10;

    /** Le rayon moyen de la Terre, en mètres. */
    private const RAYON_TERRE = 6371008.8;

    /**
     * @param  array{lng: float, lat: float}|null  $ici
     * @return array{proches: list<Proche>, zone: list<DeLaZone>}
     */
    public function pour(?array $ici, ?string $zone, string $q): array
    {
        $proches = [];
        if ($ici !== null) {
            // Une requête : les plantes actives situées, avec leur géométrie.
            $proches = $this->plantes($q)
                ->join('geometries', 'geometries.plante_id', '=', 'plantes.id')
                ->get(['plantes.id', 'plantes.nom', 'plantes.code_court', 'geometries.coordonnees'])
                ->map(function (Plante $p) use ($ici): array {
                    $brut = $p->getAttribute('coordonnees');
                    /** @var Coordonnees $geojson */
                    $geojson = json_decode(is_string($brut) ? $brut : '', true, flags: JSON_THROW_ON_ERROR);

                    return [
                        'id' => $p->id,
                        'nom' => $p->nom,
                        'code_court' => $p->code_court,
                        'distance' => self::distance($ici, GeoJson::pointRepresentatif($geojson)),
                    ];
                })
                ->sortBy([['distance', 'asc'], ['nom', 'asc']])
                ->take(self::NOMBRE)
                ->map(fn (array $p): array => [
                    'id' => $p['id'], 'nom' => $p['nom'], 'code_court' => $p['code_court'], 'distance_m' => (int) round($p['distance']),
                ])
                ->values()
                ->all();
        }

        $deLaZone = $zone === null ? [] : $this->plantes($q)
            ->where('plantes.zone_id', $zone)
            ->whereNotIn('plantes.id', array_column($proches, 'id'))
            ->orderBy('plantes.nom')
            ->orderBy('plantes.id')
            ->get(['plantes.id', 'plantes.nom', 'plantes.code_court'])
            ->map(fn (Plante $p): array => ['id' => $p->id, 'nom' => $p->nom, 'code_court' => $p->code_court])
            ->all();

        return ['proches' => array_values($proches), 'zone' => array_values($deLaZone)];
    }

    /** @return Builder<Plante> les plantes actives du jardin courant, cherchées */
    private function plantes(string $q): Builder
    {
        return Plante::query()
            ->where('plantes.etat', 'active')
            ->when($q !== '', fn (Builder $r) => $r->where(fn (Builder $r) => $r
                ->where('plantes.nom', 'ilike', '%'.addcslashes($q, '\\%_').'%')
                ->orWhereRaw('lower(plantes.code_court) = lower(?)', [$q])));
    }

    /**
     * La distance haversine entre deux points WGS 84, en mètres.
     *
     * @param  array{lng: float, lat: float}  $a
     * @param  array{0: float, 1: float}  $b  `[lng, lat]`
     */
    private static function distance(array $a, array $b): float
    {
        $phi1 = deg2rad($a['lat']);
        $phi2 = deg2rad($b[1]);
        $h = sin(($phi2 - $phi1) / 2) ** 2 + cos($phi1) * cos($phi2) * sin(deg2rad($b[0] - $a['lng']) / 2) ** 2;

        return 2 * self::RAYON_TERRE * asin(min(1.0, sqrt($h)));
    }
}
