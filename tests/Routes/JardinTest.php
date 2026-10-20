<?php

// V39 : un nom et un profil créent le jardin et l'adhésion `proprietaire`, et le
// jardin devient le jardin courant ; sans nom, sans profil, avec un profil ou un
// code de commune inconnus, rien n'est créé ; le nouveau jardin ne voit rien de
// l'autre ; changer le profil n'écrit que `jardins.profil_climatique_id`, et
// seule la commune est écrite, jamais des coordonnées (RG-28, D-48).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->utilisateur = Utilisateur::factory()->create();
});

/** Le jardin de l'utilisateur, avec son adhésion de rôle donné. */
function jardinDe(Utilisateur $utilisateur, string $role = 'proprietaire'): Jardin
{
    $jardin = Jardin::factory()->create(['proprietaire_id' => $utilisateur->id]);
    Adhesion::create(['utilisateur_id' => $utilisateur->id, 'jardin_id' => $jardin->id, 'role' => $role]);

    return $jardin;
}

it('rend l\'écran de création de jardin à un compte sans jardin', function () {
    $this->actingAs($this->utilisateur)->withoutVite()->get('/jardins/nouveau')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Bureau/Jardin')
            ->has('profils', 5)
            ->where('profils.0', ['id' => 1, 'libelle' => 'Très doux', 'decalage_jours' => -15]));
});

it('crée le jardin, l\'adhésion propriétaire, et en fait le jardin courant', function () {
    $this->actingAs($this->utilisateur)
        ->post('/jardins', ['nom' => 'Potager du bas', 'profil_climatique_id' => 4, 'commune_insee' => '25462'])
        ->assertStatus(302)
        ->assertHeader('Location', url('/'));

    $jardin = DB::table('jardins')->sole();
    expect($jardin->nom)->toBe('Potager du bas')
        ->and($jardin->proprietaire_id)->toBe($this->utilisateur->id)
        ->and($jardin->profil_climatique_id)->toBe(4)
        ->and($jardin->commune_insee)->toBe('25462')
        ->and([$jardin->latitude, $jardin->longitude, $jardin->altitude_m])->toBe([null, null, null]);

    $adhesion = DB::table('adhesions')->sole();
    expect([$adhesion->utilisateur_id, $adhesion->jardin_id, $adhesion->role])
        ->toBe([$this->utilisateur->id, $jardin->id, 'proprietaire']);

    expect(session('jardin_id'))->toBe($jardin->id)
        ->and(app(JardinCourant::class)->id())->toBe($jardin->id);
});

it('crée un jardin sans commune', function () {
    $this->actingAs($this->utilisateur)
        ->post('/jardins', ['nom' => 'Verger', 'profil_climatique_id' => 3])
        ->assertStatus(302);

    expect(DB::table('jardins')->sole()->commune_insee)->toBeNull();
});

it('ne crée rien sur une saisie refusée', function (array $saisie, string $champ) {
    $this->actingAs($this->utilisateur)
        ->postJson('/jardins', $saisie)
        ->assertStatus(422)
        ->assertJsonValidationErrors($champ);

    expect(DB::table('jardins')->count())->toBe(0)
        ->and(DB::table('adhesions')->count())->toBe(0);
})->with([
    'sans nom' => [['profil_climatique_id' => 3], 'nom'],
    'nom vide' => [['nom' => '   ', 'profil_climatique_id' => 3], 'nom'],
    'nom trop long' => [['nom' => str_repeat('a', 121), 'profil_climatique_id' => 3], 'nom'],
    'sans profil' => [['nom' => 'Verger'], 'profil_climatique_id'],
    'profil inconnu' => [['nom' => 'Verger', 'profil_climatique_id' => 99], 'profil_climatique_id'],
    'commune inconnue' => [['nom' => 'Verger', 'profil_climatique_id' => 3, 'commune_insee' => '9999'], 'commune_insee'],
]);

it('refuse la création à une session fermée', function () {
    $this->post('/jardins', ['nom' => 'Verger', 'profil_climatique_id' => 3])
        ->assertHeader('Location', url('/connexion'));

    expect(DB::table('jardins')->count())->toBe(0);
});

it('ne montre au nouveau jardin aucune donnée de l\'autre jardin', function () {
    $premier = jardinDe($this->utilisateur);
    app(JardinCourant::class)->positionner($premier->id);
    Plante::factory()->create(['nom' => 'Prunier du premier']);

    Route::middleware(['web', 'metier'])->get('/temoin-contenu', fn () => [
        'zones' => Zone::query()->pluck('nom')->all(),
        'plantes' => Plante::query()->pluck('nom')->all(),
    ]);

    $this->actingAs($this->utilisateur)
        ->post('/jardins', ['nom' => 'Second jardin', 'profil_climatique_id' => 2]);
    $second = Jardin::query()->where('nom', 'Second jardin')->sole();

    $this->actingAs($this->utilisateur)
        ->withSession(['jardin_id' => $second->id])
        ->getJson('/temoin-contenu')
        ->assertExactJson(['zones' => [], 'plantes' => []]);
    expect(DB::table('plantes')->where('jardin_id', $premier->id)->count())->toBe(1);
});

it('change le profil du jardin courant sans rien toucher d\'autre', function () {
    $jardin = jardinDe($this->utilisateur);
    DB::table('jardins')->where('id', $jardin->id)->update(['commune_insee' => '25462']);
    $avant = (array) DB::table('jardins')->where('id', $jardin->id)->sole();

    $this->actingAs($this->utilisateur)
        ->put('/jardin', ['nom' => $jardin->nom, 'profil_climatique_id' => 5])
        ->assertStatus(302);

    $apres = (array) DB::table('jardins')->where('id', $jardin->id)->sole();
    expect(array_keys(array_diff_assoc($apres, $avant)))->toBe(['profil_climatique_id'])
        ->and($apres['profil_climatique_id'])->toBe(5);
});

it('renomme le jardin et change sa commune', function () {
    $jardin = jardinDe($this->utilisateur);

    $this->actingAs($this->utilisateur)
        ->put('/jardin', ['nom' => 'Nouveau nom', 'profil_climatique_id' => 3, 'commune_insee' => '2A004'])
        ->assertStatus(302);

    $ligne = DB::table('jardins')->where('id', $jardin->id)->sole();
    expect([$ligne->nom, $ligne->commune_insee, $ligne->latitude, $ligne->longitude, $ligne->altitude_m])
        ->toBe(['Nouveau nom', '2A004', null, null, null]);
});

it('refuse la modification d\'un profil inconnu', function () {
    $jardin = jardinDe($this->utilisateur);

    $this->actingAs($this->utilisateur)
        ->putJson('/jardin', ['nom' => $jardin->nom, 'profil_climatique_id' => 99])
        ->assertStatus(422);

    expect(DB::table('jardins')->where('id', $jardin->id)->value('profil_climatique_id'))->toBe(3);
});

it('refuse la modification à un membre', function () {
    $jardin = jardinDe($this->utilisateur, 'membre');

    $this->actingAs($this->utilisateur)
        ->put('/jardin', ['nom' => 'Pris', 'profil_climatique_id' => 5])
        ->assertStatus(403);

    $ligne = DB::table('jardins')->where('id', $jardin->id)->sole();
    expect([$ligne->nom, $ligne->profil_climatique_id])->toBe([$jardin->nom, 3]);
});
