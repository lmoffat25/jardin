<?php

namespace Src\Echeances\Moteur\Temoin;

/**
 * Classe témoin de la mutation (D-37) : elle est dans le filtre `moteur` de la
 * CI, sans rien du framework. Retirée au sprint 3a, avec son test.
 */
final class Parite
{
    public function estPaire(int $nombre): bool
    {
        return $nombre % 2 === 0;
    }
}
