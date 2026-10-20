<?php

namespace Src\Echeances\TableauDeBord;

use Inertia\Inertia;
use Inertia\Response;
use Src\Comptes\Domaine\Jardin;
use Src\Support\Jardin\JardinCourant;

/**
 * Le tableau de bord du bureau (B4) et la liste « à faire » du terrain (T1) :
 * le même calcul, rangé par `RegrouperEcheances` (critère 3, V76).
 */
final class TableauDeBordController
{
    public function __construct(
        private readonly RegrouperEcheances $regrouper,
        private readonly JardinCourant $jardinCourant,
    ) {}

    public function bureau(): Response
    {
        return Inertia::render('Bureau/TableauDeBord', $this->regrouper->tableau($this->jardin()));
    }

    /** Les seules échéances du jour, par zone (D-100). */
    public function terrain(): Response
    {
        return Inertia::render('Terrain/AFaire', [
            'aujourdhui' => $this->regrouper->tableau($this->jardin())['aujourdhui'],
        ]);
    }

    private function jardin(): Jardin
    {
        return Jardin::query()->findOrFail($this->jardinCourant->id());
    }
}
