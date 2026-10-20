<?php

// V65 (CDC §5.1, écran B7) : la recherche trouve une espèce par son nom commun
// ou latin, sans égard à la casse ni aux accents, 20 résultats au plus ;
// chaque espèce porte ses variantes de conduite (RG-51), liste vide sinon.
// Une recherche vide est refusée en 422.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);

    // Des espèces témoins (RG-28) : un nom commun accentué, un nom latin, une
    // espèce à deux variantes, et 25 arbustes pour la limite.
    $pommier = [...TemoinBibliotheque::fruitier(), 'cle' => 'pommier-temoin', 'nom_commun' => 'Pommier témoin', 'nom_latin' => 'Malus exemplaris'];
    $pommier['regles'][0]['cle'] = 'pommier-temoin-taille';
    $eglantier = ['cle' => 'eglantier-temoin', 'nom_commun' => 'Églantier témoin', 'famille' => 'Famille témoin', 'categorie' => 'arbuste', 'regles' => []];
    $arbustes = array_map(fn (int $i) => [
        'cle' => "arbuste-temoin-{$i}", 'nom_commun' => sprintf('Arbuste témoin %02d', $i), 'famille' => 'Famille témoin', 'categorie' => 'arbuste', 'regles' => [],
    ], range(1, 25));
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier([
        'fruitiers' => [$pommier],
        'arbustes' => [$eglantier, ...$arbustes],
        'petits-fruits' => [TemoinBibliotheque::framboisier()],
    ])])->assertSuccessful();

    $this->utilisateur = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $this->utilisateur->id]);
    Adhesion::create(['utilisateur_id' => $this->utilisateur->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
});

it('trouve la même espèce par son nom, sans égard à la casse, et par son nom latin', function (string $q) {
    $this->actingAs($this->utilisateur)->getJson('/especes?q='.urlencode($q))
        ->assertOk()
        ->assertJsonPath('*.cle', ['pommier-temoin']);
})->with(['pommier', 'POMMIER', 'malus', 'Malus Exemplaris']);

it('cherche sans égard aux accents, dans un sens comme dans l\'autre', function (string $q) {
    $this->actingAs($this->utilisateur)->getJson('/especes?q='.urlencode($q))
        ->assertOk()
        ->assertJsonPath('*.cle', ['eglantier-temoin']);
})->with(['eglantier', 'ÉGLANTIER', 'églantier témoin', 'EGLANTIER TEMOIN']);

it('rend l\'identifiant, la clé, les noms, la catégorie et les variantes de chaque espèce', function () {
    $pommier = $this->actingAs($this->utilisateur)->getJson('/especes?q=pommier')->assertOk()->json('0');

    expect($pommier)->toEqual([
        'id' => $pommier['id'],
        'cle' => 'pommier-temoin',
        'nom_commun' => 'Pommier témoin',
        'nom_latin' => 'Malus exemplaris',
        'categorie' => 'fruitier',
        'variantes' => [],
    ])->and($pommier['id'])->toBeInt();

    $this->actingAs($this->utilisateur)->getJson('/especes?q=framboisier')
        ->assertOk()
        ->assertJsonPath('0.variantes', ['non-remontant', 'remontant']);
});

it('rend 20 résultats au plus', function () {
    $this->actingAs($this->utilisateur)->getJson('/especes?q=arbuste')
        ->assertOk()
        ->assertJsonCount(20);
});

it('lit les jokers de la recherche comme des caractères', function (string $q) {
    $this->actingAs($this->utilisateur)->getJson('/especes?q='.urlencode($q))
        ->assertOk()
        ->assertJsonCount(0);
})->with(['%', '_', 'arbuste%01']);

it('refuse une recherche vide', function (string $q) {
    $this->actingAs($this->utilisateur)->getJson('/especes?q='.urlencode($q))->assertUnprocessable();
})->with(['', ' ']);

it('refuse une session fermée', function () {
    $this->getJson('/especes?q=pommier')->assertUnauthorized();
});
