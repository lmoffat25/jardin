<?php

// V136 (RG-23, RG-26, RG-52, D-66, écran B2) : le profil se propose depuis la
// commune. Nominal : le profil le plus proche du décalage calculé, avec la
// station, l'écart et le décalage ; Montagne au-dessus de 600 m ; la question
// d'altitude entre 400 et 800 m. Recherche par nom (sans accents ni casse) ou
// code postal, 10 résultats au plus. Commune inconnue : 404, et les cinq
// profils restent proposés. Rien n'est écrit.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\TemoinClimat;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier()])->assertSuccessful();
    $this->utilisateur = Utilisateur::factory()->create();
});

it('propose le profil le plus proche du décalage calculé, avec sa raison', function () {
    $this->actingAs($this->utilisateur)->getJson('/communes/99107/profil')
        ->assertOk()
        ->assertExactJson([
            'profil_climatique_id' => 2,
            'station' => 'Station du Sud-Témoin',
            'ecart_c' => 1.2,
            'decalage_jours' => -7,
            'demander_altitude' => false,
            'confiance' => 'indicative',
        ]);
});

it('propose Montagne au-dessus de 600 m, et demande l\'altitude entre 400 et 800 m', function (string $code, int $profil, bool $demander) {
    $this->actingAs($this->utilisateur)->getJson("/communes/{$code}/profil")
        ->assertOk()
        ->assertJson(['profil_climatique_id' => $profil, 'demander_altitude' => $demander]);
})->with([
    '720 m : montagne, et la question' => ['99104', 5, true],
    '910 m : montagne, sans question' => ['99106', 5, false],
    '460 m : frais, et la question' => ['99105', 4, true],
    '100 m : tempéré, sans question' => ['99101', 3, false],
]);

it('cherche par nom, sans accents ni casse', function (string $q, array $codes) {
    $this->actingAs($this->utilisateur)->getJson('/communes?q='.urlencode($q))
        ->assertOk()
        ->assertJsonPath('*.code_insee', $codes);
})->with([
    'majuscules sans accent' => ['ECLUSE', ['99112']],
    'tiret et accents' => ['écluse-saint', ['99112']],
    'aucune' => ['Inconnue', []],
]);

it('rend le code, le nom et les codes postaux de chaque commune', function () {
    $this->actingAs($this->utilisateur)->getJson('/communes?q=99510')
        ->assertOk()
        ->assertExactJson([['code_insee' => '99105', 'nom' => 'Val-des-Coteaux-Témoin', 'codes_postaux' => ['99510', '99520']]]);
});

it('cherche par code postal, entier ou commencé', function (string $q, array $codes) {
    $codesRendus = collect($this->actingAs($this->utilisateur)->getJson("/communes?q={$q}")->assertOk()->json())
        ->pluck('code_insee')->sort()->values()->all();

    expect($codesRendus)->toBe($codes);
})->with([
    'second code postal d\'une commune' => ['99520', ['99105', '99112']],
    'début de code postal' => ['9980', ['99108', '99109', '99110', '99111']],
]);

it('rend 10 résultats au plus', function () {
    $this->actingAs($this->utilisateur)->getJson('/communes?q=val')
        ->assertOk()
        ->assertJsonCount(10);
});

it('rend 404 pour une commune inconnue, et propose les cinq profils', function () {
    $this->actingAs($this->utilisateur)->getJson('/communes/99999/profil')
        ->assertNotFound()
        ->assertJsonCount(5, 'profils')
        ->assertJsonPath('profils.0', ['id' => 1, 'libelle' => 'Très doux', 'decalage_jours' => -15]);
});

it('n\'écrit rien', function () {
    $avant = [DB::table('jardins')->count(), DB::table('communes')->get()->toArray()];

    $this->actingAs($this->utilisateur)->getJson('/communes?q=val')->assertOk();
    $this->actingAs($this->utilisateur)->getJson('/communes/99104/profil')->assertOk();
    $this->actingAs($this->utilisateur)->getJson('/communes/99999/profil')->assertNotFound();

    expect([DB::table('jardins')->count(), DB::table('communes')->get()->toArray()])->toEqual($avant);
});

it('refuse une session fermée', function () {
    $this->getJson('/communes?q=val')->assertUnauthorized();
    $this->getJson('/communes/99101/profil')->assertUnauthorized();
});
