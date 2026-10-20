<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Les interventions passées et les occurrences déjà closes (D-05), en mémoire :
 * le moteur les interroge, il ne lit jamais la base (A10).
 */
final readonly class Historique
{
    /**
     * @param  array<string, \DateTimeImmutable>  $dernieres  « plante|type » => dernière intervention
     * @param  array<string, list<string>>  $closes  règle => dates `AAAA-MM-JJ` des occurrences closes
     * @param  array<string, list<int>>  $decalages  règle => décalages appliqués à ses closes, en jours
     */
    private function __construct(
        private array $dernieres,
        private array $closes,
        private array $decalages,
    ) {}

    public static function vide(): self
    {
        return new self([], [], []);
    }

    /**
     * @param  list<array{plante_id: string, type_id: int, fait_le: \DateTimeImmutable}>  $interventions
     * @param  list<array{regle_id: string, due_le: \DateTimeImmutable, decalage_j?: int}>  $closes  échéances matérialisées `soldee`, `suspendue` ou `manquee`, avec le décalage appliqué à leur écriture
     */
    public static function depuis(array $interventions, array $closes = []): self
    {
        $dernieres = [];
        foreach ($interventions as $intervention) {
            $cle = $intervention['plante_id'].'|'.$intervention['type_id'];
            $faitLe = Calendrier::jour($intervention['fait_le']);
            $dernieres[$cle] = max($dernieres[$cle] ?? $faitLe, $faitLe);
        }

        $parOccurrence = [];
        $decalages = [];
        foreach ($closes as $close) {
            $parOccurrence[$close['regle_id']][] = $close['due_le']->format('Y-m-d');
            if (isset($close['decalage_j'])) {
                $decalages[$close['regle_id']][] = $close['decalage_j'];
            }
        }

        return new self($dernieres, $parOccurrence, $decalages);
    }

    public function derniere(string $planteId, int $typeId): ?\DateTimeImmutable
    {
        return $this->dernieres[$planteId.'|'.$typeId] ?? null;
    }

    public function estClose(string $regleId, \DateTimeImmutable $dueLe): bool
    {
        return in_array($dueLe->format('Y-m-d'), $this->closes[$regleId] ?? [], true);
    }

    /**
     * L'occurrence d'une fenêtre commencée en `$annee` est close, sous le
     * décalage du jour ou sous celui d'une close écrite avant un changement de
     * profil : sa date due a bougé avec le profil, l'occurrence non (V75, D-99).
     */
    public function occurrenceClose(string $regleId, Fenetre $fenetre, int $annee, Decalage $decalage): bool
    {
        foreach ([$decalage->valeur(), ...$this->decalages[$regleId] ?? []] as $jours) {
            if ($this->estClose($regleId, $fenetre->projeter($annee, Decalage::jours($jours))->debut)) {
                return true;
            }
        }

        return false;
    }
}
