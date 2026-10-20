<?php

// V109 (critère 18, A4) : une haie tracée en ligne et une pelouse en polygone,
// avec leurs règles, ont leurs échéances calculées et listées au tableau de
// bord comme une plante en point.
//
// V110 (critère 24) : douze plantes de même espèce créées dans une zone, puis
// posées une par une, portent chacune une géométrie de source `plan`, sans
// aucun relevé GPS préalable.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Src\Bibliotheque\Domaine\Espece;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

/** Une requête neuve du propriétaire, dans son jardin. */
function auBureau(): mixed
{
    app()->forgetScopedInstances();

    return test()->withoutVite()->actingAs(test()->proprietaire)->withSession(['jardin_id' => test()->jardin->id]);
}

/** Trace $plante sur le plan, et vérifie que le tracé est accepté. */
function tracer(string $plante, string $type, array $coordonnees): void
{
    auBureau()->putJson("/plantes/{$plante}/geometrie", ['type' => $type, 'coordinates' => $coordonnees])->assertOk();
}

/**
 * Les échéances du tableau de bord, toutes périodes : « Plante » => « période · type · due_le · état ».
 *
 * @return array<string, list<string>>
 */
function echeancesParPlante(): array
{
    $props = [];
    auBureau()->get('/')->assertOk()->assertInertia(function (AssertableInertia $page) use (&$props) {
        $props = $page->component('Bureau/TableauDeBord')->toArray()['props'];
    });

    $blocs = [];
    foreach (['aujourdhui', 'semaine', 'mois'] as $periode) {
        $blocs[] = [$periode, $props[$periode]];
    }
    foreach ($props['planning'] as $mois) {
        $blocs[] = ["planning {$mois['mois']}", $mois['zones']];
    }

    $lignes = [];
    foreach ($blocs as [$periode, $zones]) {
        foreach ($zones as $zone) {
            foreach ($zone['echeances'] as $e) {
                $lignes[$e['plante']['nom']][] = "{$periode} · {$e['type']['libelle']} · {$e['cle']['due_le']} · {$e['etat']}";
            }
        }
    }

    return $lignes;
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->proprietaire = Utilisateur::factory()->create();
    $this->jardin = Jardin::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    Adhesion::create(['utilisateur_id' => $this->proprietaire->id, 'jardin_id' => $this->jardin->id, 'role' => 'proprietaire']);
    app(JardinCourant::class)->positionner($this->jardin->id);
    $this->zone = Zone::factory()->create(['nom' => 'Verger du bas'])->id;
});

it('calcule et liste les échéances d\'une haie en ligne et d\'une pelouse en polygone comme celles d\'un pommier en point', function () {
    // Le pommier et la haie portent la même taille ; la pelouse, la tonte de son espèce.
    $pommier = Plante::factory()->create(['zone_id' => $this->zone, 'nom' => 'Pommier témoin'])->id;
    $haie = Plante::factory()->create(['zone_id' => $this->zone, 'nom' => 'Haie champêtre'])->id;
    foreach ([$pommier, $haie] as $plante) {
        Fenetre::factory()->for(Regle::factory()->state(['plante_id' => $plante]))->create();
    }
    auBureau()->postJson('/plantes', [
        'nom' => 'Pelouse de l\'entrée', 'zone_id' => $this->zone, 'espece_id' => Espece::query()->where('cle', 'pelouse-temoin')->value('id'),
    ])->assertCreated();
    $pelouse = DB::table('plantes')->where('nom', 'Pelouse de l\'entrée')->value('id');

    tracer($pommier, 'Point', [4.83571, 45.76402]);
    tracer($haie, 'LineString', [[4.8350, 45.7640], [4.8352, 45.7641], [4.8354, 45.7640], [4.8356, 45.7641]]);
    tracer($pelouse, 'Polygon', [[[4.8360, 45.7640], [4.8364, 45.7640], [4.8364, 45.7643], [4.8360, 45.7643], [4.8360, 45.7640]]]);
    expect(DB::table('geometries')->orderBy('forme')->pluck('forme')->all())->toBe(['ligne', 'point', 'polygone']);

    $echeances = echeancesParPlante();

    expect($echeances['Pommier témoin'])->toBe(['aujourdhui · Taille d\'entretien/fructification · 2027-02-01 · ouverte'])
        ->and($echeances['Haie champêtre'])->toBe($echeances['Pommier témoin'])
        ->and($echeances['Pelouse de l\'entrée'] ?? [])->not->toBeEmpty()
        ->and(collect($echeances['Pelouse de l\'entrée'])->every(fn (string $l) => str_contains($l, ' · Tonte · ')))->toBeTrue();
})->group('critere-18');

it('pose douze pommiers créés dans une zone un par un, en source plan, sans relevé gps', function () {
    $fruitier = Espece::query()->where('cle', 'fruitier-temoin')->value('id');
    foreach (range(1, 12) as $n) {
        auBureau()->postJson('/plantes', ['nom' => "Pommier {$n}", 'zone_id' => $this->zone, 'espece_id' => $fruitier])->assertCreated();
    }
    $pommiers = DB::table('plantes')->where('zone_id', $this->zone)->orderBy('code_court')->pluck('id');
    expect($pommiers)->toHaveCount(12)
        ->and(DB::table('geometries')->count())->toBe(0);

    foreach ($pommiers as $i => $pommier) {
        tracer($pommier, 'Point', [4.8350 + $i * 0.0001, 45.7640]);
    }

    expect(DB::table('geometries')->whereIn('plante_id', $pommiers)->get()->map(fn ($g) => [$g->source, $g->forme, $g->precision_m])->unique()->values()->all())
        ->toBe([['plan', 'point', null]])
        ->and(DB::table('geometries')->distinct()->count('plante_id'))->toBe(12)
        ->and(DB::table('interventions')->count())->toBe(0);
})->group('critere-24');
