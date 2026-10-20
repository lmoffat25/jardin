<?php

// V111 (critère 9, RG-15, Q8, écrans T3 et B7) : une plante créée avec la
// position du téléphone porte une géométrie point de source `gps` avec sa
// précision, sans champ de plus. La liste des plantes proches d'une position
// rend au plus 10 candidates triées par distance à leur position affichée,
// puis les plantes de la zone courante, et accepte une recherche par nom ou
// code court ; elle ne présélectionne jamais une plante seule ; aucune plante
// de B n'y figure.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-09');

/** Le point d'où l'on cherche, au milieu d'un verger inventé. */
const ICI = ['lng' => 4.83500, 'lat' => 45.76400];

/** Un pas vers le nord : environ 5,56 mètres. */
const PAS = 0.00005;

/** Une géométrie posée en base, hors de l'application. */
function situer(string $jardin, string $plante, array $geojson, string $source = 'gps'): void
{
    DB::table('geometries')->insert([
        'id' => (string) Str::uuid(), 'jardin_id' => $jardin, 'plante_id' => $plante,
        'forme' => ['Point' => 'point', 'LineString' => 'ligne', 'Polygon' => 'polygone'][$geojson['type']],
        'coordonnees' => json_encode($geojson), 'source' => $source, 'relevee_le' => '2026-06-01 12:00:00+02',
    ]);
}

/** Les plantes proches, demandées par le membre de A depuis A. */
function proches(array $parametres): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->getJson('/plantes/proches?'.http_build_query($parametres));
}

/** Les noms d'une liste de la réponse. */
function noms(TestResponse $reponse, string $liste): array
{
    return array_column($reponse->json($liste), 'nom');
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;
    $a = $this->jardins->a->id;
    $this->verger = DB::table('zones')->where('nom', 'A Verger')->value('id');
    $this->potager = DB::table('zones')->where('nom', 'A Potager')->value('id');

    // Trente témoins plantés vers le nord, le n-ième à n pas d'ICI, dans le
    // verger s'il est pair ; créés dans le désordre.
    app(JardinCourant::class)->positionner($a);
    $this->temoins = [];
    foreach ([17, 4, 29, 1, 22, 9, 13, 30, 6, 2, 25, 11, 19, 8, 3, 27, 15, 5, 21, 10, 7, 24, 12, 28, 16, 20, 14, 18, 23, 26] as $n) {
        $plante = Plante::factory()->create(['nom' => "Témoin {$n}", 'zone_id' => $n % 2 === 0 ? $this->verger : $this->potager]);
        $this->temoins[$n] = $plante;
        situer($a, $plante->id, ['type' => 'Point', 'coordinates' => [ICI['lng'], ICI['lat'] + $n * PAS]]);
    }

    // Les plantes de B sont posées exactement ici : aucune ne doit sortir.
    foreach (DB::table('plantes')->where('jardin_id', $this->jardins->b->id)->pluck('id') as $planteB) {
        situer($this->jardins->b->id, $planteB, ['type' => 'Point', 'coordinates' => [ICI['lng'], ICI['lat']]], 'plan');
    }
    app()->forgetScopedInstances();
});

it('crée une plante située par la position du téléphone, en point gps avec sa précision', function () {
    app()->forgetScopedInstances();
    $reponse = $this->actingAs($this->jardins->proprietaireA)->withSession(['jardin_id' => $this->jardins->a->id])
        ->postJson('/plantes', ['nom' => 'Cognassier du talus', 'zone_id' => $this->verger, 'position' => ['lng' => 4.83571, 'lat' => 45.76402, 'precision_m' => 8.6]])
        ->assertCreated();

    $g = DB::table('geometries')->where('plante_id', $reponse->json('id'))->sole();
    expect([$g->jardin_id, $g->forme, $g->source, $g->precision_m, json_decode($g->coordonnees, true)])
        ->toBe([$this->jardins->a->id, 'point', 'gps', 9, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]]])
        ->and((new DateTimeImmutable($g->relevee_le))->getTimestamp())->toBe(now()->getTimestamp());
});

it('crée une plante sans position, sans géométrie', function () {
    app()->forgetScopedInstances();
    $reponse = $this->actingAs($this->jardins->proprietaireA)->withSession(['jardin_id' => $this->jardins->a->id])
        ->postJson('/plantes', ['nom' => 'Cognassier du talus', 'zone_id' => $this->verger])
        ->assertCreated();

    expect(DB::table('geometries')->where('plante_id', $reponse->json('id'))->exists())->toBeFalse();
});

it('refuse une position hors bornes, et ne crée rien', function () {
    $plantes = DB::table('plantes')->count();
    app()->forgetScopedInstances();
    $this->actingAs($this->jardins->proprietaireA)->withSession(['jardin_id' => $this->jardins->a->id])
        ->postJson('/plantes', ['nom' => 'Cognassier du talus', 'zone_id' => $this->verger, 'position' => ['lng' => 4.8, 'lat' => 91]])
        ->assertUnprocessable()->assertJsonValidationErrors('position.lat');

    expect(DB::table('plantes')->count())->toBe($plantes);
});

it('rend les dix plus proches, triées par distance, puis le reste de la zone courante', function () {
    $reponse = proches([...ICI, 'precision_m' => 12, 'zone' => $this->verger])->assertOk();

    expect(noms($reponse, 'proches'))->toBe(array_map(fn (int $n) => "Témoin {$n}", range(1, 10)));
    foreach ($reponse->json('proches') as $i => $p) {
        expect(array_keys($p))->toBe(['id', 'nom', 'code_court', 'distance_m'])
            ->and($p['id'])->toBe($this->temoins[$i + 1]->id)
            ->and($p['code_court'])->toBe($this->temoins[$i + 1]->code_court)
            ->and(abs($p['distance_m'] - ($i + 1) * 5.56))->toBeLessThan(1.0);
    }

    // Les plantes du verger qui ne sont pas déjà proposées, par nom : les
    // deux plantes sans position, puis les témoins pairs au-delà du dixième.
    expect(noms($reponse, 'zone'))->toBe(['A Plante Verger 1', 'A Plante Verger 2', 'Témoin 12', 'Témoin 14', 'Témoin 16', 'Témoin 18', 'Témoin 20', 'Témoin 22', 'Témoin 24', 'Témoin 26', 'Témoin 28', 'Témoin 30'])
        ->and(array_keys($reponse->json('zone.0')))->toBe(['id', 'nom', 'code_court']);
});

it('mesure une ligne à son sommet médian, un polygone au centre de ses sommets', function () {
    $a = $this->jardins->a->id;
    app(JardinCourant::class)->positionner($a);
    $haie = Plante::factory()->create(['nom' => 'Haie du nord', 'zone_id' => $this->verger]);
    $pelouse = Plante::factory()->create(['nom' => 'Pelouse du sud', 'zone_id' => $this->verger]);
    // La haie part d'ICI, mais son sommet médian est à 3,5 pas. Le polygone a
    // trois sommets à 0,5 pas au sud et un à 4,5 pas : son centre est à
    // 1,5 pas ; compter deux fois le sommet de fermeture le mettrait à 2,1.
    situer($a, $haie->id, ['type' => 'LineString', 'coordinates' => [[ICI['lng'], ICI['lat']], [ICI['lng'], ICI['lat'] + 3.5 * PAS], [ICI['lng'], ICI['lat'] + 40 * PAS]]]);
    $s = fn (float $pas) => ICI['lat'] - $pas * PAS;
    situer($a, $pelouse->id, ['type' => 'Polygon', 'coordinates' => [[[ICI['lng'], $s(4.5)], [ICI['lng'] - 0.0001, $s(0.5)], [ICI['lng'], $s(0.5)], [ICI['lng'] + 0.0001, $s(0.5)], [ICI['lng'], $s(4.5)]]]]);

    expect(array_slice(noms(proches(ICI)->assertOk(), 'proches'), 0, 6))
        ->toBe(['Témoin 1', 'Pelouse du sud', 'Témoin 2', 'Témoin 3', 'Haie du nord', 'Témoin 4']);
});

it('cherche par nom ou par code court, parmi les proches comme dans la zone', function () {
    expect(noms(proches([...ICI, 'q' => 'témoin 3', 'zone' => $this->verger])->assertOk(), 'proches'))->toBe(['Témoin 3', 'Témoin 30'])
        ->and(noms(proches([...ICI, 'q' => strtolower($this->temoins[25]->code_court)])->assertOk(), 'proches'))->toBe(['Témoin 25'])
        ->and(noms(proches([...ICI, 'q' => 'Plante Verger', 'zone' => $this->verger]), 'zone'))->toBe(['A Plante Verger 1', 'A Plante Verger 2'])
        // `%` et `_` sont pris à la lettre : aucun nom n'en contient.
        ->and(proches([...ICI, 'q' => '%', 'zone' => $this->verger])->json())->toBe(['proches' => [], 'zone' => []])
        ->and(proches([...ICI, 'q' => 'Témoin _'])->json('proches'))->toBe([]);
});

it('ne présélectionne jamais une plante, même seule candidate', function () {
    $reponse = proches([...ICI, 'q' => 'Témoin 25'])->assertOk();

    expect($reponse->json('proches'))->toHaveCount(1)
        ->and(array_keys($reponse->json()))->toBe(['proches', 'zone'])
        ->and((string) $reponse->getContent())->not->toContain('choisi')->not->toContain('selection');
});

it('rend la zone et la recherche sans position, et refuse une position à moitié', function () {
    $reponse = proches(['zone' => $this->potager])->assertOk();
    expect($reponse->json('proches'))->toBe([])
        ->and(noms($reponse, 'zone'))->toHaveCount(16);

    proches(['lng' => ICI['lng']])->assertUnprocessable()->assertJsonValidationErrors('lat');
    proches(['lng' => 200, 'lat' => 45])->assertUnprocessable()->assertJsonValidationErrors('lng');
});

it('ne propose ni une plante archivée, ni une plante de B', function () {
    DB::table('plantes')->where('id', $this->temoins[1]->id)->update(['etat' => 'archivee', 'archivee_le' => now()]);
    $zoneB = DB::table('zones')->where('jardin_id', $this->jardins->b->id)->value('id');

    $reponse = proches([...ICI, 'zone' => $this->potager])->assertOk();
    expect(noms($reponse, 'proches')[0])->toBe('Témoin 2')
        ->and(noms($reponse, 'proches'))->not->toContain('Témoin 1')
        ->and(noms($reponse, 'zone'))->not->toContain('Témoin 1');

    $corps = (string) proches([...ICI, 'zone' => $zoneB, 'q' => 'Plante'])->assertOk()->getContent();
    expect($corps)->not->toContain(DeuxJardins::MARQUEUR)
        ->and(proches([...ICI, 'zone' => $zoneB])->json('zone'))->toBe([]);
});
