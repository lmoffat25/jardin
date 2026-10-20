<?php

// V101 (RG-15, RG-48, S18, critère 17, D-17) : une intervention sur une
// plante, saisie avec une position, garde cette position et sa précision. La
// géométrie de la plante n'est créée ou remplacée que si elle est absente ou en
// source `gps` moins précise ; jamais si elle est en source `plan` ou `manuel`.
// Une intervention sur plusieurs plantes ne modifie aucune géométrie.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-17');

/** La position que le téléphone envoie : un pommier du jardin, à $precision mètres près. */
function releve(float $precision): array
{
    return ['lng' => 4.83571, 'lat' => 45.76402, 'precision_m' => $precision];
}

/** Une taille du jour sur $plantes, saisie avec $position. */
function saisirAvecPosition(?array $position, string ...$plantes): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->membre)
        ->withSession(['jardin_id' => test()->jardin->id])
        ->postJson('/interventions', [
            'lot_saisie_id' => count($plantes) > 1 ? (string) Str::uuid() : null,
            'type_intervention_id' => RegleFactory::typeFourni('Taille d\'entretien/fructification'),
            'faite_le' => '2027-03-10',
            'position' => $position,
            'lignes' => array_map(fn (string $p) => ['id' => (string) Str::uuid(), 'plante_id' => $p], $plantes),
        ]);
}

/** Pose sur $plante une géométrie de $source, à $precision mètres près. */
function geometrie(string $plante, string $source, ?int $precision, array $coordonnees = [4.8, 45.7]): void
{
    DB::table('geometries')->insert([
        'id' => (string) Str::uuid(),
        'jardin_id' => test()->jardin->id,
        'plante_id' => $plante,
        'forme' => 'point',
        'coordonnees' => json_encode(['type' => 'Point', 'coordinates' => $coordonnees]),
        'precision_m' => $precision,
        'source' => $source,
        'relevee_le' => '2026-06-01 12:00:00+02',
    ]);
}

/** La géométrie de $plante : [source, précision, coordonnées], ou null. */
function geometrieDe(string $plante): ?array
{
    $g = DB::table('geometries')->where('plante_id', $plante)->first();

    return $g === null ? null : [$g->source, $g->precision_m, json_decode($g->coordonnees, true)];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->membre = Utilisateur::factory()->create();
    $this->jardin = Jardin::factory()->create();
    Adhesion::create(['utilisateur_id' => $this->membre->id, 'jardin_id' => $this->jardin->id, 'role' => 'membre']);
    app(JardinCourant::class)->positionner($this->jardin->id);
    $this->pommier = Plante::factory()->create(['nom' => 'Pommier du verger'])->id;
});

it('garde la position et sa précision dans l\'intervention, et crée un point gps sur une plante qui n\'en a pas', function () {
    saisirAvecPosition(releve(6.4), $this->pommier)->assertOk();

    expect(json_decode(DB::table('interventions')->value('position_capturee'), true))->toBe(['lat' => 45.76402, 'lng' => 4.83571, 'precision_m' => 6.4])
        ->and(geometrieDe($this->pommier))->toBe(['gps', 6, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]]]);

    $g = DB::table('geometries')->sole();
    expect($g->jardin_id)->toBe($this->jardin->id)
        ->and($g->forme)->toBe('point');
});

it('ne touche jamais une position tracée sur le plan ou saisie à la main', function (string $source) {
    geometrie($this->pommier, $source, null);
    $avant = geometrieDe($this->pommier);

    saisirAvecPosition(releve(1), $this->pommier)->assertOk();

    expect(geometrieDe($this->pommier))->toBe($avant)
        ->and(json_decode(DB::table('interventions')->value('position_capturee'), true)['precision_m'])->toEqual(1);
})->with(['plan', 'manuel']);

it('remplace un relevé gps par un plus précis, jamais par un moins précis', function (?int $ancienne, float $nouvelle, bool $remplace) {
    geometrie($this->pommier, 'gps', $ancienne);
    $avant = geometrieDe($this->pommier);

    saisirAvecPosition(releve($nouvelle), $this->pommier)->assertOk();

    expect(geometrieDe($this->pommier))->toBe($remplace
        ? ['gps', (int) round($nouvelle), ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]]]
        : $avant);
})->with([
    'plus précis' => [12, 4.0, true],
    'moins précis' => [4, 12.0, false],
    'aussi précis' => [4, 4.0, false],
    'ancien sans précision' => [null, 30.0, true],
]);

it('ne remplace pas un relevé gps par une position sans précision', function () {
    geometrie($this->pommier, 'gps', 12);
    $avant = geometrieDe($this->pommier);

    saisirAvecPosition(['lng' => 4.83571, 'lat' => 45.76402], $this->pommier)->assertOk();

    expect(geometrieDe($this->pommier))->toBe($avant);
});

it('ne modifie aucune géométrie pour une intervention sur six plantes', function () {
    $plantes = [$this->pommier, ...Plante::factory()->count(5)->create()->modelKeys()];
    geometrie($plantes[1], 'gps', 50);
    $avant = geometrieDe($plantes[1]);

    saisirAvecPosition(releve(2), ...$plantes)->assertOk();

    expect(DB::table('geometries')->count())->toBe(1)
        ->and(geometrieDe($plantes[1]))->toBe($avant)
        ->and(DB::table('interventions')->whereNotNull('position_capturee')->count())->toBe(6);
});

it('garde aussi la position d\'un « fait » depuis une échéance', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $regle = Regle::factory()->create(['plante_id' => $this->pommier]);
    Fenetre::factory()->for($regle)->create();
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    app()->forgetScopedInstances();

    $this->actingAs($this->membre)
        ->withSession(['jardin_id' => $this->jardin->id])
        ->postJson('/echeances/fait', ['id' => (string) Str::uuid(), 'regle_id' => $regle->id, 'due_le' => '2027-02-01', 'position' => releve(5)])
        ->assertOk();

    expect(json_decode(DB::table('interventions')->value('position_capturee'), true)['precision_m'])->toEqual(5)
        ->and(geometrieDe($this->pommier)[0])->toBe('gps');
});

it('ne place pas la plante sur un renvoi, même plus précis', function () {
    $id = (string) Str::uuid();
    $envoyer = fn (float $precision) => $this->actingAs($this->membre)
        ->withSession(['jardin_id' => $this->jardin->id])
        ->postJson('/interventions', [
            'type_intervention_id' => RegleFactory::typeFourni('Taille d\'entretien/fructification'),
            'faite_le' => '2027-03-10',
            'position' => releve($precision),
            'lignes' => [['id' => $id, 'plante_id' => $this->pommier]],
        ])
        ->assertOk();

    $envoyer(10);
    app()->forgetScopedInstances();
    $envoyer(2);

    expect(geometrieDe($this->pommier)[1])->toBe(10);
});
