<?php

namespace Src\Journal\Saisie;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Src\Journal\Domaine\Intervention;

/**
 * Une saisie d'intervention validée (V93) : un type, une date, et une ligne
 * par plante, chacune avec l'identifiant que le client a choisi (S11, H5,
 * D-06). Plusieurs lignes partagent le `lot_saisie_id` du client (RG-04).
 *
 * @phpstan-type Ligne array{id: string, plante_id: string}
 * @phpstan-type Position array{lng: float|int, lat: float|int, precision_m?: float|int|null}
 */
final class SaisieIntervention
{
    /**
     * @param  list<Ligne>  $lignes
     * @param  Position|null  $position
     */
    public function __construct(
        public readonly ?string $lotSaisieId,
        public readonly int $typeId,
        public readonly string $faiteLe,
        public readonly ?string $note,
        public readonly ?int $dureeMin,
        public readonly ?array $position,
        public readonly array $lignes,
    ) {}

    /**
     * Depuis la saisie validée par le contrôleur. Les identifiants passent en
     * minuscules, comme la base les rend : un uuid en majuscules serait sinon
     * pris pour absent à la relecture.
     *
     * @param  array{lot_saisie_id?: string|null, type_intervention_id: int|string, faite_le: string, note?: string|null, duree_min?: int|string|null, position?: Position|null, lignes: list<Ligne>}  $valide
     */
    public static function depuis(array $valide): self
    {
        $minuscule = fn (?string $uuid): ?string => $uuid === null ? null : strtolower($uuid);

        return new self(
            lotSaisieId: $minuscule($valide['lot_saisie_id'] ?? null),
            typeId: (int) $valide['type_intervention_id'],
            faiteLe: $valide['faite_le'],
            note: $valide['note'] ?? null,
            dureeMin: isset($valide['duree_min']) ? (int) $valide['duree_min'] : null,
            position: $valide['position'] ?? null,
            lignes: array_map(fn (array $l) => ['id' => strtolower($l['id']), 'plante_id' => strtolower($l['plante_id'])], $valide['lignes']),
        );
    }

    /** @return list<string> les identifiants des interventions, dans l'ordre des lignes */
    public function ids(): array
    {
        return array_column($this->lignes, 'id');
    }

    /** @return list<string> */
    public function plantesIds(): array
    {
        return array_column($this->lignes, 'plante_id');
    }

    /**
     * Les lignes de la table. `jardin_id` est posé ici : `insertOrIgnore` ne
     * passe pas par `creating`, qui le pose d'habitude (AppartientAuJardin).
     * `saisie_le` porte son fuseau : sans lui, la base lirait l'heure de
     * Paris comme une heure UTC.
     *
     * @return list<array<string, mixed>>
     */
    public function lignesAInserer(string $jardinId, string $auteurId, \DateTimeImmutable $saisieLe): array
    {
        $position = $this->position === null ? null : json_encode([
            'lng' => $this->position['lng'],
            'lat' => $this->position['lat'],
            'precision_m' => $this->position['precision_m'] ?? null,
        ], JSON_THROW_ON_ERROR);

        return array_map(fn (array $ligne) => [
            'id' => $ligne['id'],
            'jardin_id' => $jardinId,
            'plante_id' => $ligne['plante_id'],
            'type_intervention_id' => $this->typeId,
            'auteur_id' => $auteurId,
            'faite_le' => $this->faiteLe,
            'note' => $this->note,
            'duree_min' => $this->dureeMin,
            'lot_saisie_id' => $this->lotSaisieId,
            'position_capturee' => $position,
            'saisie_le' => $saisieLe->format('Y-m-d H:i:sP'),
        ], $this->lignes);
    }

    /**
     * Les rangs des lignes dont la plante manque à $presentes.
     *
     * @param  Collection<array-key, mixed>  $presentes  les identifiants lus
     * @return list<int>
     */
    public function rangsSansPlante(Collection $presentes): array
    {
        return array_keys(array_diff($this->plantesIds(), $presentes->map(fn (mixed $id): string => is_string($id) ? $id : '')->all()));
    }

    /**
     * Les rangs des lignes dont l'intervention manque à $stockees.
     *
     * @param  EloquentCollection<int, Intervention>  $stockees
     * @return list<int>
     */
    public function lignesAbsentesDe(EloquentCollection $stockees): array
    {
        return array_keys(array_diff($this->ids(), $stockees->modelKeys()));
    }
}
