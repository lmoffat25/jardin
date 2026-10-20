<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Regle;

/**
 * Une occurrence manquée du 01/02/2026 d'une règle neuve, sur la plante de la
 * règle.
 *
 * @extends Factory<EcheanceMaterialisee>
 */
class EcheanceMaterialiseeFactory extends Factory
{
    protected $model = EcheanceMaterialisee::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'regle_id' => Regle::factory(),
            'plante_id' => fn (array $attributs) => Regle::query()->whereKey($attributs['regle_id'])->value('plante_id'),
            'due_le' => '2026-02-01',
            'fenetre_debut' => '2026-02-01',
            'fenetre_fin' => '2026-03-31',
            'etat' => 'manquee',
            'explication' => 'Échéance témoin.',
        ];
    }
}
