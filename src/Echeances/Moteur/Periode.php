<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/** Une fenêtre projetée en dates absolues, décalée et rognée. */
final readonly class Periode
{
    private function __construct(
        public \DateTimeImmutable $debut,
        public \DateTimeImmutable $fin,
        public ?\DateTimeImmutable $optimalDebut,
        public ?\DateTimeImmutable $optimalFin,
    ) {}

    /**
     * L'optimale est contenue dans la fenêtre (D-09) ; rognée jusqu'à n'avoir
     * plus aucun jour, elle disparaît (D-87).
     */
    public static function rognee(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        ?\DateTimeImmutable $optimalDebut = null,
        ?\DateTimeImmutable $optimalFin = null,
    ): self {
        if ($optimalDebut !== null && $optimalFin !== null) {
            $optimalDebut = max($optimalDebut, $debut);
            $optimalFin = min($optimalFin, $fin);
            if ($optimalDebut <= $optimalFin) {
                return new self($debut, $fin, $optimalDebut, $optimalFin);
            }
        }

        return new self($debut, $fin, null, null);
    }

    public function contient(\DateTimeImmutable $date): bool
    {
        $jour = Calendrier::jour($date);

        return $this->debut <= $jour && $jour <= $this->fin;
    }

    public function dansOptimale(\DateTimeImmutable $date): bool
    {
        $jour = Calendrier::jour($date);

        // L'optimale a ses deux bornes ou aucune (rognee).
        return $this->optimalDebut !== null && $this->optimalDebut <= $jour && $jour <= $this->optimalFin;
    }

    public function estVide(): bool
    {
        return $this->debut > $this->fin;
    }
}
