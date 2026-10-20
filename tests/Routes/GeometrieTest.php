<?php

// V107 (RG-14, RG-48, critère 17, D-59, écrans B13 et B6) : un point, une ligne
// ou un polygone GeoJSON en WGS 84 enregistre la géométrie de la plante en
// source `plan` ; des coordonnées saisies l'enregistrent en source `manuel`.
// GeoJSON invalide : erreur, la géométrie précédente reste. Plante de B : 404,
// sa géométrie intacte. En base, une seule géométrie par plante, avec sa
// source et `relevee_le`.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-17');

/** Un PUT de géométrie sur $plante, par $u dans le jardin A. */
function poser(string $plante, array $charge, ?Utilisateur $u = null): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u ?? test()->jardins->proprietaireA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->putJson("/plantes/{$plante}/geometrie", $charge);
}

/** La géométrie de $plante relue en base, hors de tout filtre : [forme, source, précision, coordonnées, relevée le], ou null. */
function geometrieRelue(string $plante): ?array
{
    $g = DB::table('geometries')->where('plante_id', $plante)->first();

    return $g === null ? null : [$g->forme, $g->source, $g->precision_m, json_decode($g->coordonnees, true), $g->relevee_le];
}

/** Un polygone fermé témoin : un carré d'une dizaine de mètres. */
function carre(): array
{
    return [[[4.8350, 45.7640], [4.8351, 45.7640], [4.8351, 45.7641], [4.8350, 45.7641], [4.8350, 45.7640]]];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;
    $this->plante = DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id');
});

it('enregistre un point, une ligne ou un polygone en source plan', function (string $type, array $coordonnees, string $forme) {
    poser($this->plante, ['type' => $type, 'coordinates' => $coordonnees])->assertOk()
        ->assertJson(['id' => $this->plante, 'geometrie' => ['type' => $type, 'coordinates' => $coordonnees, 'source' => 'plan']]);

    [$formeLue, $source, $precision, $lues, $relevee] = geometrieRelue($this->plante);
    expect([$formeLue, $source, $precision, $lues])->toBe([$forme, 'plan', null, ['type' => $type, 'coordinates' => $coordonnees]])
        ->and((new DateTimeImmutable($relevee))->getTimestamp())->toBe(now()->getTimestamp())
        ->and(DB::table('geometries')->where('plante_id', $this->plante)->value('jardin_id'))->toBe($this->jardins->a->id);
})->with([
    'point' => ['Point', [4.83571, 45.76402], 'point'],
    'haie en ligne' => ['LineString', [[4.8350, 45.7640], [4.8352, 45.7641], [4.8354, 45.7640]], 'ligne'],
    'pelouse en polygone' => ['Polygon', carre(), 'polygone'],
]);

it('enregistre des coordonnées saisies en source manuel', function () {
    poser($this->plante, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402], 'source' => 'manuel'])->assertOk();

    expect(geometrieRelue($this->plante)[1])->toBe('manuel');
});

it('refuse des coordonnées saisies sur une plante posée sur le plan, sans rien changer (Q-UX-23)', function () {
    poser($this->plante, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]])->assertOk();
    $avant = geometrieRelue($this->plante);

    poser($this->plante, ['type' => 'Point', 'coordinates' => [4.8, 45.7], 'source' => 'manuel'])
        ->assertUnprocessable()->assertJsonValidationErrors(['source' => 'déplacez-la sur le plan']);

    expect(geometrieRelue($this->plante))->toBe($avant);
});

it('garde une seule géométrie par plante, la dernière posée', function () {
    poser($this->plante, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]])->assertOk();
    poser($this->plante, ['type' => 'Polygon', 'coordinates' => carre()])->assertOk();

    expect(DB::table('geometries')->where('plante_id', $this->plante)->count())->toBe(1)
        ->and(geometrieRelue($this->plante)[0])->toBe('polygone');
});

it('refuse un GeoJSON invalide et garde la géométrie précédente', function (array $charge, string $champ) {
    poser($this->plante, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]])->assertOk();
    $avant = geometrieRelue($this->plante);
    $this->travelTo(now()->addHour());

    poser($this->plante, $charge)->assertUnprocessable()->assertJsonValidationErrors($champ);

    expect(geometrieRelue($this->plante))->toBe($avant);
})->with([
    'type inconnu' => [['type' => 'MultiPoint', 'coordinates' => [[4.8, 45.7]]], 'type'],
    'type absent' => [['coordinates' => [4.8, 45.7]], 'type'],
    'longitude hors bornes' => [['type' => 'Point', 'coordinates' => [181, 45.7]], 'coordinates'],
    'latitude hors bornes' => [['type' => 'Point', 'coordinates' => [4.8, -90.5]], 'coordinates'],
    'coordonnée non numérique' => [['type' => 'Point', 'coordinates' => ['4.8', 45.7]], 'coordinates'],
    'point à trois nombres' => [['type' => 'Point', 'coordinates' => [4.8, 45.7, 12]], 'coordinates'],
    'ligne d\'un seul point' => [['type' => 'LineString', 'coordinates' => [[4.8, 45.7]]], 'coordinates'],
    'polygone non fermé' => [['type' => 'Polygon', 'coordinates' => [[[4.8350, 45.7640], [4.8351, 45.7640], [4.8351, 45.7641], [4.8350, 45.7641]]]], 'coordinates'],
    'polygone de trois points' => [['type' => 'Polygon', 'coordinates' => [[[4.8350, 45.7640], [4.8351, 45.7640], [4.8350, 45.7640]]]], 'coordinates'],
    'polygone troué' => [['type' => 'Polygon', 'coordinates' => [...carre(), ...carre()]], 'coordinates'],
    'source gps' => [['type' => 'Point', 'coordinates' => [4.8, 45.7], 'source' => 'gps'], 'source'],
]);

it('rend 404 pour une plante de B, et laisse sa géométrie intacte', function () {
    $planteB = DB::table('plantes')->where('jardin_id', $this->jardins->b->id)->value('id');
    DB::table('geometries')->insert([
        'id' => (string) Str::uuid(), 'jardin_id' => $this->jardins->b->id, 'plante_id' => $planteB, 'forme' => 'point',
        'coordonnees' => json_encode(['type' => 'Point', 'coordinates' => [2.35, 48.85]]), 'source' => 'gps', 'relevee_le' => '2026-06-01 12:00:00+02',
    ]);
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    poser($planteB, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]])->assertNotFound();

    expect(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant)
        ->and(DB::table('geometries')->where('jardin_id', $this->jardins->a->id)->exists())->toBeFalse();
});

it('réserve le tracé au propriétaire', function () {
    poser($this->plante, ['type' => 'Point', 'coordinates' => [4.83571, 45.76402]], $this->jardins->membreA)->assertForbidden();

    expect(geometrieRelue($this->plante))->toBeNull();
});
