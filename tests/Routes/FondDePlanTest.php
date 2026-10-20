<?php

// V120 (CDC §6 lot 4, RG-47, critère 16 côté serveur, écran B13) : une image
// avec deux ou trois points de calage (pixel, longitude, latitude) devient le
// fond actif du jardin, et le précédent cesse de l'être. Un seul point, ou des
// coordonnées hors bornes : erreur, rien n'est stocké. Le fond de B reste
// actif dans B et invisible dans A. En base : un seul fond actif par jardin ;
// l'image compte dans le quota.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Comptes\Domaine\Utilisateur;
use Src\Comptes\Quotas\CalculerOccupation;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-16');

/** Trois points de calage valides sur une image de 400 × 300 px. */
const TROIS_POINTS = [
    ['x' => 0, 'y' => 0, 'lng' => 4.8350, 'lat' => 45.7650],
    ['x' => 400, 'y' => 0, 'lng' => 4.8370, 'lat' => 45.7650],
    ['x' => 0, 'y' => 300, 'lng' => 4.8350, 'lat' => 45.7635],
];

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    Storage::fake('local');
});

/**
 * Importe un fond de plan dans un jardin, par son propriétaire (A par défaut).
 *
 * @param  list<array<string, mixed>>  $points
 */
function importerFond(array $points, ?UploadedFile $fichier = null, ?Utilisateur $u = null, ?string $jardin = null): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs($u ?? test()->jardins->proprietaireA)
        ->withSession(['jardin_id' => $jardin ?? test()->jardins->a->id])
        ->post('/fonds-de-plan', [
            'fichier' => $fichier ?? UploadedFile::fake()->image('plan.png', 400, 300),
            'points' => $points,
        ], ['Accept' => 'application/json']);
}

/** Une lecture dans A, par le membre de A. */
function lireDansA(string $chemin): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->get($chemin, ['Accept' => 'application/json']);
}

/** @return list<object> les fonds d'un jardin, du plus ancien au plus récent */
function fondsDe(string $jardin): array
{
    return DB::table('fonds_de_plan')->where('jardin_id', $jardin)->orderBy('cree_le')->orderBy('id')->get()->all();
}

it('fait d\'une image calée le fond actif, et éteint le précédent', function () {
    $premier = importerFond(array_slice(TROIS_POINTS, 0, 2))->assertCreated()->json('id');
    $this->travel(1)->seconds();
    $fichier = UploadedFile::fake()->image('plan.png', 400, 300);
    $second = importerFond(TROIS_POINTS, $fichier)
        ->assertCreated()
        ->assertJson(['nature' => 'image', 'calage' => TROIS_POINTS])
        ->json('id');

    $fonds = fondsDe($this->jardins->a->id);
    expect(array_column($fonds, 'id'))->toBe([$premier, $second])
        ->and(array_column($fonds, 'actif'))->toBe([false, true])
        ->and($fonds[1]->nature)->toBe('image')
        ->and(json_decode($fonds[1]->calage, true))->toEqual(TROIS_POINTS)
        ->and($fonds[1]->fichier)->toStartWith("photos/{$this->jardins->a->id}/plans/");

    lireDansA('/fonds-de-plan/actif')
        ->assertOk()
        ->assertExactJson(['nature' => 'image', 'url' => "/fonds-de-plan/{$second}/image", 'calage' => TROIS_POINTS]);
    $image = lireDansA("/fonds-de-plan/{$second}/image")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($image->streamedContent())->toBe((string) file_get_contents($fichier->getRealPath()));
});

it('rend un fond vide quand le jardin n\'en a pas', function () {
    lireDansA('/fonds-de-plan/actif')->assertOk()->assertExactJson(['nature' => null, 'url' => null, 'calage' => null]);
});

it('refuse un seul point, ou des coordonnées hors bornes, sans rien stocker', function (array $points, string $erreur) {
    importerFond($points)->assertUnprocessable()->assertJsonValidationErrors($erreur);

    expect(fondsDe($this->jardins->a->id))->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'un seul point' => [[TROIS_POINTS[0]], 'points'],
    'quatre points' => [[...TROIS_POINTS, TROIS_POINTS[0]], 'points'],
    'latitude hors bornes' => [[TROIS_POINTS[0], ['x' => 400, 'y' => 0, 'lng' => 4.8370, 'lat' => 95]], 'points.1.lat'],
    'longitude hors bornes' => [[TROIS_POINTS[0], ['x' => 400, 'y' => 0, 'lng' => -181, 'lat' => 45.7650]], 'points.1.lng'],
    'pixel hors de l\'image' => [[TROIS_POINTS[0], ['x' => 401, 'y' => 0, 'lng' => 4.8370, 'lat' => 45.7650]], 'points.1.x'],
    'pixel négatif' => [[TROIS_POINTS[0], ['x' => 400, 'y' => -1, 'lng' => 4.8370, 'lat' => 45.7650]], 'points.1.y'],
    'pixel à gauche de l\'image' => [[TROIS_POINTS[0], ['x' => -1, 'y' => 0, 'lng' => 4.8370, 'lat' => 45.7650]], 'points.1.x'],
    'pixel sous l\'image' => [[TROIS_POINTS[0], ['x' => 400, 'y' => 301, 'lng' => 4.8370, 'lat' => 45.7650]], 'points.1.y'],
]);

it('refuse un fichier qui n\'est pas une image', function () {
    importerFond(TROIS_POINTS, UploadedFile::fake()->createWithContent('plan.png', 'pas une image'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fichier');

    expect(fondsDe($this->jardins->a->id))->toBe([])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('laisse le fond de B actif dans B, et invisible dans A', function () {
    $fondB = importerFond(TROIS_POINTS, null, $this->jardins->proprietaireB, $this->jardins->b->id)->assertCreated()->json('id');
    importerFond(TROIS_POINTS)->assertCreated();

    expect(array_column(fondsDe($this->jardins->b->id), 'actif'))->toBe([true]);
    lireDansA("/fonds-de-plan/{$fondB}/image")->assertNotFound();
    expect(lireDansA('/fonds-de-plan/actif')->json('url'))->not->toContain($fondB);
});

it('compte l\'image du fond dans le quota du jardin, et refuse au-delà', function () {
    $fichier = UploadedFile::fake()->image('plan.png', 400, 300)->size(300);
    importerFond(TROIS_POINTS, $fichier)->assertCreated();
    $ko = (int) ceil(Storage::disk('local')->size((string) fondsDe($this->jardins->a->id)[0]->fichier) / 1024);

    app()->forgetScopedInstances();
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    expect(app(CalculerOccupation::class)->pourJardin())->toBe($ko)->toBeGreaterThan(0);

    DB::table('jardins')->where('id', $this->jardins->a->id)->update(['quota_photos_mo' => 0]);
    importerFond(TROIS_POINTS)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fichier' => 'Quota atteint : ce jardin n\'a plus de place pour ce plan.']);
    expect(fondsDe($this->jardins->a->id))->toHaveCount(1);
});
