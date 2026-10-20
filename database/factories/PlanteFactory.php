<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;

/**
 * Une plante fictive du jardin courant, dans une zone neuve. Le code court
 * vient d'un compteur de test jusqu'au 7a, qui l'attribue (RG-13).
 *
 * @extends Factory<Plante>
 */
class PlanteFactory extends Factory
{
    protected $model = Plante::class;

    private static int $compteur = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'code_court' => sprintf('TST-%03d', ++self::$compteur),
            'nom' => 'Plante témoin',
        ];
    }
}
