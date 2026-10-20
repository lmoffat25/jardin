<?php

// V135 (RG-52, §8.6, D-66, D-86) : importé deux fois depuis le même témoin, le
// référentiel donne les mêmes lignes ; chaque commune porte son altitude, sa
// station, son écart, son décalage et son profil proposé. Une station à période
// incomplète n'est jamais retenue. Aucun appel réseau.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Src\Comptes\Climat\ProposerProfil;
use Tests\Support\TemoinClimat;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    Http::preventStrayRequests();
});

/** @return list<array<string, mixed>> */
function communes(): array
{
    return DB::table('communes')->orderBy('code_insee')->get()->map(fn ($c) => (array) $c)->all();
}

it('importe le témoin à l\'identique, deux fois de suite', function () {
    $dossier = TemoinClimat::dossier();

    $this->artisan('jardin:importer-communes', ['dossier' => $dossier])->assertSuccessful();
    $premiere = communes();
    $this->artisan('jardin:importer-communes', ['dossier' => $dossier])->assertSuccessful();

    expect($premiere)->toHaveCount(12)
        ->and(communes())->toBe($premiere);
});

it('calcule station, écart, décalage et profil de chaque commune', function () {
    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier()])->assertSuccessful();

    $lignes = DB::table('communes')
        ->whereIn('code_insee', ['99101', '99102', '99103', '99104', '99105', '99106', '99107'])
        ->orderBy('code_insee')
        ->get(['code_insee', 'altitude_m', 'station', 'ecart_c', 'decalage_jours', 'profil_propose_id'])
        ->map(fn ($c) => [$c->code_insee, $c->altitude_m, $c->station, (float) $c->ecart_c, $c->decalage_jours, $c->profil_propose_id])
        ->all();

    expect($lignes)->toBe([
        ['99101', 100, 'Station de la Plaine-Témoin', 0.0, 0, 3],
        ['99102', 50, 'Station du Sud-Témoin', 3.0, -18, 1],
        ['99103', 200, 'Station des Hauteurs-Témoin', 0.2, -1, 3],
        ['99104', 720, 'Station des Hauteurs-Témoin', -3.2, 19, 5],
        ['99105', 460, 'Station de la Plaine-Témoin', -2.3, 14, 4],
        ['99106', 910, 'Station des Hauteurs-Témoin', -4.4, 26, 5],
        ['99107', 327, 'Station du Sud-Témoin', 1.2, -7, 2],
    ]);
});

it('ne retient jamais une station à période incomplète', function () {
    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier()])->assertSuccessful();

    expect(DB::table('communes')->where('station', 'like', '%Incomplète%')->count())->toBe(0);
});

it('range le nom pour la recherche, sans accents ni casse, et garde les codes postaux', function () {
    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier()])->assertSuccessful();

    $ligne = DB::table('communes')->where('code_insee', '99112')->sole();
    expect([$ligne->nom, $ligne->nom_recherche, $ligne->codes_postaux])
        ->toBe(['Écluse-Saint-Témoin', 'ecluse saint temoin', '99520']);
});

it('n\'écrit rien quand la station de référence manque', function () {
    $sansReference = implode("\n", array_filter(
        explode("\n", TemoinClimat::STATIONS),
        fn (string $l) => ! str_starts_with($l, '99000001'),
    ));

    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier(stations: $sansReference)])
        ->assertFailed();

    expect(DB::table('communes')->count())->toBe(0);
});

it('n\'écrit rien quand une ligne est illisible', function () {
    $communes = TemoinClimat::COMMUNES."\n99113;Val-Illisible-Témoin;99900;pas un nombre;2.0;100";

    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier(communes: $communes)])
        ->assertFailed();

    expect(DB::table('communes')->count())->toBe(0);
});

it('garde une commune absente du fichier tant qu\'un jardin la désigne, et retire les autres', function () {
    $dossier = TemoinClimat::dossier();
    $this->artisan('jardin:importer-communes', ['dossier' => $dossier])->assertSuccessful();
    $jardin = nouveauJardin();
    DB::table('jardins')->where('id', $jardin)->update(['commune_insee' => '99111']);

    $sansDeux = implode("\n", array_filter(
        explode("\n", TemoinClimat::COMMUNES),
        fn (string $l) => ! str_starts_with($l, '99110') && ! str_starts_with($l, '99111'),
    ));
    $this->artisan('jardin:importer-communes', ['dossier' => TemoinClimat::dossier(communes: $sansDeux)])
        ->assertSuccessful();

    expect(DB::table('communes')->where('code_insee', '99110')->exists())->toBeFalse()
        ->and(DB::table('communes')->where('code_insee', '99111')->exists())->toBeTrue();
});

it('propose le profil le plus proche sous 600 m, le plus près de zéro à égalité', function (int $decalage, int $altitude, int $profil) {
    $profils = [1 => -15, 2 => -7, 3 => 0, 4 => 7, 5 => 21];
    $proposer = new ProposerProfil($profils, montagneId: 5, montagneAuDessusDe: 600, demanderDe: 400, demanderA: 800);

    expect($proposer->profil($decalage, $altitude))->toBe($profil);
})->with([
    'tempéré' => [3, 100, 3],
    'frais, loin de la montagne' => [30, 100, 4],
    'égalité entre très doux et doux' => [-11, 100, 2],
    'plus près de frais que de tempéré' => [4, 100, 4],
    'montagne à 601 m' => [0, 601, 5],
    'pas montagne à 600 m' => [0, 600, 3],
]);

it('tranche l\'égalité vers zéro, quel que soit l\'ordre des profils', function (array $profils) {
    $proposer = new ProposerProfil($profils, montagneId: 5, montagneAuDessusDe: 600, demanderDe: 400, demanderA: 800);

    expect($proposer->profil(-11, 100))->toBe(2);
})->with([
    'ordre d\'affichage' => [[1 => -15, 2 => -7, 3 => 0, 4 => 7, 5 => 21]],
    'ordre inverse' => [[5 => 21, 4 => 7, 3 => 0, 2 => -7, 1 => -15]],
]);

it('demande l\'altitude entre 400 et 800 m, bornes comprises', function (int $altitude, bool $demander) {
    $proposer = new ProposerProfil([3 => 0, 5 => 21], montagneId: 5, montagneAuDessusDe: 600, demanderDe: 400, demanderA: 800);

    expect($proposer->demanderAltitude($altitude))->toBe($demander);
})->with([[399, false], [400, true], [800, true], [801, false]]);
