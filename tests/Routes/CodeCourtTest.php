<?php

// V112 (critère 22, RG-13, RG-21, écrans T6 et B6) : l'adresse `/p/{code}`
// d'une plante du jardin courant redirige vers sa fiche de terrain, quelle que
// soit la casse ; un code inexistant, ou un code qui n'existe que dans B, rend
// le même 404, jamais la fiche de B. Un `FRU-001` présent dans les deux jardins
// mène à la plante du jardin courant.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-22');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;

    // Le même code dans les deux jardins, et un code que B est seul à porter.
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $this->pommierA = Plante::factory()->create(['nom' => 'Pommier de A', 'code_court' => 'FRU-001']);
    app(JardinCourant::class)->positionner($this->jardins->b->id);
    $this->pommierB = Plante::factory()->create(['nom' => DeuxJardins::MARQUEUR.' Pommier', 'code_court' => 'FRU-001']);
    Plante::factory()->create(['nom' => DeuxJardins::MARQUEUR.' Haie', 'code_court' => 'HAI-007']);
    app()->forgetScopedInstances();
});

/** Une adresse ouverte par un utilisateur dans son jardin courant. */
function scanner(Utilisateur $u, string $jardin, string $code): TestResponse
{
    app()->forgetScopedInstances();

    return test()->withoutVite()->actingAs($u)->withSession(['jardin_id' => $jardin])->get('/p/'.$code);
}

it('ouvre la fiche de terrain de la plante, quelle que soit la casse', function (string $code) {
    scanner($this->jardins->membreA, $this->jardins->a->id, $code)
        ->assertRedirect("/plantes/{$this->pommierA->id}?contexte=terrain");
})->with(['majuscules' => ['FRU-001'], 'minuscules' => ['fru-001'], 'mêlées' => ['Fru-001']]);

it('mène à la plante du jardin courant quand le code existe dans les deux', function () {
    scanner($this->jardins->desDeux, $this->jardins->a->id, 'FRU-001')
        ->assertRedirect("/plantes/{$this->pommierA->id}?contexte=terrain");
    scanner($this->jardins->desDeux, $this->jardins->b->id, 'FRU-001')
        ->assertRedirect("/plantes/{$this->pommierB->id}?contexte=terrain");
});

it('rend le même 404 pour un code de B seul et pour un code inexistant', function () {
    $deB = scanner($this->jardins->desDeux, $this->jardins->a->id, 'HAI-007')->assertNotFound();
    $inexistant = scanner($this->jardins->desDeux, $this->jardins->a->id, 'HAI-008')->assertNotFound();

    expect($deB->getContent())->toBe($inexistant->getContent())
        ->and((string) $deB->getContent())->not->toContain(DeuxJardins::MARQUEUR)
        ->and((string) $deB->getContent())->not->toContain($this->pommierB->id);
});

it('rend 404 pour un code mal formé', function (string $code) {
    scanner($this->jardins->membreA, $this->jardins->a->id, $code)->assertNotFound();
})->with(['trop long' => [str_repeat('A', 13)], 'identifiant de plante' => ['3f1c2a9e-0000-4000-8000-000000000000'], 'joker' => ['FRU-%']]);

it('envoie à la connexion sans session, puis revient à l\'adresse', function () {
    app()->forgetScopedInstances();
    $this->get('/p/FRU-001')->assertRedirect('/connexion');

    expect(session('url.intended'))->toEndWith('/p/FRU-001');
});
