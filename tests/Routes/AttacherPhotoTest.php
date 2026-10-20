<?php

// V115 (RG-35, RG-38, H10, critère 14, écrans T4, T5, B6) : une image avec un
// identifiant client, rattachée à une plante ou à une intervention du jardin,
// est stockée sous `photos/{jardin}/{annee}/{empreinte}.{ext}` ; renvoyée,
// elle reste unique. Type non image, poids ou dimensions hors limites :
// erreur, rien n'est stocké. Plante ou intervention de B : erreur, aucun
// fichier écrit. En base : `derivees_pretes = false`, empreinte et taille.
//
// V118 (H10, RG-38) : une intervention enregistrée existe sans sa photo quand
// l'envoi de la photo échoue ; aucun des deux enregistrements n'attend l'autre.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Journal\Photos\CompresserPhoto;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-14');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;
    Storage::fake('local');
    Queue::fake();

    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
    $this->planteB = DB::table('plantes')->where('nom', DeuxJardins::MARQUEUR.' Plante Potager 1')->value('id');
});

/**
 * Un envoi de photo dans un jardin, par le membre de A par défaut.
 *
 * @param  array<string, mixed>  $charge
 */
function envoyerPhoto(array $charge, ?Utilisateur $u = null, ?string $jardin = null): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u ?? test()->jardins->membreA)
        ->withSession(['jardin_id' => $jardin ?? test()->jardins->a->id])
        ->post('/photos', $charge, ['Accept' => 'application/json']);
}

/** Une intervention enregistrée par le membre de A sur la plante de A ; rend son id. */
function interventionDansA(): string
{
    $id = (string) Str::uuid();
    app()->forgetScopedInstances();
    test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->postJson('/interventions', [
            'type_intervention_id' => RegleFactory::typeFourni('Tonte'),
            'faite_le' => '2026-08-30',
            'lignes' => [['id' => $id, 'plante_id' => test()->plante]],
        ])->assertOk();

    return $id;
}

/** @return list<string> tous les fichiers du disque privé */
function fichiersStockes(): array
{
    return Storage::disk('local')->allFiles();
}

it('stocke une photo de plante sous le jardin, l\'année et l\'empreinte', function () {
    $fichier = UploadedFile::fake()->image('taille.jpg', 800, 600);
    $empreinte = hash_file('sha256', $fichier->getRealPath());
    $id = (string) Str::uuid();

    envoyerPhoto(['id' => $id, 'fichier' => $fichier, 'plante_id' => $this->plante, 'prise_le' => '2026-08-31'])
        ->assertOk()
        ->assertExactJson(['id' => $id, 'url' => "/photos/{$id}/1200", 'derivees_pretes' => false]);

    $chemin = "photos/{$this->jardins->a->id}/2026/{$empreinte}.jpg";
    expect(fichiersStockes())->toBe([$chemin]);

    $ligne = DB::table('photos')->where('id', $id)->sole();
    expect($ligne->jardin_id)->toBe($this->jardins->a->id)
        ->and($ligne->plante_id)->toBe($this->plante)
        ->and($ligne->intervention_id)->toBeNull()
        ->and($ligne->empreinte)->toBe($empreinte)
        ->and($ligne->chemin_original)->toBe($chemin)
        ->and($ligne->derivees_pretes)->toBeFalse()
        ->and($ligne->taille_ko)->toBe((int) ceil($fichier->getSize() / 1024))
        ->and([$ligne->largeur_px, $ligne->hauteur_px])->toBe([800, 600])
        ->and($ligne->prise_le)->toBe('2026-08-31');
});

it('rattache une photo à une intervention du jardin', function () {
    $intervention = interventionDansA();
    $id = (string) Str::uuid();

    envoyerPhoto(['id' => $id, 'fichier' => UploadedFile::fake()->image('avant.png', 40, 30), 'intervention_id' => $intervention])
        ->assertOk();

    $ligne = DB::table('photos')->where('id', $id)->sole();
    expect($ligne->intervention_id)->toBe($intervention)
        ->and($ligne->plante_id)->toBeNull()
        ->and($ligne->chemin_original)->toEndWith('.png');
});

it('ne garde qu\'une photo pour un envoi répété, et rend deux fois la même réponse', function () {
    $id = (string) Str::uuid();
    $charge = ['id' => $id, 'fichier' => UploadedFile::fake()->image('a.jpg', 50, 50), 'plante_id' => $this->plante];

    $premiere = envoyerPhoto($charge)->assertOk();
    $avant = (array) DB::table('photos')->where('id', $id)->sole();

    // Le renvoi porte un autre fichier : il ne remplace rien, n'écrit rien.
    $seconde = envoyerPhoto([...$charge, 'fichier' => UploadedFile::fake()->image('b.jpg', 70, 70)], $this->jardins->proprietaireA)->assertOk();

    expect($seconde->getContent())->toBe($premiere->getContent())
        ->and(DB::table('photos')->count())->toBe(1)
        ->and((array) DB::table('photos')->where('id', $id)->sole())->toBe($avant)
        ->and(fichiersStockes())->toHaveCount(1);
    Queue::assertPushedTimes(CompresserPhoto::class, 1);
});

it('refuse ce qui n\'est pas une image, ou dépasse les limites, sans rien stocker', function (Closure $fichier, string $message) {
    envoyerPhoto(['id' => (string) Str::uuid(), 'fichier' => $fichier(), 'plante_id' => $this->plante])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fichier' => $message]);

    expect(fichiersStockes())->toBe([])
        ->and(DB::table('photos')->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'texte' => [fn () => UploadedFile::fake()->createWithContent('note.jpg', 'pas une image'), 'Une photo JPEG, PNG ou WebP.'],
    'gif' => [fn () => UploadedFile::fake()->image('anim.gif', 10, 10), 'Une photo JPEG, PNG ou WebP.'],
    'plus de 20 Mo' => [fn () => UploadedFile::fake()->image('lourde.jpg', 10, 10)->size(20 * 1024 + 1), 'Une photo pèse 20 Mo au plus.'],
    'plus de 12 000 px' => [fn () => UploadedFile::fake()->image('large.png', 12001, 2), 'Une photo mesure 12 000 px de côté au plus.'],
]);

it('refuse une plante ou une intervention de B, sans écrire de fichier', function (string $champ) {
    $b = $this->jardins->b->id;
    $cible = $champ === 'plante_id'
        ? $this->planteB
        : DB::table('interventions')->insertGetId([
            'id' => (string) Str::uuid(),
            'jardin_id' => $b,
            'plante_id' => $this->planteB,
            'type_intervention_id' => RegleFactory::typeFourni('Tonte'),
            'auteur_id' => $this->jardins->proprietaireB->id,
            'faite_le' => '2026-08-30',
        ], 'id');
    $avant = DeuxJardins::empreinte($b);

    envoyerPhoto(['id' => (string) Str::uuid(), 'fichier' => UploadedFile::fake()->image('a.jpg', 20, 20), $champ => $cible], $this->jardins->desDeux)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$champ]);

    expect(fichiersStockes())->toBe([])
        ->and(DB::table('photos')->count())->toBe(0)
        ->and(DeuxJardins::empreinte($b))->toBe($avant);
})->with(['plante_id', 'intervention_id']);

it('garde l\'intervention quand l\'envoi de sa photo échoue', function () {
    $intervention = interventionDansA();

    envoyerPhoto(['id' => (string) Str::uuid(), 'fichier' => UploadedFile::fake()->createWithContent('photo.jpg', 'tronquée'), 'intervention_id' => $intervention])
        ->assertUnprocessable();

    expect(DB::table('interventions')->where('id', $intervention)->exists())->toBeTrue()
        ->and(DB::table('photos')->count())->toBe(0);
});

it('accepte une photo pour une intervention, envoyée après elle et à part', function () {
    // L'intervention n'attend pas sa photo : elle existe seule, puis la photo s'y joint.
    $intervention = interventionDansA();
    expect(DB::table('photos')->count())->toBe(0);

    envoyerPhoto(['id' => (string) Str::uuid(), 'fichier' => UploadedFile::fake()->image('apres.jpg', 30, 30), 'intervention_id' => $intervention])
        ->assertOk();

    expect(DB::table('photos')->where('intervention_id', $intervention)->count())->toBe(1);
});
