<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Jardin;

/**
 * Un jardin fictif, profil 3 (Tempéré) du jeu de référence : le test charge
 * `ReferenceSeeder`. Le propriétaire est inséré en DB::table jusqu'au 2b, qui
 * crée le modèle `Utilisateur` et sa factory.
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
            'proprietaire_id' => function (): string {
                $id = (string) Str::uuid();
                DB::table('utilisateurs')->insert(['id' => $id, 'courriel' => "{$id}@exemple.test", 'mot_de_passe_hache' => 'x']);

                return $id;
            },
            'nom' => 'Jardin '.fake()->unique()->numerify('###'),
            'profil_climatique_id' => 3,
        ];
    }
}
