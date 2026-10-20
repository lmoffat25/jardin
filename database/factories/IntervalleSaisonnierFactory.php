<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;

/**
 * Une saison de mars à octobre, tous les 7 jours, d'une règle R1 neuve.
 *
 * @extends Factory<IntervalleSaisonnier>
 */
class IntervalleSaisonnierFactory extends Factory
{
    protected $model = IntervalleSaisonnier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'regle_id' => Regle::factory()->r1(),
            'mois_debut' => 3,
            'mois_fin' => 10,
            'intervalle_jours' => 7,
        ];
    }
}
