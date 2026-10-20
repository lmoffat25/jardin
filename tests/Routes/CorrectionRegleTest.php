<?php

// V102 (RG-08, D10, P7, écran B9) : corriger la périodicité, une fenêtre ou
// un intervalle d'une règle rend v1 inactive avec sa date de remplacement et
// crée v2 pour la même plante et le même type ; la réponse porte la prochaine
// échéance recalculée. V103 (D10, RG-06) : une échéance écrite sous v1 garde
// son `regle_id` et sa phrase. V104 (RG-21, §2.1) : une fenêtre impossible ne
// change rien ; un membre reçoit 403, une règle de B 404, B intact.

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
function corrigerAu(string $jour): void
{
    test()->travelTo(new DateTimeImmutable("{$jour} 10:00:00", new DateTimeZone('Europe/Paris')));
}

/**
 * Une règle posée le $poseeLe sur la plante $nom du jardin $jardin : R2 de
 * taille, fenêtre du 01/02 au 31/03 ; ou R1 de tonte, de mars à octobre tous
 * les 7 jours.
 */
function reglePosee(string $jardin, string $nom, string $poseeLe, string $type = 'R2'): Regle
{
    corrigerAu($poseeLe);
    app(JardinCourant::class)->positionner($jardin);
    $plante = DB::table('plantes')->where('nom', $nom)->value('id');
    if ($type === 'R1') {
        $regle = Regle::factory()->r1(7)->create(['plante_id' => $plante]);
        IntervalleSaisonnier::factory()->for($regle)->create();
    } else {
        $regle = Regle::factory()->create(['plante_id' => $plante]);
        Fenetre::factory()->for($regle)->create(['optimal_debut_jour' => 15, 'optimal_debut_mois' => 2, 'optimal_fin_jour' => 15, 'optimal_fin_mois' => 3]);
    }
    app()->forgetScopedInstances();

    return $regle;
}

/** @param  array<string, mixed>  $charge */
function corriger(Utilisateur $u, string $regle, array $charge, bool $json = true): TestResponse
{
    app()->forgetScopedInstances();
    $requete = test()->actingAs($u)->withSession(['jardin_id' => test()->jardins->a->id]);

    return $json ? $requete->putJson("/regles/{$regle}", $charge) : $requete->put("/regles/{$regle}", $charge);
}

/** @return list<array<string, mixed>> les lignes d'une table fille d'une règle, sans id */
function lignesDe(string $table, string $regle): array
{
    return DB::table($table)->where('regle_id', $regle)->orderBy('id')->get()
        ->map(fn ($l) => array_diff_key((array) $l, ['id' => 0, 'regle_id' => 0]))
        ->all();
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    $this->regle = reglePosee($this->jardins->a->id, 'A Plante Potager 1', '2026-10-01');
    corrigerAu('2026-11-15');
});

it('corrige la périodicité : v1 inactive et remplacée, v2 active, la prochaine recalculée', function () {
    $fenetresV1 = lignesDe('fenetres', $this->regle->id);

    $reponse = corriger($this->jardins->proprietaireA, $this->regle->id, ['periodicite_annees' => 2])->assertOk();

    $v1 = DB::table('regles')->where('id', $this->regle->id)->sole();
    $v2 = DB::table('regles')->where('id', '!=', $this->regle->id)->sole();
    expect($v1->est_active)->toBeFalse()
        ->and($v1->remplacee_le)->not->toBeNull()
        ->and($v1->periodicite_annees)->toBe(1)
        ->and($v1->version)->toBe(1)
        ->and($v2->est_active)->toBeTrue()
        ->and($v2->remplacee_le)->toBeNull()
        ->and($v2->version)->toBe(2)
        ->and($v2->periodicite_annees)->toBe(2)
        ->and($v2->origine)->toBe('ajustee')
        ->and($v2->plante_id)->toBe($v1->plante_id)
        ->and($v2->type_intervention_id)->toBe($v1->type_intervention_id)
        ->and($v2->regle_reference_id)->toBe($v1->regle_reference_id)
        ->and($v2->jardin_id)->toBe($this->jardins->a->id)
        ->and(lignesDe('fenetres', $this->regle->id))->toBe($fenetresV1)
        ->and(lignesDe('fenetres', $v2->id))->toBe($fenetresV1);
    $reponse->assertJsonPath('regle', ['id' => $v2->id, 'version' => 2])
        ->assertJsonPath('prochaine.cle.regle_id', $v2->id);
});

it('corrige une fenêtre : celles de v2 corrigées, celles de v1 intactes', function () {
    $fenetresV1 = lignesDe('fenetres', $this->regle->id);

    $reponse = corriger($this->jardins->proprietaireA, $this->regle->id, ['fenetres' => [
        ['debut' => '15/02', 'fin' => '15/04', 'optimal' => ['01/03', '31/03'], 'butoirs' => [null, '20/04'], 'libelle' => 'Après les gelées'],
    ]])->assertOk();

    $v2 = DB::table('regles')->where('est_active', true)->sole();
    $fenetre = DB::table('fenetres')->where('regle_id', $v2->id)->sole();
    expect(lignesDe('fenetres', $this->regle->id))->toBe($fenetresV1)
        ->and([$fenetre->debut_jour, $fenetre->debut_mois, $fenetre->fin_jour, $fenetre->fin_mois])->toBe([15, 2, 15, 4])
        ->and([$fenetre->optimal_debut_jour, $fenetre->optimal_debut_mois, $fenetre->optimal_fin_jour, $fenetre->optimal_fin_mois])->toBe([1, 3, 31, 3])
        ->and([$fenetre->butoir_debut_jour, $fenetre->butoir_fin_jour, $fenetre->butoir_fin_mois])->toBe([null, 20, 4])
        ->and($fenetre->libelle)->toBe('Après les gelées')
        ->and($fenetre->jardin_id)->toBe($this->jardins->a->id);
    $reponse->assertJsonPath('prochaine.cle', ['regle_id' => $v2->id, 'due_le' => '2027-02-15'])
        ->assertJsonPath('prochaine.periode', ['debut' => '2027-02-15', 'fin' => '2027-04-15', 'optimal_debut' => '2027-03-01', 'optimal_fin' => '2027-03-31']);
});

it('corrige un intervalle : les saisons de v2 corrigées, celles de v1 intactes', function () {
    $tonte = reglePosee($this->jardins->a->id, 'A Plante Verger 1', '2026-10-01', 'R1');
    corrigerAu('2026-11-15');
    $saisonsV1 = lignesDe('intervalles_saisonniers', $tonte->id);

    corriger($this->jardins->proprietaireA, $tonte->id, ['intervalles' => [
        ['mois_debut' => 4, 'mois_fin' => 9, 'jours' => 10],
        ['mois_debut' => 10, 'mois_fin' => 3, 'jours' => 0],
    ]])->assertOk()->assertJsonPath('regle.version', 2);

    $v2 = DB::table('regles')->where('plante_id', $tonte->plante_id)->where('est_active', true)->sole();
    expect(lignesDe('intervalles_saisonniers', $tonte->id))->toBe($saisonsV1)
        ->and(DB::table('intervalles_saisonniers')->where('regle_id', $v2->id)->orderBy('mois_debut')->get(['mois_debut', 'mois_fin', 'intervalle_jours'])->map(fn ($l) => (array) $l)->all())
        ->toBe([['mois_debut' => 4, 'mois_fin' => 9, 'intervalle_jours' => 10], ['mois_debut' => 10, 'mois_fin' => 3, 'intervalle_jours' => 0]])
        // Hors saison, la R1 retombe sur son plus court intervalle non nul (D-98, D-101).
        ->and($v2->intervalle_jours)->toBe(10);
});

it('renvoie le formulaire vers la fiche de la plante', function () {
    corriger($this->jardins->proprietaireA, $this->regle->id, ['periodicite_annees' => 2], json: false)
        ->assertRedirect("/plantes/{$this->regle->plante_id}");
});

it('garde aux échéances de v1 leur règle et leur phrase, et écrit ses manquées', function () {
    // Posée avant l'hiver 2025 : les fenêtres 2025 et 2026 sont manquées ; celle de 2025 est déjà écrite.
    $regle = reglePosee($this->jardins->a->id, 'A Plante Verger 1', '2025-01-15');
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    EcheanceMaterialisee::factory()->for($regle)->create(['due_le' => '2025-02-01', 'fenetre_debut' => '2025-02-01', 'fenetre_fin' => '2025-03-31', 'etat' => 'manquee', 'explication' => 'Phrase écrite sous la version 1.']);
    corrigerAu('2026-11-15');

    corriger($this->jardins->proprietaireA, $regle->id, ['fenetres' => [['debut' => '15/02', 'fin' => '15/04']]])->assertOk();

    $echeances = DB::table('echeances')->where('plante_id', $regle->plante_id)->orderBy('due_le')->get();
    expect($echeances->pluck('due_le')->all())->toBe(['2025-02-01', '2026-02-01'])
        ->and($echeances->pluck('regle_id')->unique()->all())->toBe([$regle->id])
        ->and($echeances->pluck('etat')->unique()->all())->toBe(['manquee'])
        ->and($echeances[0]->explication)->toBe('Phrase écrite sous la version 1.')
        ->and($echeances[1]->explication)->toContain('fenêtre févr.–mars');
});

it('refuse une correction invalide, et ne change rien', function (array $charge, string $champ) {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    corriger($this->jardins->proprietaireA, $this->regle->id, $charge)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($champ);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
})->with([
    'date qui n\'existe pas' => [['fenetres' => [['debut' => '31/02', 'fin' => '31/03']]], 'fenetres.0'],
    'date au mauvais format' => [['fenetres' => [['debut' => '1/2', 'fin' => '31/03']]], 'fenetres.0'],
    'optimale hors de la fenêtre' => [['fenetres' => [['debut' => '01/02', 'fin' => '31/03', 'optimal' => ['15/03', '15/04']]]], 'fenetres.0'],
    'optimale à l\'envers' => [['fenetres' => [['debut' => '01/02', 'fin' => '31/03', 'optimal' => ['15/03', '15/02']]]], 'fenetres.0'],
    'butoir à moitié' => [['fenetres' => [['debut' => '01/02', 'fin' => '31/03', 'butoirs' => ['15/03']]]], 'fenetres.0'],
    'butoirs vides' => [['fenetres' => [['debut' => '01/02', 'fin' => '31/03', 'butoirs' => [null, null]]]], 'fenetres.0'],
    'vide après rognage sous un profil' => [['fenetres' => [['debut' => '01/02', 'fin' => '20/02', 'butoirs' => [null, '10/02']]]], 'fenetres.0'],
    'clé inconnue' => [['fenetres' => [['debut' => '01/02', 'fin' => '31/03', 'couleur' => 'verte']]], 'fenetres.0'],
    'aucune fenêtre' => [['fenetres' => []], 'fenetres'],
    'périodicité nulle' => [['periodicite_annees' => 0], 'periodicite_annees'],
    'intervalle sur une règle R2' => [['intervalles' => [['mois_debut' => 1, 'mois_fin' => 12, 'jours' => 7]]], 'intervalles'],
    'rien à corriger' => [[], 'regle'],
]);

it('refuse des saisons qu\'une R1 ne peut pas avoir, et ne change rien', function (array $charge, string $champ) {
    $tonte = reglePosee($this->jardins->a->id, 'A Plante Verger 1', '2026-10-01', 'R1');
    corrigerAu('2026-11-15');
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    corriger($this->jardins->proprietaireA, $tonte->id, $charge)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($champ);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
})->with([
    'un mois dans deux saisons' => [['intervalles' => [['mois_debut' => 3, 'mois_fin' => 6, 'jours' => 7], ['mois_debut' => 6, 'mois_fin' => 9, 'jours' => 14]]], 'intervalles'],
    'toujours hors saison' => [['intervalles' => [['mois_debut' => 1, 'mois_fin' => 12, 'jours' => 0]]], 'intervalles'],
    'périodicité sur une règle R1' => [['periodicite_annees' => 2], 'periodicite_annees'],
    'fenêtre sur une règle R1' => [['fenetres' => [['debut' => '01/02', 'fin' => '31/03']]], 'fenetres'],
]);

it('refuse de corriger une version déjà remplacée', function () {
    corriger($this->jardins->proprietaireA, $this->regle->id, ['periodicite_annees' => 2])->assertOk();

    corriger($this->jardins->proprietaireA, $this->regle->id, ['periodicite_annees' => 3])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('regle');

    expect(DB::table('regles')->count())->toBe(2)
        ->and(DB::table('regles')->max('version'))->toBe(2);
});

it('rend 403 au membre, et laisse A intact', function () {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    corriger($this->jardins->membreA, $this->regle->id, ['periodicite_annees' => 2])->assertForbidden();

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('rend 404 pour une règle de B, et laisse B intact', function () {
    $regleB = reglePosee($this->jardins->b->id, DeuxJardins::MARQUEUR.' Plante Potager 1', '2026-10-01');
    corrigerAu('2026-11-15');
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    corriger($this->jardins->proprietaireA, $regleB->id, ['periodicite_annees' => 2])->assertNotFound();

    expect(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});
