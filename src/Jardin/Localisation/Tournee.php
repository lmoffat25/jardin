<?php

namespace Src\Jardin\Localisation;

use Illuminate\Support\Collection;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\TableauDeBord\RegrouperEcheances;
use Src\Jardin\Domaine\GeoJson;
use Src\Jardin\Domaine\Geometrie;
use Src\Support\Jardin\JardinCourant;

/**
 * La tournée du jour (critère 21, D-65, écran T7) : les échéances dues
 * aujourd'hui, de plante en plante par plus proche voisin depuis la position,
 * à la distance haversine de leur point représentatif (D-26) ; puis les
 * plantes sans position, par zone (nom), par plante, par type. Sans position,
 * toute la tournée suit ce second ordre. Les échéances d'une même plante se
 * suivent.
 *
 * Lecture seule : contrairement à la liste du jour, la tournée n'écrit pas les
 * manquées, et l'ordre modifié à l'écran ne vaut que pour la sortie (T7 · 03).
 *
 * @phpstan-type Etape array{cle: array{regle_id: string, due_le: string}, plante: array{id: string, nom: string, code_court: string, zone: array{id: string, nom: string}}, type: array{id: int, libelle: string}, distance_m?: int}
 */
final class Tournee
{
    public function __construct(
        private readonly CalculEcheances $calcul,
        private readonly JardinCourant $jardinCourant,
    ) {}

    /**
     * @param  array{lng: float, lat: float}|null  $ici
     * @return list<Etape>
     */
    public function pour(?array $ici): array
    {
        $jardin = Jardin::query()->findOrFail($this->jardinCourant->id());
        $parPlante = $this->calcul->pourJardin($jardin)
            ->filter(fn (EcheanceCalculee $e) => in_array($e->echeance->etat, RegrouperEcheances::ETATS_DU_JOUR, true))
            ->sortBy(fn (EcheanceCalculee $e) => $e->type->libelle, SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy(fn (EcheanceCalculee $e) => $e->plante->id);

        $points = $ici === null ? [] : $this->points(array_map(strval(...), $parPlante->keys()->all()));
        $etapes = [];

        // Plus proche voisin : de la position, puis de la dernière plante visitée.
        $situees = array_intersect_key($points, $parPlante->all());
        $depuis = $ici;
        while ($depuis !== null && $situees !== []) {
            $distances = array_map(fn (array $p) => PlantesProches::distance($depuis, $p), $situees);
            uksort($distances, fn (string $a, string $b) => $distances[$a] <=> $distances[$b]
                ?: self::comparer($parPlante->get($a)?->first(), $parPlante->get($b)?->first(), false));
            $plante = (string) array_key_first($distances);
            foreach ($parPlante->get($plante, []) as $i => $e) {
                $etapes[] = [...self::etape($e), 'distance_m' => $i === 0 ? (int) round($distances[$plante]) : 0];
            }
            $depuis = ['lng' => $situees[$plante][0], 'lat' => $situees[$plante][1]];
            unset($situees[$plante]);
        }

        $sansPosition = $parPlante->reject(fn (Collection $echeances, string $plante) => isset($points[$plante]))
            ->sort(fn (Collection $a, Collection $b) => self::comparer($a->first(), $b->first(), true));
        foreach ($sansPosition as $echeances) {
            foreach ($echeances as $e) {
                $etapes[] = self::etape($e);
            }
        }

        return $etapes;
    }

    /**
     * Les points représentatifs des plantes situées, en une requête.
     *
     * @param  array<int, string>  $plantes
     * @return array<string, array{0: float, 1: float}>
     */
    private function points(array $plantes): array
    {
        return Geometrie::query()->whereIn('plante_id', $plantes)->get(['plante_id', 'coordonnees'])
            ->mapWithKeys(fn (Geometrie $g) => [$g->plante_id => GeoJson::pointRepresentatif($g->coordonnees)])
            ->all();
    }

    /** Par zone (si demandé), puis par nom de plante, puis par identifiant. */
    private static function comparer(?EcheanceCalculee $a, ?EcheanceCalculee $b, bool $parZone): int
    {
        return ($parZone ? strnatcasecmp($a->plante->zone->nom ?? '', $b->plante->zone->nom ?? '') : 0)
            ?: strnatcasecmp($a->plante->nom ?? '', $b->plante->nom ?? '')
            ?: strcmp($a->plante->id ?? '', $b->plante->id ?? '');
    }

    /** @return Etape */
    private static function etape(EcheanceCalculee $e): array
    {
        // Chargée par le pont avec la plante (D-98) ; la clé étrangère l'impose.
        $zone = $e->plante->zone ?? throw new \LogicException("Plante {$e->plante->id} sans zone chargée.");

        return [
            'cle' => ['regle_id' => $e->echeance->regleId, 'due_le' => $e->echeance->dueLe->format('Y-m-d')],
            'plante' => [
                'id' => $e->plante->id,
                'nom' => $e->plante->nom,
                'code_court' => $e->plante->code_court,
                'zone' => ['id' => $zone->id, 'nom' => $zone->nom],
            ],
            'type' => ['id' => $e->type->id, 'libelle' => $e->type->libelle],
        ];
    }
}
