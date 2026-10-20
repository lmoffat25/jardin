<?php

// V105 (CDC §4.3–4.4, écran B6) : suspendre l'échéance `(regle_id, due_le)`
// avec un motif la matérialise `suspendue` ; la suivante est proposée. Sans
// motif : erreur, rien n'est écrit. Règle de B : 404. Recommencer n'écrit pas
// une seconde ligne.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

/** Fige l'horloge à $jour, 10 h à Paris. */
function suspendreAu(string $jour): void
{
    test()->travelTo(new DateTimeImmutable("{$jour} 10:00:00", new DateTimeZone('Europe/Paris')));
}

/** Une règle de taille, fenêtre du 01/02 au 31/03, posée le $poseeLe sur la plante $nom du jardin $jardin. */
function tailleASauter(string $jardin, string $nom, string $poseeLe): Regle
{
    suspendreAu($poseeLe);
    app(JardinCourant::class)->positionner($jardin);
    $regle = Regle::factory()->create(['plante_id' => DB::table('plantes')->where('nom', $nom)->value('id')]);
    Fenetre::factory()->for($regle)->create();
    app()->forgetScopedInstances();

    return $regle;
}

/** @param  array<string, mixed>  $charge */
function suspendre(Utilisateur $u, array $charge): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->postJson('/echeances/suspendre', $charge);
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    $this->regle = tailleASauter($this->jardins->a->id, 'A Plante Potager 1', '2026-10-01');
    suspendreAu('2027-03-10');
});

it('matérialise l\'échéance suspendue avec son motif, et propose la suivante', function () {
    $reponse = suspendre($this->jardins->proprietaireA, ['regle_id' => $this->regle->id, 'due_le' => '2027-02-01', 'motif' => 'Gel tardif annoncé']);

    $reponse->assertOk()
        ->assertJsonPath('echeance', ['regle_id' => $this->regle->id, 'due_le' => '2027-02-01', 'etat' => 'suspendue', 'motif' => 'Gel tardif annoncé'])
        ->assertJsonPath('prochaine.cle', ['regle_id' => $this->regle->id, 'due_le' => '2028-02-01']);
    $suspendue = DB::table('echeances')->sole();
    expect($suspendue->jardin_id)->toBe($this->jardins->a->id)
        ->and($suspendue->regle_id)->toBe($this->regle->id)
        ->and($suspendue->plante_id)->toBe($this->regle->plante_id)
        ->and($suspendue->due_le)->toBe('2027-02-01')
        ->and($suspendue->etat)->toBe('suspendue')
        ->and($suspendue->motif_suspension)->toBe('Gel tardif annoncé')
        ->and($suspendue->intervention_id)->toBeNull()
        ->and($suspendue->fenetre_debut)->toBe('2027-02-01')
        ->and($suspendue->fenetre_fin)->toBe('2027-03-31')
        ->and($suspendue->explication)->toContain('fenêtre févr.–mars');
});

it('n\'écrit pas une seconde suspension quand on recommence', function () {
    $charge = ['regle_id' => $this->regle->id, 'due_le' => '2027-02-01', 'motif' => 'Gel tardif annoncé'];

    $premiere = suspendre($this->jardins->proprietaireA, $charge)->assertOk();
    $seconde = suspendre($this->jardins->proprietaireA, [...$charge, 'motif' => 'Autre motif'])->assertOk();

    expect($seconde->json())->toBe($premiere->json())
        ->and(DB::table('echeances')->count())->toBe(1)
        ->and(DB::table('echeances')->value('motif_suspension'))->toBe('Gel tardif annoncé');
});

it('refuse ce qu\'il ne peut pas suspendre, et n\'écrit rien', function (Closure $charge, string $champ) {
    $charge = $charge();
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    suspendre($this->jardins->proprietaireA, ['regle_id' => $this->regle->id, 'due_le' => '2027-02-01', 'motif' => 'Gel tardif annoncé', ...$charge])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($champ);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
})->with([
    'sans motif' => [fn () => ['motif' => ''], 'motif'],
    'motif trop long' => [fn () => ['motif' => str_repeat('a', 201)], 'motif'],
    'couple que le moteur ne rend pas' => [fn () => ['due_le' => '2027-02-02'], 'due_le'],
    'occurrence de l\'an prochain' => [fn () => ['due_le' => '2028-02-01'], 'due_le'],
    'date au format français' => [fn () => ['due_le' => '01/02/2027'], 'due_le'],
    'règle inactive' => [function () {
        DB::table('regles')->where('id', test()->regle->id)->update(['est_active' => false, 'remplacee_le' => now()]);

        return [];
    }, 'regle_id'],
    'occurrence déjà soldée' => [function () {
        app(JardinCourant::class)->positionner(test()->jardins->a->id);
        EcheanceMaterialisee::factory()->for(test()->regle)->create(['due_le' => '2027-02-01', 'fenetre_debut' => '2027-02-01', 'fenetre_fin' => '2027-03-31', 'etat' => 'soldee']);
        app()->forgetScopedInstances();

        return [];
    }, 'due_le'],
]);

it('laisse manquée une occurrence manquée (RG-07)', function () {
    // Posée avant l'hiver 2026 : la fenêtre de 2026 est manquée.
    $regle = tailleASauter($this->jardins->a->id, 'A Plante Verger 1', '2026-01-15');
    suspendreAu('2027-03-10');

    suspendre($this->jardins->proprietaireA, ['regle_id' => $regle->id, 'due_le' => '2026-02-01', 'motif' => 'Oubliée'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('due_le');

    expect(DB::table('echeances')->where('etat', 'suspendue')->count())->toBe(0);
});

it('ne saute pas une échéance R1 (Q-UX-22)', function () {
    suspendreAu('2027-05-10');
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $tonte = Regle::factory()->r1(7)->create(['plante_id' => $this->regle->plante_id]);
    IntervalleSaisonnier::factory()->for($tonte)->create();

    suspendre($this->jardins->proprietaireA, ['regle_id' => $tonte->id, 'due_le' => '2027-05-10', 'motif' => 'Pluie'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('regle_id');

    expect(DB::table('echeances')->count())->toBe(0);
});

it('rend 403 au membre, et n\'écrit rien', function () {
    suspendre($this->jardins->membreA, ['regle_id' => $this->regle->id, 'due_le' => '2027-02-01', 'motif' => 'Gel tardif annoncé'])
        ->assertForbidden();

    expect(DB::table('echeances')->count())->toBe(0);
});

it('rend 404 pour une règle de B, et laisse B intact', function () {
    $regleB = tailleASauter($this->jardins->b->id, DeuxJardins::MARQUEUR.' Plante Potager 1', '2026-10-01');
    suspendreAu('2027-03-10');
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    suspendre($this->jardins->proprietaireA, ['regle_id' => $regleB->id, 'due_le' => '2027-02-01', 'motif' => 'Gel tardif annoncé'])
        ->assertNotFound();

    expect(DB::table('echeances')->count())->toBe(0)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});
