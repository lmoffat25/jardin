<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;

/**
 * Une taille fictive du 01/03/2026 sur une plante neuve, par un compte neuf.
 *
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plante_id' => Plante::factory(),
            'type_intervention_id' => fn () => RegleFactory::typeFourni('Taille d\'entretien/fructification'),
            'auteur_id' => Utilisateur::factory(),
            'faite_le' => '2026-03-01',
        ];
    }
}
