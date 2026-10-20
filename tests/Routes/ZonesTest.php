<?php

// V82 (CDC §6 lot 0, P0, écran B3, D-65) : déclarer et renommer des zones. Un
// nom vide ou déjà pris dans le jardin est refusé sur le champ, rien ne
// change ; une zone de B est introuvable depuis A (404, B intacte), et un nom
// pris dans B reste libre dans A. La liste rend les zones actives par ordre
// alphabétique, accents et espaces comme le classement de la base (`en_US.utf8`).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    $this->withoutVite();
});

/** Le propriétaire de A, dans A, comme d'une requête neuve. */
function dansA(): Utilisateur
{
    app()->forgetScopedInstances();
    test()->withSession(['jardin_id' => test()->jardins->a->id]);

    return test()->jardins->proprietaireA;
}

/** @return array<string, string> nom => jardin_id, toutes les zones, hors filtre */
function zonesEnBase(): array
{
    return DB::table('zones')->orderBy('nom')->pluck('jardin_id', 'nom')->all();
}

it('liste les zones actives du jardin par ordre alphabétique', function () {
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    Zone::factory()->create(['nom' => 'Allée']);
    Zone::factory()->create(['nom' => 'Ancien potager', 'archivee_le' => now()]);
    Zone::factory()->create(['nom' => 'Zone du fond']);
    Zone::factory()->create(['nom' => 'Érablière']);

    $this->actingAs(dansA())->get('/zones')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Bureau/Zones')
            ->where('zones', fn ($zones) => collect($zones)->pluck('nom')->all() === ['Allée', 'A Potager', 'A Verger', 'Érablière', 'Zone du fond'])
            ->has('zones.0', fn (AssertableInertia $zone) => $zone->whereType('id', 'string')->where('nom', 'Allée')));
});

it('crée une zone dans le jardin courant', function () {
    $this->actingAs(dansA())->post('/zones', ['nom' => 'Verger du haut'])
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    expect(zonesEnBase()['Verger du haut'])->toBe($this->jardins->a->id);
});

it('rend la zone créée en JSON', function () {
    $reponse = $this->actingAs(dansA())->postJson('/zones', ['nom' => 'Verger du haut'])->assertCreated();

    expect($reponse->json())->toBe(['id' => DB::table('zones')->where('nom', 'Verger du haut')->value('id'), 'nom' => 'Verger du haut']);
});

it('renomme une zone du jardin', function () {
    $zone = DB::table('zones')->where('nom', 'A Potager')->sole();

    $this->actingAs(dansA())->put("/zones/{$zone->id}", ['nom' => 'Potager du bas'])
        ->assertStatus(302)
        ->assertSessionHasNoErrors();

    $apres = DB::table('zones')->where('id', $zone->id)->sole();
    expect([$apres->nom, $apres->jardin_id])->toBe(['Potager du bas', $this->jardins->a->id]);
});

it('garde son nom à une zone renommée à l\'identique', function () {
    $zone = DB::table('zones')->where('nom', 'A Potager')->sole();

    $this->actingAs(dansA())->putJson("/zones/{$zone->id}", ['nom' => 'A Potager'])->assertOk();
});

it('refuse un nom vide, trop long, ou déjà pris dans le jardin, et ne change rien', function (string $methode, string $nom) {
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    Zone::factory()->create(['nom' => 'Ancien potager', 'archivee_le' => now()]);
    $avant = zonesEnBase();
    $chemin = $methode === 'post' ? '/zones' : '/zones/'.DB::table('zones')->where('nom', 'A Potager')->value('id');

    $this->actingAs(dansA())->{$methode.'Json'}($chemin, ['nom' => $nom])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nom');

    expect(zonesEnBase())->toBe($avant);
})->with(['post', 'put'])->with([
    'vide' => '',
    'trop long' => str_repeat('a', 121),
    'pris' => 'A Verger',
    'pris par une zone archivée' => 'Ancien potager',
]);

it('ne trouve pas une zone de B depuis A, et laisse B intacte', function () {
    $zoneB = DB::table('zones')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    $this->actingAs(dansA())->putJson("/zones/{$zoneB}", ['nom' => 'Renommée depuis A'])->assertNotFound();
    $this->actingAs(dansA())->putJson('/zones/pas-un-uuid', ['nom' => 'Renommée depuis A'])->assertNotFound();

    expect(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});

it('accepte dans A un nom déjà pris dans B', function () {
    $nomB = DeuxJardins::MARQUEUR.' Potager';

    $this->actingAs(dansA())->postJson('/zones', ['nom' => $nomB])->assertCreated();

    expect(DB::table('zones')->where('nom', $nomB)->orderBy('jardin_id')->pluck('jardin_id')->sort()->values()->all())
        ->toBe(collect([$this->jardins->a->id, $this->jardins->b->id])->sort()->values()->all());
});
