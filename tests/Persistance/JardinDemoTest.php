<?php

// V125 (C4, chapitre 4 §5, chapitre 6 §3, critère 23 pour les données) :
// `make jardin-demo` crée un jardin fictif de 500 plantes, avec des points,
// des lignes et des polygones sur des coordonnées inventées, et deux ans
// d'historique ; relancée, elle remplace ce seul jardin ; elle refuse de
// tourner en production.

use Database\Seeders\DemonstrationSeeder;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Jardin;

uses(RefreshDatabase::class)->group('critere-23');

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-10-15 10:00:00', new DateTimeZone('Europe/Paris')));
});

/** L'identifiant du seul jardin de démonstration ; échoue s'il n'y en a pas exactement un. */
function jardinDeDemo(): string
{
    $ids = DB::table('jardins')->where('nom', DemonstrationSeeder::NOM)->pluck('id')->all();
    expect($ids)->toHaveCount(1);

    return (string) $ids[0];
}

/**
 * Toutes les paires [lng, lat] d'une géométrie GeoJSON.
 *
 * @return list<array{float, float}>
 */
function pairesDe(array $coordonnees): array
{
    if (is_numeric($coordonnees[0] ?? null)) {
        return [[(float) $coordonnees[0], (float) $coordonnees[1]]];
    }

    return array_merge(...array_map(fn (array $c) => pairesDe($c), $coordonnees));
}

it('crée un jardin fictif de 500 plantes, en points, lignes et polygones, avec deux ans d\'historique', function () {
    expect(Artisan::call('jardin:demo'))->toBe(0);

    $jardin = jardinDeDemo();
    $dans = fn (string $table) => DB::table($table)->where('jardin_id', $jardin);

    expect($dans('zones')->count())->toBe(8)
        ->and($dans('plantes')->count())->toBe(500)
        ->and($dans('plantes')->whereNull('espece_id')->count())->toBe(0)
        ->and($dans('plantes')->distinct()->count('espece_id'))->toBeGreaterThan(5)
        ->and($dans('regles')->distinct()->count('plante_id'))->toBe(500)
        ->and($dans('geometries')->count())->toBe(500)
        ->and($dans('geometries')->groupBy('forme')->orderBy('forme')->selectRaw('forme, count(*) as n')->pluck('n', 'forme')->all())
        ->toBe(['ligne' => 20, 'point' => 470, 'polygone' => 10]);

    // Des coordonnées inventées, toutes près du point fictif écrit dans le seeder (C4, RG-28).
    [$lng0, $lat0] = DemonstrationSeeder::CENTRE;
    foreach ($dans('geometries')->pluck('coordonnees') as $json) {
        $geojson = json_decode((string) $json, true);
        foreach (pairesDe($geojson['coordinates']) as [$lng, $lat]) {
            expect(abs($lng - $lng0))->toBeLessThan(0.01)->and(abs($lat - $lat0))->toBeLessThan(0.01);
        }
    }

    // Deux ans d'historique, rien dans le futur, et des échéances soldées par lui.
    $premiere = new DateTimeImmutable((string) $dans('interventions')->min('faite_le'));
    $derniere = new DateTimeImmutable((string) $dans('interventions')->max('faite_le'));
    expect($dans('interventions')->count())->toBeGreaterThan(1000)
        ->and($premiere >= new DateTimeImmutable('2024-10-15'))->toBeTrue()
        ->and($premiere->diff($derniere)->days)->toBeGreaterThan(600)
        ->and($derniere <= new DateTimeImmutable('2026-10-15'))->toBeTrue()
        ->and($dans('echeances')->where('etat', 'soldee')->count())->toBeGreaterThan(0);

    // Une plante sur cinq a manqué la dernière saison : le tableau de bord a de quoi montrer.
    $suivies = $dans('interventions')->where('faite_le', '>', '2025-10-15')->distinct()->count('plante_id');
    expect($suivies)->toBe(400);
});

it('relancée, remplace le seul jardin de démonstration', function () {
    $this->seed(ReferenceSeeder::class);
    $autre = Jardin::factory()->create(['nom' => 'Un autre jardin']);

    expect(Artisan::call('jardin:demo'))->toBe(0);
    $premier = jardinDeDemo();
    expect(Artisan::call('jardin:demo'))->toBe(0);
    $second = jardinDeDemo();

    expect($second)->not->toBe($premier)
        ->and(DB::table('plantes')->where('jardin_id', $premier)->count())->toBe(0)
        ->and(DB::table('plantes')->where('jardin_id', $second)->count())->toBe(500)
        ->and(DB::table('jardins')->where('id', $autre->id)->exists())->toBeTrue()
        ->and(DB::table('utilisateurs')->where('courriel', DemonstrationSeeder::COURRIEL)->count())->toBe(1);
});

it('refuse de tourner en production, sans rien écrire', function () {
    app()['env'] = 'production';

    expect(Artisan::call('jardin:demo'))->toBe(1)
        ->and(Artisan::output())->toContain('production')
        ->and(DB::table('jardins')->count())->toBe(0)
        ->and(DB::table('utilisateurs')->count())->toBe(0);
});
