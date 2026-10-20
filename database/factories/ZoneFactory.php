<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Jardin\Domaine\Zone;

/**
 * Une zone fictive du jardin courant (le trait de cloisonnement le renseigne).
 *
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['nom' => 'Zone '.fake()->unique()->numerify('####')];
    }
}
