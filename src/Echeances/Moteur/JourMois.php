<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Un jour et un mois sans année : une borne de fenêtre, d'optimale ou de
 * butoir, telle que la bibliothèque l'écrit. Le 29/02 est accepté (D-09).
 */
final readonly class JourMois
{
    public function __construct(
        public int $jour,
        public int $mois,
    ) {
        // 2000 est bissextile : seul le jour/mois qui n'existe aucune année est refusé.
        if (! checkdate($mois, $jour, 2000)) {
            throw new \InvalidArgumentException(sprintf('Jour/mois impossible : %02d/%02d', $jour, $mois));
        }
    }

    public function avant(self $autre): bool
    {
        return [$this->mois, $this->jour] < [$autre->mois, $autre->jour];
    }
}
