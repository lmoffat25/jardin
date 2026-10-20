<?php

// V117 (CDC §8.4, §8.6, D-60) : un envoi qui ferait dépasser le quota du
// jardin est refusé avec un message, sans rien stocker ; l'occupation compte
// les photos du jardin, et seulement elles.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Quotas\CalculerOccupation;
use Src\Journal\Domaine\Photo;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    Storage::fake('local');
    Queue::fake();

    // Un quota de 1 Mo pour A, occupé à 1 000 Ko ; B, plein, ne compte pas.
    DB::table('jardins')->where('id', $this->jardins->a->id)->update(['quota_photos_mo' => 1]);
    app(JardinCourant::class)->positionner($this->jardins->b->id);
    Photo::factory()->create(['taille_ko' => 5000]);
    app()->forgetScopedInstances();
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    Photo::factory()->count(2)->create(['taille_ko' => 500]);
    app()->forgetScopedInstances();

    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
});

/** Envoie dans A une image dont le poids annoncé est donné en Ko. */
function envoyerPesant(int $ko): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->post('/photos', [
            'id' => (string) Str::uuid(),
            'fichier' => UploadedFile::fake()->image('p.jpg', 10, 10)->size($ko),
            'plante_id' => test()->plante,
        ], ['Accept' => 'application/json']);
}

it('compte les photos du jardin courant, et seulement elles', function () {
    app(JardinCourant::class)->positionner($this->jardins->a->id);

    expect(app(CalculerOccupation::class)->pourJardin())->toBe(1000);
});

it('refuse un envoi qui dépasse le quota, sans rien stocker', function () {
    envoyerPesant(25)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fichier' => 'Quota atteint : ce jardin n\'a plus de place pour les photos.']);

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(DB::table('photos')->where('jardin_id', $this->jardins->a->id)->count())->toBe(2);
    Queue::assertNothingPushed();
});

it('accepte un envoi qui remplit le quota tout juste', function () {
    envoyerPesant(24)->assertOk();

    expect(DB::table('photos')->where('jardin_id', $this->jardins->a->id)->sum('taille_ko'))->toBe(1024);
});
