<?php

// V36 : un courriel et un mot de passe justes ouvrent une session et redirigent
// vers `/` (la page d'arrivée est vérifiée au 6 par V76, D-47) ; un refus ne dit
// pas lequel des deux champs est faux ; sans session, une route métier
// redirige vers `/connexion`.

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Utilisateur;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->utilisateur = Utilisateur::factory()->create(['courriel' => 'temoin@exemple.test', 'mot_de_passe_hache' => 'phrase-temoin']);
});

it('rend l\'écran de connexion', function () {
    $this->withoutVite()->get('/connexion')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Bureau/Connexion'));
});

it('ouvre une session et redirige vers l\'accueil', function () {
    $this->travelTo('2026-10-20 09:30:00');

    $this->post('/connexion', ['courriel' => 'temoin@exemple.test', 'mot_de_passe' => 'phrase-temoin'])
        ->assertStatus(302)
        ->assertHeader('Location', url('/'));

    $this->assertAuthenticatedAs($this->utilisateur);
    $relue = DB::table('utilisateurs')->where('id', $this->utilisateur->id)->value('derniere_connexion_le');
    expect($relue)->not->toBeNull();
    expect(Carbon::parse((string) $relue)->equalTo('2026-10-20 09:30:00'))->toBeTrue();
});

it('régénère la session à la connexion', function () {
    // La requête porte le cookie d'une session existante : sans régénération,
    // elle garderait cet identifiant (fixation de session).
    $avant = str_repeat('a', 40);

    $this->withCookie((string) config('session.cookie'), $avant)
        ->post('/connexion', ['courriel' => 'temoin@exemple.test', 'mot_de_passe' => 'autre']);
    expect(session()->getId())->toBe($avant);

    $this->withCookie((string) config('session.cookie'), $avant)
        ->post('/connexion', ['courriel' => 'temoin@exemple.test', 'mot_de_passe' => 'phrase-temoin']);
    expect(session()->getId())->not->toBe($avant);
});

it('refuse un mot de passe faux et un courriel inconnu par le même message', function () {
    $faux = $this->from('/connexion')
        ->post('/connexion', ['courriel' => 'temoin@exemple.test', 'mot_de_passe' => 'autre'])
        ->assertStatus(302)
        ->assertHeader('Location', url('/connexion'))
        ->assertSessionHasErrors(['courriel' => 'Identifiants incorrects.'])
        ->assertSessionDoesntHaveErrors('mot_de_passe');
    $this->assertGuest();

    $inconnu = $this->from('/connexion')
        ->post('/connexion', ['courriel' => 'inconnu@exemple.test', 'mot_de_passe' => 'phrase-temoin'])
        ->assertSessionHasErrors(['courriel' => 'Identifiants incorrects.'])
        ->assertSessionDoesntHaveErrors('mot_de_passe');
    $this->assertGuest();

    expect(DB::table('utilisateurs')->where('id', $this->utilisateur->id)->value('derniere_connexion_le'))->toBeNull();
});

it('bloque le sixième essai de la minute, même juste', function () {
    foreach (range(1, 5) as $_) {
        $this->post('/connexion', ['courriel' => 'temoin@exemple.test', 'mot_de_passe' => 'autre']);
    }

    $this->from('/connexion')
        ->post('/connexion', ['courriel' => 'temoin@exemple.test', 'mot_de_passe' => 'phrase-temoin'])
        ->assertHeader('Location', url('/connexion'))
        ->assertSessionHasErrors('courriel');
    $this->assertGuest();
});

it('redirige vers /connexion une route métier sans session', function () {
    Route::middleware(['web', 'metier'])->get('/metier-temoin', fn () => 'métier');

    $this->get('/metier-temoin')
        ->assertStatus(302)
        ->assertHeader('Location', url('/connexion'));
});

it('ferme la session', function () {
    $this->actingAs($this->utilisateur)
        ->post('/deconnexion')
        ->assertStatus(302)
        ->assertHeader('Location', url('/connexion'));

    $this->assertGuest();
});
