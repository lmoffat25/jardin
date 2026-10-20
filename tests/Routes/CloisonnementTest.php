<?php

// V41 (critère 12, C15) : le test énumère toutes les routes de l'application.
// Connecté sur A, une route appelée avec les identifiants de B rend 404 ou 403,
// et aucune ne montre une ligne de B ni ne modifie B. Une route ni inscrite au
// registre ni déclarée en exception fait échouer le test.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\DeuxJardins;
use Tests\Support\RegistreDesRoutes;

uses(RefreshDatabase::class)->group('critere-12');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
});

/**
 * Une requête d'un utilisateur dans son jardin courant. Le conteneur oublie
 * le jardin courant de la requête d'avant, comme en production.
 *
 * @param  array<string, mixed>  $charge
 */
function appeler(Utilisateur $u, string $jardinCourant, string $cle, string $chemin, array $charge = []): TestResponse
{
    app()->forgetScopedInstances();

    return test()->withoutVite()
        ->actingAs($u)
        ->withSession(['jardin_id' => $jardinCourant])
        ->call(explode(' ', $cle, 2)[0], $chemin, $charge);
}

it('inscrit chaque route au registre ou en exception', function () {
    $absentes = RegistreDesRoutes::nonInscrites();
    if ($absentes !== []) {
        test()->fail('route '.implode(', route ', $absentes).' absente du registre');
    }
    expect(RegistreDesRoutes::orphelines())->toBe([]);

    foreach (RegistreDesRoutes::entrees() as $cle => $entree) {
        if ($entree['nature'] === 'ecrit' && ! isset($entree['charge'])) {
            test()->fail("route {$cle} : écrit sans charge déclarée");
        }
    }
});

it('voit une route ajoutée sans inscription', function () {
    Route::middleware(['web', 'metier'])->get('/route-oubliee/{plante}', fn () => 'oubliée');

    expect(RegistreDesRoutes::nonInscrites())->toBe(['GET /route-oubliee/{plante}']);
});

it('ne laisse passer aucune ligne de B', function (string $cle) {
    $entree = RegistreDesRoutes::entrees()[$cle];
    $b = $this->jardins->b->id;
    $avant = DeuxJardins::empreinte($b);

    $reponse = appeler(
        $this->jardins->proprietaireA,
        $this->jardins->a->id,
        $cle,
        RegistreDesRoutes::chemin($cle, $entree, $b),
        isset($entree['charge']) ? $entree['charge']($b) : [],
    );

    if (isset($entree['parametres'])) {
        expect($reponse->getStatusCode())->toBeIn([403, 404]);
    }
    $corps = (string) $reponse->getContent();
    expect($corps)->not->toContain(DeuxJardins::MARQUEUR);
    foreach ($this->jardins->identifiantsB as $id) {
        expect($corps)->not->toContain($id);
    }
    expect(DeuxJardins::empreinte($b))->toBe($avant);
})->with(array_keys(RegistreDesRoutes::entrees()));

it('ne montre pas B à qui adhère aux deux, depuis A', function (string $cle) {
    $entree = RegistreDesRoutes::entrees()[$cle];
    $a = $this->jardins->a->id;

    $reponse = appeler($this->jardins->desDeux, $a, $cle, RegistreDesRoutes::chemin($cle, $entree, $a));

    expect((string) $reponse->getContent())->not->toContain(DeuxJardins::MARQUEUR);
})->with(array_keys(array_filter(RegistreDesRoutes::entrees(), fn ($e) => $e['nature'] !== 'ecrit')));
