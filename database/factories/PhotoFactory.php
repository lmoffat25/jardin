<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Photo;

/**
 * Une photo fictive d'une plante neuve, de 100 Ko, sans fichier sur le disque.
 *
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $empreinte = hash('sha256', $this->faker->uuid());

        return [
            'plante_id' => Plante::factory(),
            'empreinte' => $empreinte,
            'chemin_original' => "photos/temoin/2026/{$empreinte}.jpg",
            'taille_ko' => 100,
        ];
    }
}
