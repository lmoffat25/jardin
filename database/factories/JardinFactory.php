<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;

/**
 * Un jardin fictif, profil 3 (Tempéré) du jeu de référence : le test charge
 * `ReferenceSeeder`. Son propriétaire est un compte neuf ; l'adhésion
 * `proprietaire` n'est pas créée ici (c'est `CreerJardin`, au 2d).
 *
 * @extends Factory<Jardin>
 */
class JardinFactory extends Factory
{
    protected $model = Jardin::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'proprietaire_id' => Utilisateur::factory(),
            'nom' => 'Jardin '.fake()->unique()->numerify('###'),
            'profil_climatique_id' => 3,
        ];
    }
}
