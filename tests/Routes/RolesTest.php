<?php

// V128 (CDC §2.1, RG-20, D-49) : chaque route du registre déclare le rôle
// qu'elle exige. Connecté en membre de A, une route réservée au propriétaire
// rend 403 et laisse A intact ; une route ouverte au membre rend le même code
// que pour le propriétaire.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\DeuxJardins;
use Tests\Support\RegistreDesRoutes;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
});

/** Le code rendu à un utilisateur pour une entrée, appelée dans A. */
function codePour(Utilisateur $u, string $cle, string $a): int
{
    $entree = RegistreDesRoutes::entrees()[$cle];
    app()->forgetScopedInstances();

    return test()->withoutVite()
        ->actingAs($u)
        ->withSession(['jardin_id' => $a])
        ->call(explode(' ', $cle, 2)[0], RegistreDesRoutes::chemin($cle, $entree, $a), isset($entree['charge']) ? $entree['charge']($a) : [])
        ->getStatusCode();
}

it('ne laisse au membre que ce que son rôle permet', function (string $cle) {
    $a = $this->jardins->a->id;
    $avant = DeuxJardins::empreinte($a);

    $membre = codePour($this->jardins->membreA, $cle, $a);

    if (RegistreDesRoutes::entrees()[$cle]['role'] === 'proprietaire') {
        expect($membre)->toBe(403)
            ->and(DeuxJardins::empreinte($a))->toBe($avant);
    } else {
        expect($membre)->toBe(codePour($this->jardins->proprietaireA, $cle, $a));
    }
})->with(array_keys(RegistreDesRoutes::entrees()));

it('réserve au propriétaire une route sous role:proprietaire, sans autre garde', function () {
    Route::middleware(['web', 'metier', 'role:proprietaire'])->post('/reservee-temoin', fn () => 'faite');
    $a = $this->jardins->a->id;

    app()->forgetScopedInstances();
    $this->actingAs($this->jardins->membreA)->withSession(['jardin_id' => $a])
        ->post('/reservee-temoin')->assertForbidden();

    app()->forgetScopedInstances();
    $this->actingAs($this->jardins->proprietaireA)->withSession(['jardin_id' => $a])
        ->post('/reservee-temoin')->assertOk()->assertSee('faite');
});

it('juge le rôle dans le jardin courant, pas dans un autre', function () {
    // Propriétaire de A et membre de B : dans B, il n'est que membre.
    Route::middleware(['web', 'metier', 'role:proprietaire'])->post('/reservee-temoin', fn () => 'faite');

    app()->forgetScopedInstances();
    $this->actingAs($this->jardins->desDeux)->withSession(['jardin_id' => $this->jardins->b->id])
        ->post('/reservee-temoin')->assertForbidden();
});
