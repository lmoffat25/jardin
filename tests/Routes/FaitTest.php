<?php

// V98 (P2, H11, D-57, écran T1) : « fait » sur une échéance désignée par
// `(regle_id, due_le)` et un identifiant client crée l'intervention du type de
// la règle sur sa plante, datée d'aujourd'hui, et solde ce couple : une
// échéance à venir est matérialisée `soldee`, une manquée passe de `manquee` à
// `soldee`. Règle inconnue ou inactive, couple que le moteur ne rend pas :
// erreur, rien n'est créé. Règle de B : 404. Renvoyée, la demande n'en crée
// pas d'autre.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

/** Fige l'horloge à $jour, 10 h à Paris. */
function figerAu(string $jour): void
{
    test()->travelTo(new DateTimeImmutable("{$jour} 10:00:00", new DateTimeZone('Europe/Paris')));
}

/** Une règle de taille, fenêtre du 01/02 au 31/03, posée le $poseeLe sur la plante $plante du jardin $jardin. */
function regleDeTaille(string $jardin, string $plante, string $poseeLe): Regle
{
    figerAu($poseeLe);
    app(JardinCourant::class)->positionner($jardin);
    $regle = Regle::factory()->create(['plante_id' => $plante]);
    Fenetre::factory()->for($regle)->create();
    app()->forgetScopedInstances();

    return $regle;
}

/** @param  array<string, mixed>  $charge */
function faitDansA(Utilisateur $u, array $charge): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->postJson('/echeances/fait', $charge);
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
    // Posée à l'automne 2026 : l'hiver 2027 est sa première fenêtre.
    $this->regle = regleDeTaille($this->jardins->a->id, $this->plante, '2026-10-01');
    figerAu('2027-03-10');
});

it('crée l\'intervention de la règle, datée d\'aujourd\'hui, et solde l\'échéance à venir', function () {
    $id = (string) Str::uuid();

    $reponse = faitDansA($this->jardins->membreA, ['id' => $id, 'regle_id' => $this->regle->id, 'due_le' => '2027-02-01']);

    $reponse->assertOk()->assertExactJson(['interventions' => [[
        'id' => $id,
        'plante_id' => $this->plante,
        'type_intervention_id' => RegleFactory::typeFourni('Taille d\'entretien/fructification'),
        'faite_le' => '2027-03-10',
        'note' => null,
        'duree_min' => null,
        'auteur_id' => $this->jardins->membreA->id,
    ]]]);

    $intervention = DB::table('interventions')->sole();
    $soldee = DB::table('echeances')->sole();
    expect($intervention->jardin_id)->toBe($this->jardins->a->id)
        ->and($soldee->jardin_id)->toBe($this->jardins->a->id)
        ->and($soldee->regle_id)->toBe($this->regle->id)
        ->and($soldee->plante_id)->toBe($this->plante)
        ->and($soldee->due_le)->toBe('2027-02-01')
        ->and($soldee->etat)->toBe('soldee')
        ->and($soldee->intervention_id)->toBe($id)
        ->and($soldee->fenetre_debut)->toBe('2027-02-01')
        ->and($soldee->fenetre_fin)->toBe('2027-03-31')
        ->and($soldee->explication)->toContain('fenêtre févr.–mars');
});

it('rend la même réponse à un renvoi, et n\'en crée pas d\'autre', function () {
    $charge = ['id' => (string) Str::uuid(), 'regle_id' => $this->regle->id, 'due_le' => '2027-02-01'];

    $premier = faitDansA($this->jardins->membreA, $charge)->assertOk();
    $renvoi = faitDansA($this->jardins->membreA, $charge)->assertOk();

    expect($renvoi->json())->toBe($premier->json())
        ->and(DB::table('interventions')->count())->toBe(1)
        ->and(DB::table('echeances')->count())->toBe(1);
});

it('solde la manquée désignée, pas l\'occurrence en cours', function () {
    // Posée avant l'hiver 2026 : la fenêtre de 2026 est manquée, et déjà écrite.
    $regle = regleDeTaille($this->jardins->a->id, DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id'), '2026-01-15');
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    EcheanceMaterialisee::factory()->for($regle)->create(['due_le' => '2026-02-01', 'fenetre_fin' => '2026-03-31', 'etat' => 'manquee']);
    figerAu('2027-03-10');
    $id = (string) Str::uuid();

    faitDansA($this->jardins->membreA, ['id' => $id, 'regle_id' => $regle->id, 'due_le' => '2026-02-01'])
        ->assertOk()
        ->assertJsonPath('interventions.0.faite_le', '2027-03-10');

    $echeances = DB::table('echeances')->where('regle_id', $regle->id)->get();
    expect($echeances)->toHaveCount(1)
        ->and($echeances[0]->due_le)->toBe('2026-02-01')
        ->and($echeances[0]->etat)->toBe('soldee')
        ->and($echeances[0]->intervention_id)->toBe($id);
});

it('refuse ce qu\'il ne peut pas solder, et ne crée rien', function (Closure $charge, int $code) {
    $reponse = faitDansA($this->jardins->proprietaireA, ['id' => (string) Str::uuid(), 'regle_id' => $this->regle->id, 'due_le' => '2027-02-01', ...$charge()]);

    expect($reponse->getStatusCode())->toBe($code)
        ->and(DB::table('interventions')->count())->toBe(0)
        ->and(DB::table('echeances')->count())->toBe(0);
})->with([
    'règle inconnue' => [fn () => ['regle_id' => (string) Str::uuid()], 404],
    'règle inactive' => [function () {
        DB::table('regles')->where('id', test()->regle->id)->update(['est_active' => false]);

        return [];
    }, 422],
    'couple que le moteur ne rend pas' => [fn () => ['due_le' => '2027-02-02'], 422],
    'occurrence de l\'an prochain' => [fn () => ['due_le' => '2028-02-01'], 422],
    'occurrence d\'avant la règle' => [fn () => ['due_le' => '2026-02-01'], 422],
    'identifiant qui n\'est pas un uuid' => [fn () => ['id' => '42'], 422],
    'date au format français' => [fn () => ['due_le' => '01/02/2027'], 422],
]);

it('rend 404 pour une règle de B, et laisse B intact', function () {
    $regleB = regleDeTaille($this->jardins->b->id, DB::table('plantes')->where('nom', DeuxJardins::MARQUEUR.' Plante Potager 1')->value('id'), '2026-10-01');
    figerAu('2027-03-10');
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    faitDansA($this->jardins->proprietaireA, ['id' => (string) Str::uuid(), 'regle_id' => $regleB->id, 'due_le' => '2027-02-01'])
        ->assertNotFound();

    expect(DB::table('interventions')->count())->toBe(0)
        ->and(DB::table('echeances')->count())->toBe(0)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});
