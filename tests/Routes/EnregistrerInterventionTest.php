<?php

// V93 (RG-05, CDC §3.8, écrans T2, T3, T4, B8) : un identifiant fourni par le
// client, une plante, un type, une date, et facultativement une note et une
// durée, créent une intervention dont l'auteur est l'utilisateur connecté.
// Type inconnu, plante absente, date invalide, plante ou type de B : erreur,
// rien n'est créé. En base : le `jardin_id` de A, `faite_le` sans heure,
// `saisie_le` posé par le serveur.
//
// V97 (H9, RG-05) : jamais de refus métier. Une plante archivée entre-temps,
// une échéance déjà soldée, une date passée de plusieurs mois sont acceptées.

use Carbon\CarbonImmutable;
use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;

    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
    $this->planteB = DB::table('plantes')->where('nom', DeuxJardins::MARQUEUR.' Plante Potager 1')->value('id');
    $this->taille = RegleFactory::typeFourni('Taille d\'entretien/fructification');
    $this->typeB = DB::table('types_intervention')->insertGetId(
        ['jardin_id' => $this->jardins->b->id, 'libelle' => 'Type propre à B', 'famille' => 'entretien'],
    );
});

/**
 * Une saisie d'une ligne sur la plante de A, complétée par $surcharge.
 *
 * @param  array<string, mixed>  $surcharge
 * @return array<string, mixed>
 */
function saisieSimple(array $surcharge = []): array
{
    return [
        'lot_saisie_id' => null,
        'type_intervention_id' => test()->taille,
        'faite_le' => '2026-08-15',
        'note' => null,
        'duree_min' => null,
        'lignes' => [['id' => (string) Str::uuid(), 'plante_id' => test()->plante]],
        ...$surcharge,
    ];
}

/** @param  array<string, mixed>  $charge */
function enregistrerDansA(Utilisateur $u, array $charge): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->postJson('/interventions', $charge);
}

/** Le nombre d'interventions, tous jardins confondus. */
function interventionsEnBase(): int
{
    return DB::table('interventions')->count();
}

it('enregistre une intervention dont l\'auteur est l\'utilisateur connecté', function () {
    $id = (string) Str::uuid();

    $reponse = enregistrerDansA($this->jardins->membreA, saisieSimple([
        'note' => 'Bois mort retiré.',
        'duree_min' => 25,
        'position' => ['lng' => 2.35, 'lat' => 48.85, 'precision_m' => 8],
        'lignes' => [['id' => $id, 'plante_id' => $this->plante]],
    ]));

    $reponse->assertOk()->assertExactJson(['interventions' => [[
        'id' => $id,
        'plante_id' => $this->plante,
        'type_intervention_id' => $this->taille,
        'faite_le' => '2026-08-15',
        'note' => 'Bois mort retiré.',
        'duree_min' => 25,
        'auteur_id' => $this->jardins->membreA->id,
    ]]]);

    $ligne = DB::table('interventions')->where('id', $id)->sole();
    expect($ligne->jardin_id)->toBe($this->jardins->a->id)
        ->and($ligne->auteur_id)->toBe($this->jardins->membreA->id)
        ->and($ligne->faite_le)->toBe('2026-08-15')
        ->and($ligne->lot_saisie_id)->toBeNull()
        ->and(json_decode($ligne->position_capturee, true))->toBe(['lat' => 48.85, 'lng' => 2.35, 'precision_m' => 8])
        ->and(CarbonImmutable::parse($ligne->saisie_le)->equalTo(CarbonImmutable::parse('2026-09-01 10:00:00', 'Europe/Paris')))->toBeTrue();
});

it('pose saisie_le au serveur, même si le client l\'envoie', function () {
    $id = (string) Str::uuid();

    enregistrerDansA($this->jardins->proprietaireA, saisieSimple([
        'saisie_le' => '2020-01-01 00:00:00',
        'auteur_id' => $this->jardins->proprietaireB->id,
        'jardin_id' => $this->jardins->b->id,
        'lignes' => [['id' => $id, 'plante_id' => $this->plante]],
    ]))->assertOk();

    $ligne = DB::table('interventions')->where('id', $id)->sole();
    expect($ligne->jardin_id)->toBe($this->jardins->a->id)
        ->and($ligne->auteur_id)->toBe($this->jardins->proprietaireA->id)
        ->and(CarbonImmutable::parse($ligne->saisie_le)->year)->toBe(2026);
});

it('refuse une saisie invalide, et ne crée rien', function (Closure $surcharge, string $champ) {
    enregistrerDansA($this->jardins->proprietaireA, saisieSimple($surcharge()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$champ]);

    expect(interventionsEnBase())->toBe(0);
})->with([
    'type inconnu' => [fn () => ['type_intervention_id' => 999999], 'type_intervention_id'],
    'type absent' => [fn () => ['type_intervention_id' => null], 'type_intervention_id'],
    'plante absente' => [fn () => ['lignes' => [['id' => (string) Str::uuid(), 'plante_id' => (string) Str::uuid()]]], 'lignes.0'],
    'date impossible' => [fn () => ['faite_le' => '2026-02-30'], 'faite_le'],
    'date au format français' => [fn () => ['faite_le' => '15/08/2026'], 'faite_le'],
    'date avec heure' => [fn () => ['faite_le' => '2026-08-15 10:00:00'], 'faite_le'],
    'identifiant qui n\'est pas un uuid' => [fn () => ['lignes' => [['id' => '42', 'plante_id' => test()->plante]]], 'lignes.0.id'],
    'aucune ligne' => [fn () => ['lignes' => []], 'lignes'],
    'durée nulle' => [fn () => ['duree_min' => 0], 'duree_min'],
    'durée de plus d\'un jour' => [fn () => ['duree_min' => 1441], 'duree_min'],
    'note trop longue' => [fn () => ['note' => str_repeat('a', 2001)], 'note'],
    'plusieurs plantes sans lot' => [fn () => ['lignes' => [
        ['id' => (string) Str::uuid(), 'plante_id' => test()->plante],
        ['id' => (string) Str::uuid(), 'plante_id' => DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id')],
    ]], 'lot_saisie_id'],
    'deux fois le même identifiant' => [fn () => ['lot_saisie_id' => (string) Str::uuid(), 'lignes' => [
        ['id' => '6f1c1c2e-0000-4000-8000-000000000001', 'plante_id' => test()->plante],
        ['id' => '6f1c1c2e-0000-4000-8000-000000000001', 'plante_id' => DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id')],
    ]], 'lignes.1.id'],
]);

it('refuse une plante de B comme une plante absente, et ne crée rien', function () {
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    enregistrerDansA($this->jardins->proprietaireA, saisieSimple(['lignes' => [['id' => (string) Str::uuid(), 'plante_id' => $this->planteB]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lignes.0' => 'Plante introuvable dans ce jardin.']);

    expect(interventionsEnBase())->toBe(0)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});

it('refuse un type propre à B comme un type inconnu, et ne crée rien', function () {
    $inconnu = enregistrerDansA($this->jardins->proprietaireA, saisieSimple(['type_intervention_id' => 999999]));
    $deB = enregistrerDansA($this->jardins->proprietaireA, saisieSimple(['type_intervention_id' => $this->typeB]));

    $deB->assertUnprocessable()->assertJsonValidationErrors(['type_intervention_id' => 'Type d\'intervention inconnu.']);
    expect($deB->json())->toBe($inconnu->json())
        ->and(interventionsEnBase())->toBe(0);
});

it('accepte une plante archivée entre-temps', function () {
    DB::table('plantes')->where('id', $this->plante)->update(['etat' => 'archivee', 'archivee_le' => '2026-08-20 09:00:00', 'motif_archivage' => 'Arrachée']);

    enregistrerDansA($this->jardins->membreA, saisieSimple())->assertOk();

    expect(DB::table('interventions')->where('plante_id', $this->plante)->count())->toBe(1);
});

it('accepte une intervention sur une échéance déjà soldée', function () {
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $regle = Regle::factory()->create(['plante_id' => $this->plante]);
    Fenetre::factory()->for($regle)->create();
    EcheanceMaterialisee::factory()->for($regle)->create(['due_le' => '2026-08-01', 'etat' => 'soldee']);

    enregistrerDansA($this->jardins->membreA, saisieSimple(['faite_le' => '2026-08-02']))->assertOk();

    expect(DB::table('interventions')->where('plante_id', $this->plante)->count())->toBe(1);
});

it('accepte une date passée de plusieurs mois', function () {
    enregistrerDansA($this->jardins->membreA, saisieSimple(['faite_le' => '2026-02-03']))
        ->assertOk()
        ->assertJsonPath('interventions.0.faite_le', '2026-02-03');
});

it('accepte des identifiants en majuscules, et les rend comme la base les garde', function () {
    $id = (string) Str::uuid();

    enregistrerDansA($this->jardins->membreA, saisieSimple(['lignes' => [['id' => strtoupper($id), 'plante_id' => strtoupper($this->plante)]]]))
        ->assertOk()
        ->assertJsonPath('interventions.0.id', $id)
        ->assertJsonPath('interventions.0.plante_id', $this->plante);
});
