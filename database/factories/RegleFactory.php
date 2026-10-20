<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;

/**
 * Une règle R2 active du jardin courant, sur une plante neuve, de type fourni
 * « Taille d'entretien/fructification » : le test charge `ReferenceSeeder`.
 * Ses fenêtres ou ses saisons se créent à part (`FenetreFactory`,
 * `IntervalleSaisonnierFactory`).
 *
 * @extends Factory<Regle>
 */
class RegleFactory extends Factory
{
    protected $model = Regle::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plante_id' => Plante::factory(),
            'type_intervention_id' => fn () => self::typeFourni('Taille d\'entretien/fructification'),
            'type_regle' => 'R2',
            'periodicite_annees' => 1,
            'confiance' => 'verifiee',
            'origine' => 'bibliotheque',
        ];
    }

    /** Une règle R1 : l'intervalle de la règle, et ses saisons à part. */
    public function r1(int $jours = 7): static
    {
        return $this->state([
            'type_intervention_id' => fn () => self::typeFourni('Tonte'),
            'type_regle' => 'R1',
            'intervalle_jours' => $jours,
            'periodicite_annees' => null,
        ]);
    }

    /** L'id d'un type fourni du jeu de référence, par son libellé. */
    public static function typeFourni(string $libelle): int
    {
        return TypeIntervention::query()->whereNull('jardin_id')->where('libelle', $libelle)->firstOrFail()->id;
    }
}
