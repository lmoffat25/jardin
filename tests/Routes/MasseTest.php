<?php

// V90 (CDC §8.7, écran B5) : déplacer N plantes vers une autre zone, ou changer
// leur repère, en une demande. Un nom qui deviendrait double dans la zone
// d'arrivée, ou un identifiant de B parmi les N, et rien ne change, ni dans A
// ni dans B ; un membre reçoit 403.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;

    // Le potager de A reçoit quatre plantes de plus : cinq à déplacer.
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $this->potager = DB::table('zones')->where('nom', 'A Potager')->value('id');
    $this->verger = DB::table('zones')->where('nom', 'A Verger')->value('id');
    foreach (['Tomate', 'Courgette', 'Fève', 'Poireau'] as $nom) {
        Plante::factory()->create(['zone_id' => $this->potager, 'nom' => $nom]);
    }
    $this->ids = DB::table('plantes')->where('zone_id', $this->potager)->orderBy('nom')->pluck('id')->all();
    app()->forgetScopedInstances();
});

/** $utilisateur, dans A, comme d'une requête neuve. */
function enMasseDansA(Utilisateur $utilisateur): Utilisateur
{
    app()->forgetScopedInstances();
    test()->withSession(['jardin_id' => test()->jardins->a->id]);

    return $utilisateur;
}

/**
 * @param  list<string>  $ids
 * @return array<string, string|null> id => valeur de $colonne, pour $ids
 */
function colonneDes(array $ids, string $colonne): array
{
    return DB::table('plantes')->whereIn('id', $ids)->orderBy('id')->pluck($colonne, 'id')->all();
}

it('déplace N plantes vers une autre zone en une demande', function () {
    expect($this->ids)->toHaveCount(5);

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => $this->ids, 'zone_id' => $this->verger])
        ->assertOk()
        ->assertJson(['modifiees' => 5]);

    expect(array_values(array_unique(colonneDes($this->ids, 'zone_id'))))->toBe([$this->verger])
        ->and(DB::table('plantes')->where('zone_id', $this->potager)->count())->toBe(0);
});

it('accepte dans le lot une plante déjà dans la zone d\'arrivée', function () {
    $dejaLa = DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id');

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => [...$this->ids, $dejaLa], 'zone_id' => $this->verger])
        ->assertOk()
        ->assertJson(['modifiees' => 6]);

    expect(array_values(array_unique(colonneDes([...$this->ids, $dejaLa], 'zone_id'))))->toBe([$this->verger]);
});

it('change le repère de N plantes, et l\'efface envoyé vide', function () {
    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->put('/plantes/lot', ['ids' => $this->ids, 'repere' => 'Rang du milieu'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(array_values(array_unique(colonneDes($this->ids, 'repere'))))->toBe(['Rang du milieu'])
        ->and(array_values(array_unique(colonneDes($this->ids, 'zone_id'))))->toBe([$this->potager]);

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => array_slice($this->ids, 0, 2), 'repere' => null])
        ->assertOk()
        ->assertJson(['modifiees' => 2]);

    expect(array_values(colonneDes(array_slice($this->ids, 0, 2), 'repere')))->toBe([null, null])
        ->and(DB::table('plantes')->where('repere', 'Rang du milieu')->count())->toBe(3);
});

it('refuse un nom qui deviendrait double dans la zone d\'arrivée, et ne change rien', function () {
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    Plante::factory()->create(['zone_id' => $this->verger, 'nom' => 'Fève']);
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => $this->ids, 'zone_id' => $this->verger, 'repere' => 'Au fond'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids' => 'Une plante de la zone d\'arrivée porte déjà ce nom : Fève.']);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('refuse deux plantes du même nom envoyées vers la même zone', function () {
    // Une tomate de la serre et celle du potager ne peuvent arriver ensemble au verger.
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $troisieme = Zone::factory()->create(['nom' => 'A Serre'])->id;
    $homonyme = Plante::factory()->create(['zone_id' => $troisieme, 'nom' => 'Tomate'])->id;
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => [...$this->ids, $homonyme], 'zone_id' => $this->verger])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids' => 'Deux plantes déplacées portent le même nom : Tomate.']);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('ne change rien quand un identifiant de B est parmi les N, ni dans A ni dans B', function () {
    $planteB = DB::table('plantes')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avantA = DeuxJardins::empreinte($this->jardins->a->id);
    $avantB = DeuxJardins::empreinte($this->jardins->b->id);

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => [...$this->ids, $planteB], 'repere' => 'Partout'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids' => 'Plante introuvable dans ce jardin.']);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avantA)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avantB);
});

it('refuse une zone de B, et ne change rien', function () {
    $zoneB = DB::table('zones')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avantA = DeuxJardins::empreinte($this->jardins->a->id);
    $avantB = DeuxJardins::empreinte($this->jardins->b->id);

    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', ['ids' => $this->ids, 'zone_id' => $zoneB])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zone_id' => 'Zone introuvable dans ce jardin.']);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avantA)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avantB);
});

it('refuse une demande sans rien à changer ou sans plante', function (array $charge, string $champ) {
    $this->actingAs(enMasseDansA($this->jardins->proprietaireA))
        ->putJson('/plantes/lot', $charge)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$champ]);
})->with([
    'ni zone ni repère' => [['ids' => ['00000000-0000-4000-8000-000000000090']], 'zone_id'],
    'aucune plante' => [['ids' => [], 'repere' => 'X'], 'ids'],
    'un identifiant en double' => [['ids' => ['00000000-0000-4000-8000-000000000090', '00000000-0000-4000-8000-000000000090'], 'repere' => 'X'], 'ids.0'],
]);

it('refuse la modification en masse à un membre', function () {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(enMasseDansA($this->jardins->membreA))
        ->putJson('/plantes/lot', ['ids' => $this->ids, 'repere' => 'Au fond'])
        ->assertForbidden();

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});
