<?php

// Fin du 2b : l'authentification lit `utilisateurs` (D-43, D-81) ; un jardin
// rend son profil climatique et ses adhésions ; le profil est en lecture seule.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Comptes\Domaine\Utilisateur;

uses(RefreshDatabase::class);

it('authentifie par la table utilisateurs, mot de passe haché', function () {
    $utilisateur = Utilisateur::factory()->create(['courriel' => 'temoin@exemple.test', 'mot_de_passe_hache' => 'phrase-temoin']);

    $hache = DB::table('utilisateurs')->where('id', $utilisateur->id)->value('mot_de_passe_hache');
    expect($hache)->not->toBe('phrase-temoin');
    expect(Hash::check('phrase-temoin', (string) $hache))->toBeTrue();

    expect(Auth::attempt(['courriel' => 'temoin@exemple.test', 'password' => 'autre']))->toBeFalse();
    expect(Auth::attempt(['courriel' => 'temoin@exemple.test', 'password' => 'phrase-temoin']))->toBeTrue();
    expect(Auth::user())->toBeInstanceOf(Utilisateur::class);
    expect(Auth::id())->toBe($utilisateur->id);
    expect($utilisateur->toArray())->not->toHaveKey('mot_de_passe_hache');
});

it('relie le jardin à son profil et à ses adhésions, et l\'utilisateur aux siennes', function () {
    $this->seed(ReferenceSeeder::class);
    $jardin = Jardin::factory()->create(['profil_climatique_id' => 4]);
    $membre = Utilisateur::factory()->create();
    Adhesion::create(['utilisateur_id' => $jardin->proprietaire_id, 'jardin_id' => $jardin->id, 'role' => 'proprietaire']);
    Adhesion::create(['utilisateur_id' => $membre->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);

    expect($jardin->profilClimatique)->toBeInstanceOf(ProfilClimatique::class);
    expect($jardin->profilClimatique?->libelle)->toBe('Frais');
    expect($jardin->profilClimatique?->decalage_jours)->toBe(7);
    expect($jardin->adhesions->pluck('role', 'utilisateur_id')->all())
        ->toEqual([$jardin->proprietaire_id => 'proprietaire', $membre->id => 'membre']);
    expect($membre->adhesions->pluck('jardin_id')->all())->toBe([$jardin->id]);
    expect($membre->adhesions->first()?->jardin?->id)->toBe($jardin->id);
});

it('refuse d\'écrire ou de supprimer un profil climatique', function () {
    $this->seed(ReferenceSeeder::class);
    $profil = ProfilClimatique::findOrFail(3);

    $profil->libelle = 'Modifié';
    expect(fn () => $profil->save())->toThrow(LogicException::class);
    expect(fn () => ProfilClimatique::findOrFail(3)->delete())->toThrow(LogicException::class);
    expect(fn () => ProfilClimatique::create(['id' => 9, 'libelle' => 'Neuf', 'decalage_jours' => 0, 'ordre_affichage' => 9]))
        ->toThrow(LogicException::class);
    expect(DB::table('profils_climatiques')->where('id', 3)->value('libelle'))->toBe('Tempéré');
    expect(DB::table('profils_climatiques')->count())->toBe(5);
});
