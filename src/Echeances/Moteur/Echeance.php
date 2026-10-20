<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/** Une échéance calculée, désignée par `(regleId, dueLe)` (H11, D-07). */
final readonly class Echeance
{
    public function __construct(
        public string $regleId,
        public \DateTimeImmutable $dueLe,
        public Periode $periode,
        public Etat $etat,
        public Explication $explication,
    ) {}
}
