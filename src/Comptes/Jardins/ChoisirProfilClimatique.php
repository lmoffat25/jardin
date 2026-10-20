<?php

namespace Src\Comptes\Jardins;

use Src\Comptes\Domaine\Jardin;
use Src\Support\Jardin\JardinCourant;

/**
 * Change le profil climatique du jardin courant (RG-23) : n'écrit que
 * `jardins.profil_climatique_id`. Les échéances suivent à leur prochain calcul
 * (V75).
 */
final class ChoisirProfilClimatique
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    public function executer(int $profilId): void
    {
        Jardin::query()->whereKey($this->jardinCourant->id())->update(['profil_climatique_id' => $profilId]);
    }
}
