<?php

// V116 (A19, A20, A21) : une miniature 160 px et une image d'affichage
// 1 200 px au format WebP sont produites par un job, qui porte le
// `jardin_id` ; elles existent sur le disque sous un nom qui contient
// l'empreinte, et `derivees_pretes` passe à vrai. La requête d'envoi
// n'attend pas le job.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Journal\Photos\CompresserPhoto;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;
    Storage::fake('local');
    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
});

/** Envoie une photo 2 400 × 1 600 sur la plante de A ; rend son id. */
function envoyerGrandePhoto(): string
{
    $id = (string) Str::uuid();
    app()->forgetScopedInstances();
    test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->post('/photos', ['id' => $id, 'fichier' => UploadedFile::fake()->image('verger.jpg', 2400, 1600), 'plante_id' => test()->plante], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('derivees_pretes', false);

    return $id;
}

function servirPhoto(string $id, string $taille): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->get("/photos/{$id}/{$taille}");
}

/** Le worker vide la file, sans jardin courant hérité (D-80). */
function viderLaFile(): void
{
    app()->forgetScopedInstances();
    test()->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--memory' => 1024]);
}

it('envoie le job avec le jardin de la photo, sans l\'attendre', function () {
    Queue::fake();

    $id = envoyerGrandePhoto();

    Queue::assertPushed(CompresserPhoto::class, fn (CompresserPhoto $job) => $job->jardinId === $this->jardins->a->id && $job->photoId === $id);
    expect(DB::table('photos')->where('id', $id)->value('derivees_pretes'))->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('produit les deux dérivées WebP par la file, puis les sert', function () {
    config(['queue.default' => 'database']);
    $id = envoyerGrandePhoto();
    $photo = DB::table('photos')->where('id', $id)->sole();
    $dossier = dirname($photo->chemin_original);

    // Avant le job : l'original, servi pour toutes les tailles.
    expect(DB::table('jobs')->count())->toBe(1);
    servirPhoto($id, '160')->assertOk()->assertHeader('Content-Type', 'image/jpeg');

    viderLaFile();

    expect(app(JardinCourant::class)->estPositionne())->toBeFalse()
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('photos')->where('id', $id)->value('derivees_pretes'))->toBeTrue();
    foreach (['160' => [160, 107], '1200' => [1200, 800]] as $taille => $dimensions) {
        $chemin = "{$dossier}/{$photo->empreinte}-{$taille}.webp";
        expect(Storage::disk('local')->exists($chemin))->toBeTrue();
        $info = getimagesizefromstring((string) Storage::disk('local')->get($chemin));
        expect($info)->not->toBeFalse()
            ->and([$info[0], $info[1], $info['mime']])->toBe([...$dimensions, 'image/webp']);

        servirPhoto($id, (string) $taille)->assertOk()->assertHeader('Content-Type', 'image/webp');
    }
    servirPhoto($id, 'original')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

it('laisse l\'original servi quand le job échoue trois fois', function () {
    config(['queue.default' => 'database']);
    $id = envoyerGrandePhoto();
    $photo = DB::table('photos')->where('id', $id)->sole();
    Storage::disk('local')->put($photo->chemin_original, 'plus une image');

    viderLaFile();

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('photos')->where('id', $id)->value('derivees_pretes'))->toBeFalse()
        ->and(Storage::disk('local')->allFiles())->toBe([$photo->chemin_original]);
    expect(servirPhoto($id, '1200')->assertOk()->streamedContent())->toBe('plus une image');
});

it('ne sert pas la photo d\'un autre jardin', function () {
    Queue::fake();
    $id = envoyerGrandePhoto();

    app()->forgetScopedInstances();
    $this->actingAs($this->jardins->proprietaireB)
        ->withSession(['jardin_id' => $this->jardins->b->id])
        ->get("/photos/{$id}/160")
        ->assertNotFound();
});
