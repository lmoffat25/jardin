<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Jardin\Domaine\FondDePlan;

/**
 * Un fond de plan image fictif du jardin courant, calé par deux points, sans
 * fichier sur le disque ; inactif.
 *
 * @extends Factory<FondDePlan>
 */
class FondDePlanFactory extends Factory
{
    protected $model = FondDePlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nature' => 'image',
            'fichier' => 'photos/temoin/plans/'.hash('sha256', $this->faker->uuid()).'.png',
            'calage' => [
                ['x' => 0, 'y' => 0, 'lng' => 4.835, 'lat' => 45.765],
                ['x' => 400, 'y' => 300, 'lng' => 4.837, 'lat' => 45.7635],
            ],
            'actif' => false,
        ];
    }
}
