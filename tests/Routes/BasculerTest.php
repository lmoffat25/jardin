<?php

// V40 : basculer vers un jardin où l'utilisateur a une adhésion change le
// jardin courant ; vers un autre, la réponse est 403, et le jardin courant ne
// change pas (relu par les props partagées).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    Route::middleware(['web', 'metier'])->get('/page-temoin', fn () => Inertia::render('Temoin/Page'));

    $this->utilisateur = Utilisateur::factory()->create();
    $this->premier = Jardin::factory()->create(['nom' => 'Premier', 'cree_le' => '2026-01-01 00:00:00']);
    $this->second = Jardin::factory()->create(['nom' => 'Second', 'cree_le' => '2026-02-01 00:00:00']);
    Adhesion::create(['utilisateur_id' => $this->utilisateur->id, 'jardin_id' => $this->premier->id, 'role' => 'proprietaire']);
    Adhesion::create(['utilisateur_id' => $this->utilisateur->id, 'jardin_id' => $this->second->id, 'role' => 'membre']);
});

/** Le jardin courant que les props partagées rendent. */
function jardinCourantLu(): string
{
    $id = null;
    test()->withoutVite()->get('/page-temoin')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$id) {
            $id = $page->toArray()['props']['jardinCourant']['id'];
        });

    return $id;
}

it('bascule vers un jardin dont l\'utilisateur est membre', function () {
    $this->actingAs($this->utilisateur);
    expect(jardinCourantLu())->toBe($this->premier->id);

    $this->post("/jardins/{$this->second->id}/basculer")
        ->assertStatus(302)
        ->assertHeader('Location', url('/'));

    expect(session('jardin_id'))->toBe($this->second->id)
        ->and(jardinCourantLu())->toBe($this->second->id);
});

it('refuse de basculer vers un jardin sans adhésion', function (string $cible) {
    $etranger = Jardin::factory()->create(['nom' => 'Étranger']);
    $id = match ($cible) {
        'etranger' => $etranger->id,
        'inexistant' => '0192f0c4-0000-7000-8000-000000000000',
        'pas-un-uuid' => 'abc',
    };

    $this->actingAs($this->utilisateur);
    expect(jardinCourantLu())->toBe($this->premier->id);

    $this->post("/jardins/{$id}/basculer")->assertStatus(403);

    expect(session('jardin_id'))->toBe($this->premier->id)
        ->and(jardinCourantLu())->toBe($this->premier->id);
})->with(['etranger', 'inexistant', 'pas-un-uuid']);

it('refuse la bascule à une session fermée', function () {
    $this->post("/jardins/{$this->second->id}/basculer")
        ->assertHeader('Location', url('/connexion'));
});
