<?php

// V38 : toute réponse Inertia d'une page authentifiée porte l'utilisateur, le
// jardin courant (identifiant, nom, profil climatique) et les jardins de ses
// adhésions ; un jardin_id de session sans adhésion est ignoré.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    Route::middleware(['web', 'metier'])->get('/page-temoin', fn () => Inertia::render('Temoin/Page'));

    $this->utilisateur = Utilisateur::factory()->create(['courriel' => 'temoin@exemple.test']);
    $this->premier = Jardin::factory()->create(['nom' => 'Verger témoin', 'profil_climatique_id' => 4, 'cree_le' => '2026-01-01 00:00:00']);
    $this->second = Jardin::factory()->create(['nom' => 'Potager témoin', 'cree_le' => '2026-02-01 00:00:00']);
    foreach ([$this->second, $this->premier] as $jardin) {
        Adhesion::create(['utilisateur_id' => $this->utilisateur->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
    }
});

it('porte l\'utilisateur, le jardin courant et les jardins', function () {
    $this->actingAs($this->utilisateur)->withoutVite()->get('/page-temoin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Temoin/Page')
            ->where('utilisateur', ['id' => $this->utilisateur->id, 'courriel' => 'temoin@exemple.test'])
            ->where('jardinCourant', [
                'id' => $this->premier->id,
                'nom' => 'Verger témoin',
                'profil' => ['id' => 4, 'libelle' => 'Frais', 'decalage_jours' => 7],
            ])
            ->where('jardins', [
                ['id' => $this->premier->id, 'nom' => 'Verger témoin'],
                ['id' => $this->second->id, 'nom' => 'Potager témoin'],
            ]));
});

it('prend le jardin de la session quand l\'utilisateur y adhère', function () {
    $this->actingAs($this->utilisateur)->withoutVite()
        ->withSession(['jardin_id' => $this->second->id])
        ->get('/page-temoin')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('jardinCourant.id', $this->second->id));

    expect(app(JardinCourant::class)->id())->toBe($this->second->id);
});

it('ignore un jardin de session sans adhésion', function (string $jardinSession) {
    $etranger = Jardin::factory()->create(['nom' => 'Jardin étranger']);
    $valeur = $jardinSession === 'etranger' ? $etranger->id : $jardinSession;

    $this->actingAs($this->utilisateur)->withoutVite()
        ->withSession(['jardin_id' => $valeur])
        ->get('/page-temoin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('jardinCourant.id', $this->premier->id)
            ->where('jardins', fn ($jardins) => collect($jardins)->pluck('id')->doesntContain($etranger->id)));
})->with(['etranger', 'pas-un-uuid']);

it('envoie vers la création de jardin un compte sans adhésion', function () {
    $seul = Utilisateur::factory()->create();

    $this->actingAs($seul)->get('/page-temoin')
        ->assertStatus(302)
        ->assertHeader('Location', url('/jardins/nouveau'));
});
