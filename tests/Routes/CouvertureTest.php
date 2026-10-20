<?php

// V119 (RG-35, critère 14, écrans B6, B5, T3) : une photo de la plante, ou
// d'une de ses interventions, devient sa couverture ; les listes rendent
// l'adresse de sa miniature, ou de l'original tant que les dérivées ne sont
// pas prêtes. Une photo d'une autre plante est refusée. Depuis A, une photo
// ou une plante de B rend 404 ou une erreur, et rien ne change. En base :
// `plantes.photo_couverture_id`.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Journal\Domaine\Photo;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-14');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;

    $this->plante = DB::table('plantes')->where('nom', 'A Plante Potager 1')->value('id');
    $this->autre = DB::table('plantes')->where('nom', 'A Plante Verger 1')->value('id');
    $this->planteB = DB::table('plantes')->where('nom', DeuxJardins::MARQUEUR.' Plante Potager 1')->value('id');
});

/**
 * Une photo posée dans un jardin, sans fichier ; rend son id.
 *
 * @param  array<string, mixed>  $attributs
 */
function photoDans(string $jardin, array $attributs): string
{
    app()->forgetScopedInstances();
    app(JardinCourant::class)->positionner($jardin);
    $id = Photo::factory()->create($attributs)->id;
    app()->forgetScopedInstances();

    return $id;
}

/** Choisit la couverture d'une plante, par le propriétaire de A. */
function couvrir(string $plante, mixed $photo): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->proprietaireA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->putJson("/plantes/{$plante}/couverture", ['photo_id' => $photo]);
}

/** La miniature de la plante dans la liste des plantes de A. */
function miniatureListee(string $plante): mixed
{
    app()->forgetScopedInstances();
    $props = test()->withoutVite()
        ->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->get('/plantes')
        ->assertOk()
        ->viewData('page')['props'];

    return collect($props['plantes'])->firstWhere('id', $plante)['miniature'];
}

function couverture(string $plante): ?string
{
    return DB::table('plantes')->where('id', $plante)->value('photo_couverture_id');
}

it('fait d\'une photo de la plante sa couverture, l\'original tant que les dérivées manquent', function () {
    $photo = photoDans($this->jardins->a->id, ['plante_id' => $this->plante]);

    couvrir($this->plante, $photo)
        ->assertOk()
        ->assertJson(['id' => $this->plante, 'photo_couverture_id' => $photo, 'miniature' => "/photos/{$photo}/original"]);

    expect(couverture($this->plante))->toBe($photo)
        ->and(miniatureListee($this->plante))->toBe("/photos/{$photo}/original")
        ->and(miniatureListee($this->autre))->toBeNull();

    // Les dérivées prêtes, la liste rend la miniature, et la fiche aussi.
    DB::table('photos')->where('id', $photo)->update(['derivees_pretes' => true]);
    expect(miniatureListee($this->plante))->toBe("/photos/{$photo}/160");

    app()->forgetScopedInstances();
    $fiche = $this->withoutVite()->actingAs($this->jardins->membreA)
        ->withSession(['jardin_id' => $this->jardins->a->id])
        ->get("/plantes/{$this->plante}")
        ->assertOk()
        ->viewData('page')['props']['plante'];
    expect($fiche['couverture'])->toBe(['photo_id' => $photo, 'miniature' => "/photos/{$photo}/160", 'affichage' => "/photos/{$photo}/1200"]);
});

it('accepte la photo d\'une intervention de la plante', function () {
    $intervention = (string) Str::uuid();
    app()->forgetScopedInstances();
    $this->actingAs($this->jardins->membreA)
        ->withSession(['jardin_id' => $this->jardins->a->id])
        ->postJson('/interventions', [
            'type_intervention_id' => RegleFactory::typeFourni('Tonte'),
            'faite_le' => '2026-08-30',
            'lignes' => [['id' => $intervention, 'plante_id' => $this->plante]],
        ])->assertOk();
    $photo = photoDans($this->jardins->a->id, ['plante_id' => null, 'intervention_id' => $intervention]);

    couvrir($this->plante, $photo)->assertOk();

    expect(couverture($this->plante))->toBe($photo);
});

it('refuse la photo d\'une autre plante, sans rien changer', function () {
    $photo = photoDans($this->jardins->a->id, ['plante_id' => $this->autre]);

    couvrir($this->plante, $photo)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo_id' => 'Cette photo n\'est pas une photo de cette plante.']);
    couvrir($this->plante, 'pas-un-uuid')->assertUnprocessable()->assertJsonValidationErrors('photo_id');

    expect(couverture($this->plante))->toBeNull();
});

it('ne laisse toucher ni à une plante ni à une photo de B depuis A', function () {
    $photoB = photoDans($this->jardins->b->id, ['plante_id' => $this->planteB]);
    $photoA = photoDans($this->jardins->a->id, ['plante_id' => $this->plante]);
    $avant = DeuxJardins::empreinte($this->jardins->b->id);

    couvrir($this->planteB, $photoA)->assertNotFound();
    couvrir($this->plante, $photoB)->assertUnprocessable()->assertJsonValidationErrors('photo_id');

    expect(couverture($this->plante))->toBeNull()
        ->and(couverture($this->planteB))->toBeNull()
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avant);
});
