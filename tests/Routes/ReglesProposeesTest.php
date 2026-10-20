<?php

// V65 (RG-25, RG-31, RG-51, écran B7) : les règles proposées d'une espèce sont
// ses règles de référence en vigueur, avec leurs périodes décalées pour le
// profil du jardin courant et l'année courante, leur confiance et leurs
// sources. Pour une espèce à variantes : les règles communes et celles de la
// variante demandée, et 422 sans variante. Aucune route n'écrit sous
// `/especes`.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Src\Bibliotheque\Domaine\Espece;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

/** Un utilisateur membre d'un jardin neuf du profil donné. */
function membreDuProfil(int $profil): Utilisateur
{
    $utilisateur = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $utilisateur->id, 'profil_climatique_id' => $profil]);
    Adhesion::create(['utilisateur_id' => $utilisateur->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);

    return $utilisateur;
}

function espece(string $cle): int
{
    return Espece::query()->where('cle', $cle)->value('id');
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier([
        'fruitiers' => [TemoinBibliotheque::fruitier()],
        'pelouses' => [TemoinBibliotheque::pelouse()],
        'petits-fruits' => [TemoinBibliotheque::framboisier()],
    ])])->assertSuccessful();
    $this->travelTo(new DateTimeImmutable('2026-10-20 10:00', new DateTimeZone('Europe/Paris')));
});

it('rend la règle, sa confiance et ses sources, la fenêtre de l\'année courante dans le climat de référence', function () {
    $this->actingAs(membreDuProfil(3))->getJson('/especes/'.espece('fruitier-temoin').'/regles-proposees')
        ->assertOk()
        ->assertExactJson([[
            'cle' => 'fruitier-temoin-taille',
            'type_intervention' => 'Taille d\'entretien/fructification',
            'type_regle' => 'R2',
            'confiance' => 'verifiee',
            'periodes' => [[
                'libelle' => 'Fin d\'hiver',
                'debut' => '2026-02-01', 'fin' => '2026-03-31',
                'optimal_debut' => '2026-02-15', 'optimal_fin' => '2026-03-15',
            ]],
            'intervalles' => [],
            'sources' => [
                ['url' => 'https://conservatoire.exemple.test/fruitier', 'libelle' => 'Conservatoire témoin', 'consultee_le' => '2026-10-20'],
                ['url' => 'https://institut.exemple.test/fruitier', 'libelle' => 'Institut témoin', 'consultee_le' => '2026-10-20'],
            ],
        ]]);
});

it('décale les périodes selon le profil du jardin courant', function (int $profil, array $periode) {
    $this->actingAs(membreDuProfil($profil))->getJson('/especes/'.espece('fruitier-temoin').'/regles-proposees')
        ->assertOk()
        ->assertJsonPath('0.periodes.0', ['libelle' => 'Fin d\'hiver', ...$periode]);
})->with([
    'Très doux, 15 jours plus tôt' => [1, ['debut' => '2026-01-17', 'fin' => '2026-03-16', 'optimal_debut' => '2026-01-31', 'optimal_fin' => '2026-02-28']],
    'Montagne, 21 jours plus tard' => [5, ['debut' => '2026-02-22', 'fin' => '2026-04-21', 'optimal_debut' => '2026-03-08', 'optimal_fin' => '2026-04-05']],
]);

it('décale les bornes de saison d\'une règle R1, jamais son intervalle', function (int $profil, array $intervalles) {
    $this->actingAs(membreDuProfil($profil))->getJson('/especes/'.espece('pelouse-temoin').'/regles-proposees')
        ->assertOk()
        ->assertJsonPath('0.type_regle', 'R1')
        ->assertJsonPath('0.periodes', [])
        ->assertJsonPath('0.intervalles', $intervalles);
})->with([
    'Tempéré' => [3, [
        ['mois_debut' => 4, 'mois_fin' => 9, 'jours' => 7, 'debut' => '2026-04-01', 'fin' => '2026-09-30'],
        ['mois_debut' => 10, 'mois_fin' => 3, 'jours' => 0, 'debut' => '2026-10-01', 'fin' => '2027-03-31'],
    ]],
    'Très doux' => [1, [
        ['mois_debut' => 4, 'mois_fin' => 9, 'jours' => 7, 'debut' => '2026-03-17', 'fin' => '2026-09-15'],
        ['mois_debut' => 10, 'mois_fin' => 3, 'jours' => 0, 'debut' => '2026-09-16', 'fin' => '2027-03-16'],
    ]],
]);

it('rend les règles communes et celles de la variante demandée', function (string $variante, array $cles) {
    $this->actingAs(membreDuProfil(3))->getJson('/especes/'.espece('framboisier-temoin')."/regles-proposees?variante={$variante}")
        ->assertOk()
        ->assertJsonPath('*.cle', $cles);
})->with([
    'remontant' => ['remontant', ['framboisier-temoin-recolte', 'framboisier-temoin-taille-remontant']],
    'non remontant' => ['non-remontant', ['framboisier-temoin-recolte', 'framboisier-temoin-taille-non-remontant']],
]);

it('refuse une espèce à variantes sans variante, ou avec une variante inconnue', function (string $requete) {
    $this->actingAs(membreDuProfil(3))->getJson('/especes/'.espece('framboisier-temoin')."/regles-proposees{$requete}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variante');
})->with(['', '?variante=', '?variante=inconnue']);

it('refuse une variante pour une espèce qui n\'en a pas', function () {
    $this->actingAs(membreDuProfil(3))->getJson('/especes/'.espece('fruitier-temoin').'/regles-proposees?variante=remontant')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('variante');
});

it('ne propose pas une règle sortie de vigueur', function () {
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier([
        'fruitiers' => [[...TemoinBibliotheque::fruitier(), 'regles' => []]],
    ])])->assertSuccessful();

    $this->actingAs(membreDuProfil(3))->getJson('/especes/'.espece('fruitier-temoin').'/regles-proposees')
        ->assertOk()
        ->assertExactJson([]);
});

it('rend 404 pour une espèce inconnue', function () {
    $this->actingAs(membreDuProfil(3))->getJson('/especes/999999/regles-proposees')->assertNotFound();
});

it('refuse une session fermée', function () {
    $this->getJson('/especes/'.espece('fruitier-temoin').'/regles-proposees')->assertUnauthorized();
});

it('n\'a aucune route en écriture sous /especes', function () {
    $routes = array_filter(Route::getRoutes()->getRoutes(), fn ($r) => str_starts_with($r->uri(), 'especes'));

    expect($routes)->not->toBeEmpty();
    foreach ($routes as $route) {
        expect(array_diff($route->methods(), ['GET', 'HEAD']))->toBe([], "{$route->uri()} écrit");
    }
});
