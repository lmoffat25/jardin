<?php

// V37 : la commande crée le compte propriétaire, lit le mot de passe sur
// l'entrée standard (jamais en argument), et refuse si un compte existe déjà.
// Elle est découverte dans src/Comptes/Console sans enregistrement (D-46).

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Comptes\Console\CreerProprietaire;
use Src\Comptes\Domaine\Utilisateur;

uses(RefreshDatabase::class);

it('est découverte sans enregistrement manuel', function () {
    expect(Artisan::all())->toHaveKey('jardin:creer-proprietaire');
    expect(Artisan::all()['jardin:creer-proprietaire'])->toBeInstanceOf(CreerProprietaire::class);
    expect(file_get_contents(base_path('bootstrap/app.php')))->not->toContain('CreerProprietaire');
});

it('crée le compte, le mot de passe lu sur l\'entrée et jamais affiché', function () {
    $this->artisan('jardin:creer-proprietaire', ['courriel' => 'proprietaire@exemple.test'])
        ->expectsQuestion('Mot de passe', 'phrase-secrete-temoin')
        ->doesntExpectOutputToContain('phrase-secrete-temoin')
        ->assertExitCode(0);

    $compte = DB::table('utilisateurs')->sole();
    expect($compte->courriel)->toBe('proprietaire@exemple.test');
    expect(Hash::check('phrase-secrete-temoin', $compte->mot_de_passe_hache))->toBeTrue();
});

it('n\'accepte pas le mot de passe en argument', function () {
    $definition = Artisan::all()['jardin:creer-proprietaire']->getDefinition();

    expect(array_keys($definition->getArguments()))->toBe(['courriel']);
    expect(array_keys($definition->getOptions()))->not->toContain('mot_de_passe', 'password', 'mot-de-passe');
});

it('refuse si un compte existe déjà, sans rien écrire', function () {
    Utilisateur::factory()->create(['courriel' => 'premier@exemple.test']);

    $this->artisan('jardin:creer-proprietaire', ['courriel' => 'second@exemple.test'])
        ->assertExitCode(1);

    expect(DB::table('utilisateurs')->pluck('courriel')->all())->toBe(['premier@exemple.test']);
});

it('refuse un mot de passe vide', function () {
    $this->artisan('jardin:creer-proprietaire', ['courriel' => 'proprietaire@exemple.test'])
        ->expectsQuestion('Mot de passe', '')
        ->assertExitCode(1);

    expect(DB::table('utilisateurs')->count())->toBe(0);
});
