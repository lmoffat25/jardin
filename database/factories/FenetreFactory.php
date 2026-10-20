<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;

/**
 * Une fenêtre du 01/02 au 31/03, sans optimale ni butoir, d'une règle R2 neuve.
 *
 * @extends Factory<Fenetre>
 */
class FenetreFactory extends Factory
{
    protected $model = Fenetre::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'regle_id' => Regle::factory(),
            'debut_jour' => 1,
            'debut_mois' => 2,
            'fin_jour' => 31,
            'fin_mois' => 3,
            'ordre' => 1,
        ];
    }
}
