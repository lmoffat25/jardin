<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Comptes\Domaine\Utilisateur;

/**
 * Un compte fictif ; le mot de passe est haché par le cast du modèle.
 *
 * @extends Factory<Utilisateur>
 */
class UtilisateurFactory extends Factory
{
    protected $model = Utilisateur::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'courriel' => fake()->unique()->numerify('compte-#####').'@exemple.test',
            'mot_de_passe_hache' => 'mot-de-passe-temoin',
        ];
    }
}
