<?php

// V108 (RG-48, S18, D-17) : pour chaque couple (source en place, source reçue)
// parmi `plan`, `manuel`, `gps`, la géométrie n'est remplacée que si la source
// reçue est au moins aussi fiable ; un relevé `gps` ne remplace un `gps` que
// plus précis. Un relevé `gps` moins fiable est conservé dans l'intervention,
// jamais dans la géométrie.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-17');

/** Les coordonnées de la géométrie en place, et celles de chaque envoi. */
const EN_PLACE = [4.80000, 45.70000];
const RECUE = [4.83571, 45.76402];

/** Pose en base, sans passer par l'application, une géométrie de $source. */
function fixerGeometrie(string $plante, string $source): void
{
    DB::table('geometries')->insert([
        'id' => (string) Str::uuid(),
        'jardin_id' => test()->jardin->id,
        'plante_id' => $plante,
        'forme' => 'point',
        'coordonnees' => json_encode(['type' => 'Point', 'coordinates' => EN_PLACE]),
        'precision_m' => $source === 'gps' ? 10 : null,
        'source' => $source,
        'relevee_le' => '2026-06-01 12:00:00+02',
    ]);
}

/** Envoie RECUE pour $plante en $source : `plan` et `manuel` par le tracé, `gps` par une intervention saisie sur place. */
function recevoir(string $plante, string $source, int $precision = 4): void
{
    app()->forgetScopedInstances();
    $requete = test()->actingAs(test()->proprietaire)->withSession(['jardin_id' => test()->jardin->id]);

    if ($source === 'gps') {
        $requete->postJson('/interventions', [
            'type_intervention_id' => RegleFactory::typeFourni('Taille d\'entretien/fructification'),
            'faite_le' => '2027-03-10',
            'position' => ['lng' => RECUE[0], 'lat' => RECUE[1], 'precision_m' => $precision],
            'lignes' => [['id' => (string) Str::uuid(), 'plante_id' => $plante]],
        ])->assertOk();

        return;
    }

    // Des coordonnées saisies sur une plante posée sur le plan sont refusées,
    // pas ignorées (Q-UX-23).
    $refus = $source === 'manuel' && DB::table('geometries')->where('plante_id', $plante)->value('source') === 'plan';
    $requete->putJson("/plantes/{$plante}/geometrie", ['type' => 'Point', 'coordinates' => RECUE, 'source' => $source])
        ->assertStatus($refus ? 422 : 200);
}

/** La géométrie de $plante relue : [source, coordonnées]. */
function etatGeometrie(string $plante): array
{
    $g = DB::table('geometries')->where('plante_id', $plante)->sole();

    return [$g->source, json_decode($g->coordonnees, true)['coordinates']];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->proprietaire = Utilisateur::factory()->create();
    $this->jardin = Jardin::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    Adhesion::create(['utilisateur_id' => $this->proprietaire->id, 'jardin_id' => $this->jardin->id, 'role' => 'proprietaire']);
    app(JardinCourant::class)->positionner($this->jardin->id);
    $this->pommier = Plante::factory()->create(['nom' => 'Pommier du verger'])->id;
});

it('ne remplace la géométrie que par une source au moins aussi fiable', function (string $enPlace, string $recue, bool $remplacee) {
    fixerGeometrie($this->pommier, $enPlace);

    recevoir($this->pommier, $recue);

    expect(etatGeometrie($this->pommier))->toBe($remplacee ? [$recue, RECUE] : [$enPlace, EN_PLACE])
        ->and(DB::table('geometries')->count())->toBe(1);
})->with([
    'plan, puis plan' => ['plan', 'plan', true],
    'plan, puis manuel' => ['plan', 'manuel', false],
    'plan, puis gps' => ['plan', 'gps', false],
    'manuel, puis plan' => ['manuel', 'plan', true],
    'manuel, puis manuel' => ['manuel', 'manuel', true],
    'manuel, puis gps' => ['manuel', 'gps', false],
    'gps, puis plan' => ['gps', 'plan', true],
    'gps, puis manuel' => ['gps', 'manuel', true],
    'gps, puis gps plus précis' => ['gps', 'gps', true],
]);

it('ne remplace pas un gps par un gps moins précis, et le garde dans l\'intervention', function () {
    fixerGeometrie($this->pommier, 'gps');

    recevoir($this->pommier, 'gps', precision: 25);

    expect(etatGeometrie($this->pommier))->toBe(['gps', EN_PLACE])
        ->and(json_decode(DB::table('interventions')->value('position_capturee'), true))
        ->toEqual(['lng' => RECUE[0], 'lat' => RECUE[1], 'precision_m' => 25]);
});

it('garde dans l\'intervention le relevé gps qu\'une position du plan a écarté', function () {
    fixerGeometrie($this->pommier, 'plan');

    recevoir($this->pommier, 'gps');

    expect(etatGeometrie($this->pommier))->toBe(['plan', EN_PLACE])
        ->and(json_decode(DB::table('interventions')->value('position_capturee'), true))
        ->toEqual(['lng' => RECUE[0], 'lat' => RECUE[1], 'precision_m' => 4]);
});

it('efface la précision d\'un relevé gps remplacé par le plan, et date le tracé', function () {
    fixerGeometrie($this->pommier, 'gps');

    recevoir($this->pommier, 'plan');

    $g = DB::table('geometries')->sole();
    expect($g->precision_m)->toBeNull()
        ->and((new DateTimeImmutable($g->relevee_le))->getTimestamp())->toBe(now()->getTimestamp());
});
