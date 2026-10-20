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
     */
    private function __construct(
        private array $dernieres,
        private array $closes,
    ) {}

    public static function vide(): self
    {
        return new self([], []);
    }

    /**
     * @param  list<array{plante_id: string, type_id: int, fait_le: \DateTimeImmutable}>  $interventions
     * @param  list<array{regle_id: string, due_le: \DateTimeImmutable}>  $closes  échéances matérialisées `soldee`, `suspendue` ou `manquee`
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
        foreach ($closes as $close) {
            $parOccurrence[$close['regle_id']][] = $close['due_le']->format('Y-m-d');
        }

        return new self($dernieres, $parOccurrence);
    }

    public function derniere(string $planteId, int $typeId): ?\DateTimeImmutable
    {
        return $this->dernieres[$planteId.'|'.$typeId] ?? null;
    }

    public function estClose(string $regleId, \DateTimeImmutable $dueLe): bool
    {
        return in_array($dueLe->format('Y-m-d'), $this->closes[$regleId] ?? [], true);
    }
}
