<?php

// V95 (H5, RG-21, critère 12) : une demande qui réutilise l'identifiant d'une
// intervention du jardin B est refusée avec la même réponse qu'une plante
// introuvable (D-08) ; rien n'est créé dans A, rien n'est modifié dans B.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-12');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;

    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
    $this->voisine = DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id');
    $this->taille = RegleFactory::typeFourni('Taille d\'entretien/fructification');

    // Une intervention de B, dont l'identifiant sera réutilisé depuis A.
    app(JardinCourant::class)->positionner($this->jardins->b->id);
    $this->interventionB = Intervention::factory()->create([
        'plante_id' => DB::table('plantes')->where('nom', DeuxJardins::MARQUEUR.' Plante Potager 1')->value('id'),
        'auteur_id' => $this->jardins->proprietaireB->id,
        'note' => DeuxJardins::MARQUEUR.' note de B',
    ])->id;
    app()->forgetScopedInstances();
});

/**
 * Une saisie dans A, en lot, des lignes données.
 *
 * @param  list<array{id: string, plante_id: string}>  $lignes
 */
function saisirDansA(array $lignes): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->proprietaireA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->postJson('/interventions', [
            'lot_saisie_id' => '0d9c1b7e-1111-4000-8000-000000000000',
            'type_intervention_id' => test()->taille,
            'faite_le' => '2026-08-30',
            'lignes' => $lignes,
        ]);
}

/** Le nombre d'interventions de A, lues hors de tout filtre. */
function interventionsDeA(): int
{
    return DB::table('interventions')->where('jardin_id', test()->jardins->a->id)->count();
}

it('répond à un identifiant de B comme à une plante introuvable', function (int $rang) {
    $avant = DeuxJardins::empreinte($this->jardins->b->id);
    $lignes = [
        ['id' => (string) Str::uuid(), 'plante_id' => $this->voisine],
        ['id' => (string) Str::uuid(), 'plante_id' => DB::table('plantes')->where('nom', 'A Plante Verger 2')->value('id')],
    ];

    $introuvable = $lignes;
    $introuvable[$rang] = ['id' => (string) Str::uuid(), 'plante_id' => (string) Str::uuid()];
    $reponseIntrouvable = saisirDansA($introuvable);

    $reutilise = $lignes;
    $reutilise[$rang] = ['id' => $this->interventionB, 'plante_id' => $this->plante];
    $reponseReutilise = saisirDansA($reutilise);

    $reponseReutilise->assertUnprocessable()
        ->assertJsonValidationErrors(["lignes.{$rang}" => 'Plante introuvable dans ce jardin.']);
    expect($reponseReutilise->getStatusCode())->toBe($reponseIntrouvable->getStatusCode())
        ->and($reponseReutilise->getContent())->toBe($reponseIntrouvable->getContent())
        ->and($reponseReutilise->getContent())->not->toContain($this->interventionB)
        ->and($reponseReutilise->getContent())->not->toContain(DeuxJardins::MARQUEUR)
        ->and(interventionsDeA())->toBe(0)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
})->with(['seule ligne fautive en tête' => 0, 'en second, après une ligne valide' => 1]);

it('répond de même à un identifiant de B envoyé seul', function () {
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    app()->forgetScopedInstances();
    $reponse = $this->actingAs($this->jardins->membreA)
        ->withSession(['jardin_id' => $this->jardins->a->id])
        ->postJson('/interventions', [
            'type_intervention_id' => $this->taille,
            'faite_le' => '2026-08-30',
            'lignes' => [['id' => $this->interventionB, 'plante_id' => $this->plante]],
        ]);

    $reponse->assertUnprocessable()->assertExactJson([
        'message' => 'Plante introuvable dans ce jardin.',
        'errors' => ['lignes.0' => ['Plante introuvable dans ce jardin.']],
    ]);
    expect(interventionsDeA())->toBe(0)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});
