<?php

// V100 (H9, chapitre 1 C1) : la seconde intervention d'une fenêtre déjà soldée
// est enregistrée ; aucune seconde échéance soldée n'est écrite ; l'historique
// de la plante montre les deux interventions.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->membre = Utilisateur::factory()->create();
    $this->jardin = Jardin::factory()->create();
    Adhesion::create(['utilisateur_id' => $this->membre->id, 'jardin_id' => $this->jardin->id, 'role' => 'membre']);
    app(JardinCourant::class)->positionner($this->jardin->id);
    $this->pommier = Plante::factory()->create(['nom' => 'Pommier du verger']);
    $this->regle = Regle::factory()->create(['plante_id' => $this->pommier->id]);
    Fenetre::factory()->for($this->regle)->create();
    $this->travelTo(new DateTimeImmutable('2027-03-20 09:00:00', new DateTimeZone('Europe/Paris')));
});

/** Une taille du $faiteLe sur le pommier ; rend son identifiant. */
function tailler(string $faiteLe): string
{
    $id = (string) Str::uuid();
    app()->forgetScopedInstances();
    test()->actingAs(test()->membre)
        ->withSession(['jardin_id' => test()->jardin->id])
        ->postJson('/interventions', [
            'type_intervention_id' => RegleFactory::typeFourni('Taille d\'entretien/fructification'),
            'faite_le' => $faiteLe,
            'lignes' => [['id' => $id, 'plante_id' => test()->pommier->id]],
        ])
        ->assertOk();

    return $id;
}

it('enregistre la seconde taille d\'une fenêtre soldée sans écrire une seconde soldée', function () {
    $premiere = tailler('2027-03-02');
    $seconde = tailler('2027-03-15');

    $soldees = DB::table('echeances')->where('etat', 'soldee')->get();
    expect($soldees)->toHaveCount(1)
        ->and($soldees[0]->due_le)->toBe('2027-02-01')
        ->and($soldees[0]->intervention_id)->toBe($premiere);

    $historique = $this->actingAs($this->membre)
        ->withSession(['jardin_id' => $this->jardin->id])
        ->getJson("/plantes/{$this->pommier->id}/historique")
        ->assertOk()
        ->json('interventions');
    expect(array_column($historique, 'id'))->toBe([$seconde, $premiere]);
});

it('ne solde rien de plus quand la seconde est saisie avant la première', function () {
    // Saisies dans le désordre : la plus tardive d'abord.
    $tardive = tailler('2027-03-15');
    tailler('2027-03-02');

    $soldees = DB::table('echeances')->where('etat', 'soldee')->get();
    expect($soldees)->toHaveCount(1)
        ->and($soldees[0]->intervention_id)->toBe($tardive)
        ->and(DB::table('interventions')->count())->toBe(2);
});
