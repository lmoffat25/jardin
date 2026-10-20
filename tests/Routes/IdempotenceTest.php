<?php

// V94 (RG-50, H5, S11, critère 2) : la même demande envoyée deux fois (même
// identifiant) laisse une seule intervention, et les deux réponses sont des
// succès identiques. Renvoyée avec le même identifiant et un contenu
// différent, elle ne modifie pas la ligne enregistrée (D-15).
//
// V96 (RG-04, écran B8) : une saisie sur 6 plantes, un identifiant client par
// plante, crée 6 interventions qui partagent un même `lot_saisie_id` ;
// renvoyée, elle en laisse toujours 6.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-02');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;

    // Trois plantes de plus dans le potager de A : six en tout.
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $potager = DB::table('zones')->where('nom', 'A Potager')->value('id');
    foreach (['Groseillier', 'Cassissier', 'Framboisier'] as $nom) {
        Plante::factory()->create(['zone_id' => $potager, 'nom' => $nom]);
    }
    app()->forgetScopedInstances();

    $this->plantes = DB::table('plantes')->where('jardin_id', $this->jardins->a->id)->orderBy('nom')->pluck('id')->all();
    $this->taille = RegleFactory::typeFourni('Taille d\'entretien/fructification');
    $this->tonte = RegleFactory::typeFourni('Tonte');
});

/** @param  array<string, mixed>  $charge */
function renvoyerDansA(Utilisateur $u, array $charge): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->postJson('/interventions', $charge);
}

it('ne crée qu\'une intervention pour une demande envoyée deux fois, et rend deux fois la même réponse', function () {
    $charge = [
        'type_intervention_id' => $this->taille,
        'faite_le' => '2026-08-30',
        'note' => 'Taille en vert.',
        'duree_min' => 15,
        'lignes' => [['id' => (string) Str::uuid(), 'plante_id' => $this->plantes[0]]],
    ];

    $premiere = renvoyerDansA($this->jardins->membreA, $charge)->assertOk();
    $seconde = renvoyerDansA($this->jardins->membreA, $charge)->assertOk();

    expect($seconde->getContent())->toBe($premiere->getContent())
        ->and(DB::table('interventions')->count())->toBe(1);
});

it('ne modifie pas la ligne enregistrée quand le renvoi diffère, et rend l\'état stocké', function () {
    $id = (string) Str::uuid();
    $premiere = renvoyerDansA($this->jardins->membreA, [
        'type_intervention_id' => $this->taille,
        'faite_le' => '2026-08-30',
        'note' => 'Taille en vert.',
        'duree_min' => 15,
        'lignes' => [['id' => $id, 'plante_id' => $this->plantes[0]]],
    ])->assertOk();
    $avant = (array) DB::table('interventions')->where('id', $id)->sole();

    $renvoi = renvoyerDansA($this->jardins->proprietaireA, [
        'type_intervention_id' => $this->tonte,
        'faite_le' => '2026-08-31',
        'note' => 'Autre chose.',
        'duree_min' => 90,
        'position' => ['lng' => 2.35, 'lat' => 48.85, 'precision_m' => 8],
        'lignes' => [['id' => $id, 'plante_id' => $this->plantes[1]]],
    ])->assertOk();

    expect($renvoi->getContent())->toBe($premiere->getContent())
        ->and((array) DB::table('interventions')->where('id', $id)->sole())->toBe($avant)
        ->and(DB::table('interventions')->count())->toBe(1);
});

it('décompose une saisie sur six plantes en six interventions d\'un même lot', function () {
    $lot = (string) Str::uuid();
    $lignes = array_map(fn (string $plante) => ['id' => (string) Str::uuid(), 'plante_id' => $plante], $this->plantes);
    expect($lignes)->toHaveCount(6);
    $charge = ['lot_saisie_id' => $lot, 'type_intervention_id' => $this->taille, 'faite_le' => '2026-08-30', 'lignes' => $lignes];

    $premiere = renvoyerDansA($this->jardins->membreA, $charge)->assertOk();

    expect($premiere->json('interventions.*.id'))->toBe(array_column($lignes, 'id'))
        ->and($premiere->json('interventions.*.plante_id'))->toBe($this->plantes)
        ->and(DB::table('interventions')->where('lot_saisie_id', $lot)->count())->toBe(6)
        ->and(DB::table('interventions')->distinct()->pluck('plante_id')->sort()->values()->all())->toBe(collect($this->plantes)->sort()->values()->all());

    $seconde = renvoyerDansA($this->jardins->membreA, $charge)->assertOk();

    expect($seconde->getContent())->toBe($premiere->getContent())
        ->and(DB::table('interventions')->count())->toBe(6)
        ->and(DB::table('interventions')->where('lot_saisie_id', $lot)->count())->toBe(6);
});

it('complète un lot renvoyé en partie, sans doubler ce qui est déjà là', function () {
    $lot = (string) Str::uuid();
    $lignes = array_map(fn (string $plante) => ['id' => (string) Str::uuid(), 'plante_id' => $plante], $this->plantes);
    $charge = ['lot_saisie_id' => $lot, 'type_intervention_id' => $this->taille, 'faite_le' => '2026-08-30'];

    renvoyerDansA($this->jardins->membreA, [...$charge, 'lignes' => array_slice($lignes, 0, 4)])->assertOk();
    renvoyerDansA($this->jardins->membreA, [...$charge, 'lignes' => $lignes])
        ->assertOk()
        ->assertJsonCount(6, 'interventions');

    expect(DB::table('interventions')->where('lot_saisie_id', $lot)->count())->toBe(6);
});
