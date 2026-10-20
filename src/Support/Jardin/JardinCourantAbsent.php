<?php

namespace Src\Support\Jardin;

use RuntimeException;

/** Du code a demandé le jardin courant avant que personne ne l'ait positionné (A11). */
final class JardinCourantAbsent extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Aucun jardin courant : il doit être positionné par la requête, le job ou la commande.');
    }
}
