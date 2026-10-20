<?php

// V121 (chapitre 3 §12, A11, D-60) : la commande de nettoyage prend un jardin
// en argument ; sans `--supprimer`, elle liste les fichiers de ce jardin
// référencés nulle part depuis plus de 7 jours et ne supprime rien ; avec
// `--supprimer`, elle les supprime. Ceux des autres jardins, et les fichiers
// récents, restent.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Journal\Domaine\Photo;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-20 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;
    Storage::fake('local');

    $a = $this->jardins->a->id;
    $b = $this->jardins->b->id;
    $vieux = now()->subDays(8)->getTimestamp();

    // Dans A : une photo référencée et ses deux dérivées, un fond de plan
    // référencé, une orpheline ancienne, une orpheline récente.
    app(JardinCourant::class)->positionner($a);
    $photo = Photo::factory()->create([
        'plante_id' => DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id'),
        'empreinte' => str_repeat('a', 64),
        'chemin_original' => "photos/{$a}/2026/".str_repeat('a', 64).'.jpg',
    ]);
    app()->forgetScopedInstances();
    DB::table('fonds_de_plan')->insert([
        'id' => (string) Str::uuid(), 'jardin_id' => $a, 'nature' => 'image',
        'fichier' => "photos/{$a}/plans/plan.png", 'calage' => '[]', 'actif' => true,
    ]);
    $this->gardes = [
        $photo->chemin_original,
        $photo->cheminDerivee('160'),
        $photo->cheminDerivee('1200'),
        "photos/{$a}/plans/plan.png",
    ];
    $this->ancienne = "photos/{$a}/2026/".str_repeat('b', 64).'.jpg';
    $this->recente = "photos/{$a}/2026/".str_repeat('c', 64).'.jpg';
    $this->deB = "photos/{$b}/2026/".str_repeat('d', 64).'.jpg';

    foreach ([...$this->gardes, $this->ancienne, $this->deB] as $chemin) {
        Storage::disk('local')->put($chemin, 'image');
        touch(Storage::disk('local')->path($chemin), $vieux);
    }
    Storage::disk('local')->put($this->recente, 'image');
    touch(Storage::disk('local')->path($this->recente), now()->subDays(2)->getTimestamp());
});

/** @return list<string> */
function fichiersDuDisque(): array
{
    $fichiers = Storage::disk('local')->allFiles();
    sort($fichiers);

    return $fichiers;
}

it('liste les orphelines anciennes du jardin, sans rien supprimer', function () {
    $avant = fichiersDuDisque();

    $this->artisan('photos:nettoyer', ['jardin' => $this->jardins->a->id])
        ->expectsOutput($this->ancienne)
        ->doesntExpectOutput($this->recente)
        ->doesntExpectOutput($this->deB)
        ->doesntExpectOutput($this->gardes[0])
        ->doesntExpectOutput($this->gardes[1])
        ->doesntExpectOutput($this->gardes[3])
        ->expectsOutput('1 fichier orphelin de plus de 7 jours ; rien n\'est supprimé sans --supprimer.')
        ->assertSuccessful();

    expect(fichiersDuDisque())->toBe($avant);
});

it('supprime les orphelines anciennes du jardin avec --supprimer, et elles seules', function () {
    $avant = fichiersDuDisque();

    $this->artisan('photos:nettoyer', ['jardin' => $this->jardins->a->id, '--supprimer' => true])
        ->expectsOutput($this->ancienne)
        ->expectsOutput('1 fichier orphelin de plus de 7 jours supprimé.')
        ->assertSuccessful();

    expect(fichiersDuDisque())->toBe(array_values(array_diff($avant, [$this->ancienne])));
});

it('refuse un jardin absent ou inconnu, sans rien supprimer', function (string $jardin) {
    $avant = fichiersDuDisque();

    $this->artisan('photos:nettoyer', ['jardin' => $jardin, '--supprimer' => true])
        ->expectsOutput('Jardin inconnu : rien n\'est supprimé.')
        ->assertFailed();

    expect(fichiersDuDisque())->toBe($avant);
})->with(['pas un uuid' => 'jardin-1', 'inconnu' => '5e0c9a52-7a1d-4c3e-9f4b-0000000000ff']);
