<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/** Le profil climatique du jardin, réduit à un nombre de jours (RG-25). */
final readonly class Decalage
{
    private function __construct(private int $jours) {}

    public static function jours(int $jours): self
    {
        return new self($jours);
    }

    public function appliquer(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify(sprintf('%+d days', $this->jours));
    }

    public function valeur(): int
    {
        return $this->jours;
    }
}
