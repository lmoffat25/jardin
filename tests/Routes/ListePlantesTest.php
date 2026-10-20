<?php

// V89 (critère 11, RG-19, écran B5) : à 500 plantes, la liste rend au plus 50
// lignes par page et le nombre total, avec le même nombre de requêtes qu'à 20 ;
// la recherche porte sur le nom ou le code court exact, les filtres sur la zone
// et l'état. Une plante archivée sort de la liste par défaut (D-103).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-11');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->withoutVite();
});

/**
 * Un jardin de $n plantes dans cinq zones, écrit en masse ; la plante $p porte
 * le code `LST-<p>` et le nom `Plante <p>`. Rend son membre et ses zones.
 *
 * @return array{Utilisateur, list<string>}
 */
function jardinALister(int $n): array
{
    $membre = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $membre->id]);
    Adhesion::create(['utilisateur_id' => $membre->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);

    $zones = array_map(fn (int $z) => ['id' => (string) Str::uuid(), 'jardin_id' => $jardin->id, 'nom' => "Zone {$z}"], range(1, 5));
    DB::table('zones')->insert($zones);
    $plantes = array_map(fn (int $p) => [
        'id' => (string) Str::uuid(),
        'jardin_id' => $jardin->id,
        'zone_id' => $zones[$p % 5]['id'],
        'code_court' => sprintf('LST-%03d', $p),
        'nom' => "Plante {$p}",
    ], range(1, $n));
    foreach (array_chunk($plantes, 500) as $paquet) {
        DB::table('plantes')->insert($paquet);
    }

    return [$membre, array_column($zones, 'id')];
}

/** @return array<string, mixed> les props de la liste */
function propsDeLaListe(Utilisateur $membre, string $requete = ''): array
{
    app()->forgetScopedInstances();
    test()->flushSession();
    $reponse = test()->actingAs($membre)->get('/plantes'.$requete)->assertOk();
    $reponse->assertInertia(fn (AssertableInertia $p) => $p->component('Bureau/Plantes'));

    return $reponse->viewData('page')['props'];
}

it('rend 50 lignes et le total à 500 plantes, avec autant de requêtes qu\'à 20', function () {
    $compter = function (Utilisateur $membre): int {
        $requetes = 0;
        DB::listen(function () use (&$requetes) {
            $requetes++;
        });
        propsDeLaListe($membre);

        return $requetes;
    };
    [$petit] = jardinALister(20);
    $requetes20 = $compter($petit);
    [$grand] = jardinALister(500);
    $requetes500 = $compter($grand);

    $props = propsDeLaListe($grand);
    expect($props['plantes'])->toHaveCount(50)
        ->and([$props['total'], $props['page'], $props['pages']])->toBe([500, 1, 10])
        ->and($requetes500)->toBe($requetes20)
        ->and($props['plantes'][0])->toHaveKeys(['id', 'nom', 'code_court', 'repere', 'etat', 'zone', 'miniature'])
        ->and($props['plantes'][0]['miniature'])->toBeNull();

    $derniere = propsDeLaListe($grand, '?page=10');
    expect($derniere['plantes'])->toHaveCount(50)->and($derniere['page'])->toBe(10)
        ->and(array_intersect(array_column($derniere['plantes'], 'id'), array_column($props['plantes'], 'id')))->toBe([]);
});

it('trouve une plante par son code court exact, sans tenir compte de la casse', function () {
    [$membre] = jardinALister(500);

    $props = propsDeLaListe($membre, '?q=lst-123');
    expect(array_column($props['plantes'], 'code_court'))->toBe(['LST-123'])
        ->and($props['total'])->toBe(1);

    // Un code partiel ne vaut pas code : « LST-12 » n'est le nom d'aucune plante.
    expect(propsDeLaListe($membre, '?q=LST-12')['total'])->toBe(0);
});

it('cherche dans le nom sans tenir compte de la casse, et sans joker', function () {
    [$membre] = jardinALister(500);

    $props = propsDeLaListe($membre, '?q=plante 12');
    // Plante 12, 120 à 129 : onze plantes.
    expect($props['total'])->toBe(11);

    DB::table('plantes')->where('nom', 'Plante 7')->update(['nom' => 'Rosier 100% rouge']);
    expect(propsDeLaListe($membre, '?q=100%25')['total'])->toBe(1)
        ->and(propsDeLaListe($membre, '?q=_')['total'])->toBe(0);
});

it('filtre par zone et par état ; une plante archivée sort de la liste par défaut', function () {
    [$membre, $zones] = jardinALister(500);
    $archivee = DB::table('plantes')->where('code_court', 'LST-005')->value('id');
    DB::table('plantes')->where('id', $archivee)->update(['etat' => 'archivee', 'archivee_le' => now(), 'motif_archivage' => 'Arrachée']);

    $zone = propsDeLaListe($membre, '?zone='.$zones[0]);
    expect($zone['total'])->toBe(99)
        ->and(collect($zone['plantes'])->pluck('zone.id')->unique()->all())->toBe([$zones[0]]);

    expect(propsDeLaListe($membre)['total'])->toBe(499)
        ->and(array_column(propsDeLaListe($membre, '?etat=archivee')['plantes'], 'id'))->toBe([$archivee])
        ->and(propsDeLaListe($membre, '?etat=toutes')['total'])->toBe(500);
});

it('refuse un filtre inconnu', function (string $requete, string $champ) {
    [$membre] = jardinALister(3);
    app()->forgetScopedInstances();

    $this->actingAs($membre)->getJson('/plantes'.$requete)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$champ]);
})->with([
    'état inconnu' => ['?etat=morte', 'etat'],
    'zone qui n\'est pas un identifiant' => ['?zone=potager', 'zone'],
    'page nulle' => ['?page=0', 'page'],
]);

it('ne montre aucune plante de B, même filtrée sur une zone de B', function () {
    $jardins = new DeuxJardins;
    $zoneB = DB::table('zones')->where('jardin_id', $jardins->b->id)->value('id');

    foreach (['', '?zone='.$zoneB, '?q=Plante', '?etat=toutes'] as $requete) {
        app()->forgetScopedInstances();
        $corps = (string) $this->actingAs($jardins->proprietaireA)
            ->withSession(['jardin_id' => $jardins->a->id])
            ->get('/plantes'.$requete)
            ->assertOk()
            ->getContent();
        expect($corps)->not->toContain(DeuxJardins::MARQUEUR);
    }
});
