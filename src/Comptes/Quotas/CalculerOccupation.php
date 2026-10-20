<?php

namespace Src\Comptes\Quotas;

use Src\Journal\Domaine\Photo;

/**
 * L'occupation du disque par le jardin courant, en Ko (CDC §8.4, §8.6) : la
 * somme de ses photos, lues sous le filtre du jardin. Les fonds de plan image
 * s'y ajoutent au 11b (D-60).
 */
final class CalculerOccupation
{
    public function pourJardin(): int
    {
        return (int) Photo::query()->sum('taille_ko');
    }
}
