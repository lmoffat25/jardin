<?php

// V88 (RG-02, RG-13, CDC §2.1, écran B6) : archiver une plante avec un motif.
// Elle sort des listes courantes, son historique et ses manquées restent
// consultables, ses règles cessent de produire des échéances. Sans motif :
// erreur ; un membre : 403 ; une plante de B : 404, intacte. En base : `etat =
// archivee`, `archivee_le`, le motif ; règles `est_active = false` sans
// `remplacee_le` (D-22) ; le code court reste attribué.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;

    // Une plante de A : une taille à venir, une taille manquée, une intervention.
    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->sole();
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $this->taille = Regle::factory()->state(['plante_id' => $this->plante->id])->create();
    Fenetre::factory()->for($this->taille)->create();
    EcheanceMaterialisee::factory()->for($this->taille)->create(['plante_id' => $this->plante->id, 'due_le' => '2026-02-01']);
    Intervention::factory()->create(['plante_id' => $this->plante->id, 'auteur_id' => $this->jardins->proprietaireA->id, 'faite_le' => '2025-02-10']);
    // Une autre plante de A, à tailler aussi : elle reste aux échéances.
    $this->voisine = DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id');
    Fenetre::factory()->for(Regle::factory()->state(['plante_id' => $this->voisine]))->create();
    app()->forgetScopedInstances();
});

/** Un utilisateur de A, dans A, comme d'une requête neuve. */
function archiverDansA(Utilisateur $utilisateur): Utilisateur
{
    app()->forgetScopedInstances();
    test()->withSession(['jardin_id' => test()->jardins->a->id]);

    return $utilisateur;
}

/** @return list<string> les plantes des échéances calculées du jardin A */
function plantesEcheancesDeA(): array
{
    app()->forgetScopedInstances();
    app(JardinCourant::class)->positionner(test()->jardins->a->id);

    return app(CalculEcheances::class)->pourJardin(Jardin::query()->findOrFail(test()->jardins->a->id))
        ->map(fn (EcheanceCalculee $e) => $e->plante->id)
        ->unique()
        ->values()
        ->all();
}

it('archive la plante avec son motif, et garde son histoire', function () {
    expect(plantesEcheancesDeA())->toContain($this->plante->id, $this->voisine);

    $this->actingAs(archiverDansA($this->jardins->proprietaireA))
        ->post("/plantes/{$this->plante->id}/archiver", ['motif' => 'Arrachée après la tempête'])
        ->assertRedirect("/plantes/{$this->plante->id}")
        ->assertSessionHasNoErrors();

    // En base : l'état, la date, le motif ; le code court reste attribué.
    $apres = DB::table('plantes')->where('id', $this->plante->id)->sole();
    expect([$apres->etat, $apres->motif_archivage, $apres->code_court, $apres->nom, $apres->zone_id])
        ->toBe(['archivee', 'Arrachée après la tempête', $this->plante->code_court, $this->plante->nom, $this->plante->zone_id])
        ->and((new DateTimeImmutable($apres->archivee_le))->format('Y-m-d'))->toBe('2026-09-01')
        ->and(DB::table('plantes')->where('jardin_id', $this->jardins->a->id)->where('code_court', $this->plante->code_court)->count())->toBe(1);

    // Ses règles sont désactivées, pas remplacées (D-22).
    expect(DB::table('regles')->where('plante_id', $this->plante->id)->get(['est_active', 'remplacee_le'])->map(fn ($r) => (array) $r)->all())
        ->toBe([['est_active' => false, 'remplacee_le' => null]]);

    // Elle sort des échéances du jardin ; ses autres plantes y restent.
    expect(plantesEcheancesDeA())->toBe([$this->voisine]);

    // Sa fiche, ses manquées et son historique restent consultables.
    $this->withoutVite()->actingAs(archiverDansA($this->jardins->membreA))->get("/plantes/{$this->plante->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('plante.code_court', $this->plante->code_court)
            ->where('echeances', [])
            ->where('manquees', fn ($manquees) => collect($manquees)->pluck('cle.due_le')->all() === ['2026-02-01']));
    $this->actingAs(archiverDansA($this->jardins->membreA))->getJson("/plantes/{$this->plante->id}/historique")
        ->assertOk()
        ->assertJsonPath('interventions.0.faite_le', '2025-02-10');
});

it('refuse d\'archiver sans motif, et ne change rien', function (array $saisie) {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(archiverDansA($this->jardins->proprietaireA))
        ->postJson("/plantes/{$this->plante->id}/archiver", $saisie)
        ->assertStatus(422)
        ->assertJsonValidationErrors('motif');

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
})->with([
    'absent' => [[]],
    'vide' => [['motif' => '   ']],
    'trop long' => [['motif' => str_repeat('a', 201)]],
]);

it('refuse d\'archiver deux fois, et garde la première archive', function () {
    $this->actingAs(archiverDansA($this->jardins->proprietaireA))
        ->postJson("/plantes/{$this->plante->id}/archiver", ['motif' => 'Morte'])
        ->assertOk();
    $this->travelTo(new DateTimeImmutable('2026-09-05 10:00:00', new DateTimeZone('Europe/Paris')));
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(archiverDansA($this->jardins->proprietaireA))
        ->postJson("/plantes/{$this->plante->id}/archiver", ['motif' => 'Remplacée'])
        ->assertStatus(409);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('refuse l\'archivage à un membre', function () {
    $avant = DeuxJardins::empreinte($this->jardins->a->id);

    $this->actingAs(archiverDansA($this->jardins->membreA))
        ->postJson("/plantes/{$this->plante->id}/archiver", ['motif' => 'Morte'])
        ->assertForbidden();

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avant);
});

it('ne trouve pas une plante de B depuis A, et la laisse intacte', function () {
    $planteB = DB::table('plantes')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    $this->actingAs(archiverDansA($this->jardins->proprietaireA))
        ->postJson("/plantes/{$planteB}/archiver", ['motif' => 'Morte'])
        ->assertNotFound();
    $this->actingAs(archiverDansA($this->jardins->proprietaireA))
        ->postJson('/plantes/pas-un-uuid/archiver', ['motif' => 'Morte'])
        ->assertNotFound();

    expect(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});
