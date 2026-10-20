<?php

// V130 (RG-27, critère 3, D-62, écrans B4, B5, B6, B12) : un inventaire
// incomplet ne casse aucune lecture. Pour un jardin vide, et pour une plante
// sans espèce, sans géométrie et sans règle, le tableau de bord, la liste des
// plantes, la fiche de la plante et l'export répondent 200.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\Export;

uses(RefreshDatabase::class)->group('critere-03');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
    $this->membre = Utilisateur::factory()->create();
    $this->jardin = Jardin::factory()->create(['nom' => 'Jardin vide']);
    Adhesion::create(['utilisateur_id' => $this->membre->id, 'jardin_id' => $this->jardin->id, 'role' => 'membre']);
});

/** Une lecture dans le jardin, par son membre. */
function lireIncomplet(string $chemin): TestResponse
{
    app()->forgetScopedInstances();

    return test()->withoutVite()->actingAs(test()->membre)->withSession(['jardin_id' => test()->jardin->id])->get($chemin);
}

it('répond pour un jardin vide', function (string $chemin) {
    lireIncomplet($chemin)->assertOk();
})->with(['tableau de bord' => '/', 'liste' => '/plantes', 'export' => '/export']);

it('rend un export vide mais complet pour un jardin vide', function () {
    $fichiers = Export::fichiers(lireIncomplet('/export')->assertOk());

    foreach (Export::CSV as $table) {
        $csv = Export::csv($fichiers["{$table}.csv"]);
        expect($csv['entetes'])->toContain('id')
            ->and($csv['lignes'])->toBe([]);
    }
    expect(json_decode($fichiers['donnees.json'], true)['plantes'])->toBe([]);
});

it('répond pour une plante sans espèce, sans géométrie ni règle', function (string $chemin) {
    app(JardinCourant::class)->positionner($this->jardin->id);
    $plante = Plante::factory()->create(['zone_id' => Zone::factory()->create()->id, 'nom' => 'Inconnue du fond']);

    $reponse = lireIncomplet(str_replace('{plante}', $plante->id, $chemin))->assertOk();
    if ($chemin === '/export') {
        $lignes = Export::csv(Export::fichiers($reponse)['plantes.csv'])['lignes'];
        expect($lignes)->toHaveCount(1)
            ->and($lignes[0])->toMatchArray(['nom' => 'Inconnue du fond', 'espece' => '']);
    }
})->with(['tableau de bord' => '/', 'liste' => '/plantes', 'fiche' => '/plantes/{plante}', 'export' => '/export']);
