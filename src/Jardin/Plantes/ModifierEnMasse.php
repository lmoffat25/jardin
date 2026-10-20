<?php

namespace Src\Jardin\Plantes;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;

/**
 * Modifie en une demande plusieurs plantes du jardin courant (CDC §8.7, V90,
 * écran B5) : leur zone, leur repère, ou les deux. Tout ou rien : un
 * identifiant introuvable par le filtre du jardin courant (une plante de B,
 * D1), ou un nom qui deviendrait double dans la zone d'arrivée (RG-13), et
 * rien ne change. La saisie est validée par l'appelant (zone par
 * `ZoneDuJardin`) ; la zone est relue ici. Un repère envoyé vide est effacé
 * (D-102).
 *
 * @phpstan-type Lot array{ids: list<string>, zone_id?: string, repere?: string|null}
 */
final class ModifierEnMasse
{
    /**
     * @param  Lot  $saisie
     * @return int le nombre de plantes modifiées
     *
     * @throws ValidationException
     */
    public function executer(array $saisie): int
    {
        return DB::transaction(function () use ($saisie): int {
            $plantes = Plante::query()->whereKey($saisie['ids'])->lockForUpdate()->get(['id', 'nom', 'zone_id']);
            if ($plantes->count() !== count($saisie['ids'])) {
                throw ValidationException::withMessages(['ids' => 'Plante introuvable dans ce jardin.']);
            }

            /** @var array<string, string|null> $champs */
            $champs = Arr::only($saisie, ['repere']);
            if (isset($saisie['zone_id'])) {
                $zone = Zone::query()->whereNull('archivee_le')->findOrFail($saisie['zone_id']);
                $this->verifierNoms($plantes, $zone->id);
                $champs['zone_id'] = $zone->id;
            }

            try {
                Plante::query()->whereKey($saisie['ids'])->update($champs);
            } catch (UniqueConstraintViolationException) {
                // Une plante arrivée dans la zone entre la vérification et l'écriture.
                throw ValidationException::withMessages(['ids' => 'Une plante de la zone d\'arrivée porte déjà l\'un de ces noms.']);
            }

            return $plantes->count();
        });
    }

    /**
     * Le nom d'une plante est unique dans sa zone, plantes archivées comprises
     * (contrainte `(zone_id, nom)`) : ni deux plantes du lot, ni une plante du
     * lot et une plante restée dans la zone, ne peuvent y porter le même nom.
     *
     * @param  EloquentCollection<int, Plante>  $plantes
     *
     * @throws ValidationException
     */
    private function verifierNoms(EloquentCollection $plantes, string $zoneId): void
    {
        $doubles = $plantes->countBy('nom')->filter(fn (int $n) => $n > 1)->keys()->sort()->values();
        if ($doubles->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => 'Deux plantes déplacées portent le même nom : '.$doubles->implode(', ').'.']);
        }

        $pris = Plante::query()
            ->where('zone_id', $zoneId)
            ->whereKeyNot($plantes->modelKeys())
            ->whereIn('nom', $plantes->pluck('nom'))
            ->orderBy('nom')
            ->pluck('nom');
        if ($pris->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => 'Une plante de la zone d\'arrivée porte déjà ce nom : '.$pris->implode(', ').'.']);
        }
    }
}
