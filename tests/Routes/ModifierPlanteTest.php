<?php

// V132 (CDC §6 lot 0, RG-13, D-54, écran B6) : nom, zone, repère, variété,
// date de plantation, âge estimé et notes se modifient ; le code court, les
// règles et la géométrie ne changent pas. Un nom déjà pris dans la zone
// d'arrivée est refusé (422), rien ne change ; un membre reçoit 403 ; une
// plante de B est introuvable depuis A (404) et une zone de B refusée (422),
// B intact.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;

    // Une plante de A, avec une règle, sa fenêtre, et une géométrie.
    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->sole();
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    Fenetre::factory()->for(Regle::factory()->state(['plante_id' => $this->plante->id]))->create();
    DB::table('geometries')->insert([
        'id' => '00000000-0000-4000-8000-000000000132',
        'jardin_id' => $this->jardins->a->id,
        'plante_id' => $this->plante->id,
        'forme' => 'point',
        'coordonnees' => json_encode(['type' => 'Point', 'coordinates' => [2.0, 45.0]]),
        'source' => 'manuel',
        'relevee_le' => '2026-03-01 10:00:00+00',
    ]);
    $this->verger = DB::table('zones')->where('nom', 'A Verger')->value('id');
    app()->forgetScopedInstances();
});

/** Un utilisateur de A, dans A, comme d'une requête neuve. */
function modifierDansA(Utilisateur $utilisateur): Utilisateur
{
    app()->forgetScopedInstances();
    test()->withSession(['jardin_id' => test()->jardins->a->id]);

    return $utilisateur;
}

/** @return array<string, mixed> */
function modificationValide(string $zoneId): array
{
    return [
        'nom' => 'Cerisier du haut',
        'zone_id' => $zoneId,
        'repere' => 'Près du portail',
        'variete' => 'Burlat',
        'plantee_le' => '2019-11-15',
        'age_estime_ans' => 7,
        'notes' => 'Greffé sur merisier.',
    ];
}

/** @return array{regles: string, fenetres: string, geometries: string} */
function reglesEtGeometrie(string $planteId): array
{
    $regles = DB::table('regles')->where('plante_id', $planteId)->orderBy('id')->get();

    return [
        'regles' => md5((string) json_encode($regles)),
        'fenetres' => md5((string) json_encode(DB::table('fenetres')->whereIn('regle_id', $regles->pluck('id'))->orderBy('id')->get())),
        'geometries' => md5((string) json_encode(DB::table('geometries')->where('plante_id', $planteId)->get())),
    ];
}

it('modifie la plante, sans toucher à son code court, ses règles ni sa géométrie', function () {
    $avant = reglesEtGeometrie($this->plante->id);
    expect($avant['regles'])->not->toBe(md5('[]'));

    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->put("/plantes/{$this->plante->id}", modificationValide($this->verger))
        ->assertRedirect("/plantes/{$this->plante->id}")
        ->assertSessionHasNoErrors();

    $apres = DB::table('plantes')->where('id', $this->plante->id)->sole();
    expect([$apres->nom, $apres->zone_id, $apres->repere, $apres->variete, $apres->plantee_le, $apres->age_estime_ans, $apres->notes])
        ->toBe(['Cerisier du haut', $this->verger, 'Près du portail', 'Burlat', '2019-11-15', 7, 'Greffé sur merisier.'])
        ->and([$apres->code_court, $apres->jardin_id, $apres->etat])->toBe([$this->plante->code_court, $this->jardins->a->id, 'active'])
        ->and(reglesEtGeometrie($this->plante->id))->toBe($avant);
});

it('efface un champ facultatif envoyé vide, et laisse celui qui n\'est pas envoyé', function () {
    DB::table('plantes')->where('id', $this->plante->id)->update(['repere' => 'Au fond', 'notes' => 'Note gardée.']);

    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson("/plantes/{$this->plante->id}", ['nom' => $this->plante->nom, 'zone_id' => $this->plante->zone_id, 'repere' => null])
        ->assertOk();

    $apres = DB::table('plantes')->where('id', $this->plante->id)->sole();
    expect([$apres->repere, $apres->notes])->toBe([null, 'Note gardée.']);
});

it('garde son nom à une plante qui ne change pas de zone', function () {
    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson("/plantes/{$this->plante->id}", ['nom' => $this->plante->nom, 'zone_id' => $this->plante->zone_id, 'notes' => 'Une note.'])
        ->assertOk()
        ->assertJson(['id' => $this->plante->id, 'code_court' => $this->plante->code_court]);
});

it('refuse un nom déjà pris dans la zone d\'arrivée, et ne change rien', function () {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson("/plantes/{$this->plante->id}", [...modificationValide($this->verger), 'nom' => 'A Plante Verger 1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nom' => 'Une plante de cette zone porte déjà ce nom.']);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('refuse une saisie invalide, et ne change rien', function (array $saisie, string $champ) {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson("/plantes/{$this->plante->id}", [...modificationValide($this->verger), ...$saisie])
        ->assertStatus(422)
        ->assertJsonValidationErrors($champ);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
})->with([
    'nom vide' => [['nom' => ''], 'nom'],
    'sans zone' => [['zone_id' => null], 'zone_id'],
    'date mal écrite' => [['plantee_le' => '15/11/2019'], 'plantee_le'],
    'âge négatif' => [['age_estime_ans' => -1], 'age_estime_ans'],
]);

it('refuse la modification à un membre', function () {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(modifierDansA($this->jardins->membreA))
        ->putJson("/plantes/{$this->plante->id}", modificationValide($this->verger))
        ->assertForbidden();

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('ne trouve pas une plante de B depuis A, et laisse B intact', function () {
    $planteB = DB::table('plantes')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson("/plantes/{$planteB}", modificationValide($this->verger))
        ->assertNotFound();
    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/pas-un-uuid', modificationValide($this->verger))
        ->assertNotFound();

    expect(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});

it('refuse une zone de B, et laisse A et B intacts', function () {
    $zoneB = DB::table('zones')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avant = [DeuxJardins::empreinte($this->jardins->a->id), DeuxJardins::empreinte($this->jardins->b->id)];

    $this->actingAs(modifierDansA($this->jardins->proprietaireA))
        ->putJson("/plantes/{$this->plante->id}", modificationValide($zoneB))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zone_id' => 'Zone introuvable dans ce jardin.']);

    expect([DeuxJardins::empreinte($this->jardins->a->id), DeuxJardins::empreinte($this->jardins->b->id)])->toBe($avant);
});
