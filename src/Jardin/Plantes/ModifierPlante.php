<?php

namespace Src\Jardin\Plantes;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;

/**
 * Modifie une plante du jardin courant (CDC §6 lot 0, V132, D-54) : son nom,
 * sa zone et ses champs descriptifs. Le code court, l'espèce, les règles et la
 * géométrie ne changent pas (RG-13). La saisie est validée par l'appelant
 * (zone par `ZoneDuJardin`, nom unique dans la zone d'arrivée) ; la zone est
 * relue ici par le filtre du jardin courant. Un champ facultatif absent de la
 * saisie est laissé tel quel, un champ envoyé vide est effacé (D-102).
 *
 * @phpstan-type Modification array{nom: string, zone_id: string, repere?: string|null, variete?: string|null, plantee_le?: string|null, age_estime_ans?: int|null, notes?: string|null}
 */
final class ModifierPlante
{
    private const FACULTATIFS = ['repere', 'variete', 'plantee_le', 'age_estime_ans', 'notes'];

    /** @param  Modification  $saisie */
    public function executer(Plante $plante, array $saisie): Plante
    {
        return DB::transaction(function () use ($plante, $saisie): Plante {
            $zone = Zone::query()->whereNull('archivee_le')->findOrFail($saisie['zone_id']);

            /** @var array<string, mixed> $facultatifs */
            $facultatifs = Arr::only($saisie, self::FACULTATIFS);
            $plante->update(['nom' => $saisie['nom'], 'zone_id' => $zone->id, ...$facultatifs]);

            return $plante;
        });
    }
}
