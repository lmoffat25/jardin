<?php

// V99 (critère 7, RG-05) : le lundi, une taille datée du samedi précédent
// solde l'échéance dont la fenêtre contient ce samedi, et la prochaine échéance
// de la règle est recalculée à partir du samedi. V54 côté écriture (H9) : une
// intervention hors de toute fenêtre ne solde rien.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-07');

/** Une saisie de $type datée du $faiteLe sur $plantes, par le propriétaire du jardin. */
function saisirLe(Utilisateur $u, Jardin $jardin, int $type, string $faiteLe, string ...$plantes): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u)
        ->withSession(['jardin_id' => $jardin->id])
        ->postJson('/interventions', [
            'lot_saisie_id' => count($plantes) > 1 ? (string) Str::uuid() : null,
            'type_intervention_id' => $type,
            'faite_le' => $faiteLe,
            'lignes' => array_map(fn (string $p) => ['id' => (string) Str::uuid(), 'plante_id' => $p], $plantes),
        ]);
}

/** La prochaine échéance calculée de $regle, aujourd'hui : [état, due_le]. */
function prochaineDe(Jardin $jardin, Regle $regle): array
{
    app(JardinCourant::class)->positionner($jardin->id);
    $echeance = app(CalculEcheances::class)->pourJardin($jardin)
        ->filter(fn (EcheanceCalculee $e) => $e->regle->id === $regle->id)
        ->last();

    return [$echeance?->echeance->etat->value, $echeance?->echeance->dueLe->format('Y-m-d')];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    // Règles posées à l'automne 2026, dans un jardin tempéré (aucun décalage).
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->proprietaire = Utilisateur::factory()->create();
    $this->jardin = Jardin::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    Adhesion::create(['utilisateur_id' => $this->proprietaire->id, 'jardin_id' => $this->jardin->id, 'role' => 'proprietaire']);
    app(JardinCourant::class)->positionner($this->jardin->id);
    $this->pommier = Plante::factory()->create(['nom' => 'Pommier du verger']);
    $this->taille = Regle::factory()->create(['plante_id' => $this->pommier->id]);
    Fenetre::factory()->for($this->taille)->create();
    $this->typeTaille = RegleFactory::typeFourni('Taille d\'entretien/fructification');

    // Le lundi 08/03/2027 ; le samedi précédent est le 06/03.
    $this->travelTo(new DateTimeImmutable('2027-03-08 09:00:00', new DateTimeZone('Europe/Paris')));
});

it('solde, le lundi, la fenêtre qui contient la taille du samedi, et recalcule la suite', function () {
    expect(prochaineDe($this->jardin, $this->taille))->toBe(['ouverte', '2027-02-01']);

    saisirLe($this->proprietaire, $this->jardin, $this->typeTaille, '2027-03-06', $this->pommier->id)->assertOk();

    $soldee = DB::table('echeances')->sole();
    expect($soldee->regle_id)->toBe($this->taille->id)
        ->and($soldee->due_le)->toBe('2027-02-01')
        ->and($soldee->etat)->toBe('soldee')
        ->and($soldee->intervention_id)->toBe(DB::table('interventions')->value('id'))
        ->and(prochaineDe($this->jardin, $this->taille))->toBe(['a_venir', '2028-02-01']);
});

it('recalcule une tonte à partir du samedi, et solde l\'échéance due ce jour-là', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $pelouse = Plante::factory()->create(['nom' => 'Pelouse du devant']);
    $tonte = Regle::factory()->r1(7)->create(['plante_id' => $pelouse->id]);
    IntervalleSaisonnier::factory()->for($tonte)->create();
    $this->travelTo(new DateTimeImmutable('2027-03-08 09:00:00', new DateTimeZone('Europe/Paris')));

    saisirLe($this->proprietaire, $this->jardin, RegleFactory::typeFourni('Tonte'), '2027-03-06', $pelouse->id)->assertOk();

    $soldee = DB::table('echeances')->where('regle_id', $tonte->id)->sole();
    expect($soldee->due_le)->toBe('2027-03-06')
        ->and($soldee->etat)->toBe('soldee')
        ->and(prochaineDe($this->jardin, $tonte))->toBe(['a_venir', '2027-03-13']);
});

it('ne solde rien pour une taille hors de toute fenêtre', function () {
    $this->travelTo(new DateTimeImmutable('2027-06-20 09:00:00', new DateTimeZone('Europe/Paris')));

    saisirLe($this->proprietaire, $this->jardin, $this->typeTaille, '2027-06-15', $this->pommier->id)->assertOk();

    expect(DB::table('echeances')->where('etat', 'soldee')->count())->toBe(0)
        ->and(DB::table('interventions')->count())->toBe(1);
});

it('solde l\'échéance de chaque plante d\'une saisie sur plusieurs plantes', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $poirier = Plante::factory()->create(['nom' => 'Poirier du verger']);
    $autre = Regle::factory()->create(['plante_id' => $poirier->id]);
    Fenetre::factory()->for($autre)->create();
    $this->travelTo(new DateTimeImmutable('2027-03-08 09:00:00', new DateTimeZone('Europe/Paris')));

    saisirLe($this->proprietaire, $this->jardin, $this->typeTaille, '2027-03-06', $this->pommier->id, $poirier->id)->assertOk();

    expect(DB::table('echeances')->where('etat', 'soldee')->orderBy('plante_id')->pluck('regle_id')->sort()->values()->all())
        ->toBe(collect([$this->taille->id, $autre->id])->sort()->values()->all());
});

it('passe une manquée déjà écrite à soldée, quand la taille la contient', function () {
    // Le calcul du 02/04 a écrit la fenêtre 2027 manquée ; la taille du 28/03 est saisie après.
    $this->travelTo(new DateTimeImmutable('2027-04-02 09:00:00', new DateTimeZone('Europe/Paris')));
    app(JardinCourant::class)->positionner($this->jardin->id);
    app(MaterialiserManquees::class)->executer(app(CalculEcheances::class)->pourJardin($this->jardin));
    expect(DB::table('echeances')->sole()->etat)->toBe('manquee');

    saisirLe($this->proprietaire, $this->jardin, $this->typeTaille, '2027-03-28', $this->pommier->id)->assertOk();

    $echeance = DB::table('echeances')->sole();
    expect($echeance->due_le)->toBe('2027-02-01')
        ->and($echeance->etat)->toBe('soldee')
        ->and($echeance->intervention_id)->toBe(DB::table('interventions')->value('id'));
});
