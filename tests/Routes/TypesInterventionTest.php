<?php

// V91 (RG-03, P1, écrans B11 et T2) : la liste rend les types fournis et ceux
// du jardin, et en tête les 5 types utilisés le plus récemment dans le jardin ;
// créer un type l'ajoute au jardin. Libellé vide, famille inconnue ou libellé
// déjà présent → 422. Un type de B n'apparaît pas dans A et n'y est pas
// utilisable ; un type fourni n'est pas modifiable.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Comptes\Domaine\Utilisateur;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;
use Src\Support\Jardin\JardinCourantAbsent;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;
    $this->fournis = DB::table('types_intervention')->whereNull('jardin_id')->count();
});

/** $utilisateur, dans $jardin, comme d'une requête neuve. */
function typesDans(Utilisateur $utilisateur, string $jardin): Utilisateur
{
    app()->forgetScopedInstances();
    test()->withSession(['jardin_id' => $jardin]);

    return $utilisateur;
}

/** Un type propre à $jardin, écrit hors modèle ; rend son id. */
function typeDuJardin(string $jardin, string $libelle, string $famille = 'entretien'): int
{
    return (int) DB::table('types_intervention')->insertGetId(['jardin_id' => $jardin, 'libelle' => $libelle, 'famille' => $famille]);
}

/** Une intervention de $type sur une plante de $jardin, faite le $jour. */
function interventionDe(string $jardin, int $type, string $jour, string $saisie = '2026-10-01 08:00:00'): void
{
    app(JardinCourant::class)->positionner($jardin);
    Intervention::factory()->create([
        'plante_id' => DB::table('plantes')->where('jardin_id', $jardin)->value('id'),
        'type_intervention_id' => $type,
        'faite_le' => $jour,
        'saisie_le' => $saisie,
    ]);
    app()->forgetScopedInstances();
}

it('rend les types fournis et ceux du jardin, pas ceux de B', function () {
    $compost = typeDuJardin($this->jardins->a->id, 'Compost de feuilles', 'sol');
    typeDuJardin($this->jardins->b->id, DeuxJardins::MARQUEUR.' Taille secrète', 'taille');

    $reponse = $this->actingAs(typesDans($this->jardins->proprietaireA, $this->jardins->a->id))
        ->getJson('/types-intervention')
        ->assertOk();

    $tous = collect($reponse->json('tous'));
    expect($tous)->toHaveCount($this->fournis + 1)
        ->and($tous->firstWhere('id', $compost))->toBe(['id' => $compost, 'libelle' => 'Compost de feuilles', 'famille' => 'sol', 'fourni' => false])
        ->and($tous->firstWhere('libelle', 'Tonte'))->toMatchArray(['famille' => 'entretien', 'fourni' => true])
        ->and($reponse->json('recents'))->toBe([])
        ->and((string) $reponse->getContent())->not->toContain(DeuxJardins::MARQUEUR);
});

it('met en tête les 5 types utilisés le plus récemment dans le jardin', function () {
    $a = $this->jardins->a->id;
    $type = fn (string $libelle): int => RegleFactory::typeFourni($libelle);
    $propre = typeDuJardin($a, 'Taille des rosiers', 'taille');

    interventionDe($a, $type('Tonte'), '2026-09-01');
    interventionDe($a, $type('Arrosage'), '2026-09-20');
    interventionDe($a, $type('Paillage'), '2026-08-15');
    interventionDe($a, $propre, '2026-09-10');
    interventionDe($a, $type('Récolte'), '2026-07-01');
    interventionDe($a, $type('Compost'), '2026-06-01');
    // La tonte revient le 25/09 : la plus récente.
    interventionDe($a, $type('Tonte'), '2026-09-25');
    // Même jour que l'arrosage, saisi plus tôt : passe derrière.
    interventionDe($a, $type('Binage'), '2026-09-20', '2026-09-30 08:00:00');
    // Une intervention de B ne compte pas dans A.
    interventionDe($this->jardins->b->id, $type('Chaulage'), '2026-10-01');

    $recents = $this->actingAs(typesDans($this->jardins->membreA, $a))
        ->getJson('/types-intervention')
        ->assertOk()
        ->json('recents');

    expect(array_column($recents, 'libelle'))->toBe(['Tonte', 'Arrosage', 'Binage', 'Taille des rosiers', 'Paillage'])
        ->and($recents[3])->toBe(['id' => $propre, 'libelle' => 'Taille des rosiers', 'famille' => 'taille', 'fourni' => false]);
});

it('crée un type dans le jardin courant', function () {
    $this->actingAs(typesDans($this->jardins->proprietaireA, $this->jardins->a->id))
        ->postJson('/types-intervention', ['libelle' => '  Taille des rosiers ', 'famille' => 'taille'])
        ->assertCreated()
        ->assertJson(['libelle' => 'Taille des rosiers', 'famille' => 'taille', 'fourni' => false]);

    $type = DB::table('types_intervention')->where('libelle', 'Taille des rosiers')->sole();
    expect([$type->jardin_id, $type->famille, $type->demande_quantite, $type->demande_produit])
        ->toBe([$this->jardins->a->id, 'taille', false, false]);

    $tous = $this->actingAs(typesDans($this->jardins->proprietaireA, $this->jardins->a->id))
        ->getJson('/types-intervention')
        ->json('tous');
    expect(array_column($tous, 'libelle'))->toContain('Taille des rosiers');
});

it('refuse un libellé vide, une famille inconnue, ou un libellé déjà présent', function (array $charge, string $champ, string $message) {
    typeDuJardin($this->jardins->a->id, 'Taille des rosiers', 'taille');
    // Pris dans B, libre dans A.
    typeDuJardin($this->jardins->b->id, 'Taille des glycines', 'taille');
    $avant = DB::table('types_intervention')->count();

    $this->actingAs(typesDans($this->jardins->proprietaireA, $this->jardins->a->id))
        ->postJson('/types-intervention', $charge)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$champ => $message]);

    expect(DB::table('types_intervention')->count())->toBe($avant);
})->with([
    'libellé vide' => [['libelle' => '   ', 'famille' => 'taille'], 'libelle', 'Donnez un libellé au type.'],
    'famille inconnue' => [['libelle' => 'Greffe', 'famille' => 'greffe'], 'famille', 'Famille inconnue.'],
    'libellé d\'un type fourni' => [['libelle' => 'tonte', 'famille' => 'entretien'], 'libelle', 'Ce type existe déjà.'],
    'libellé d\'un type du jardin' => [['libelle' => 'TAILLE DES ROSIERS', 'famille' => 'taille'], 'libelle', 'Ce type existe déjà.'],
    'libellé trop long' => [['libelle' => str_repeat('a', 81), 'famille' => 'taille'], 'libelle', 'Le libellé tient en 80 caractères.'],
]);

it('accepte dans A un libellé déjà pris dans B', function () {
    typeDuJardin($this->jardins->b->id, 'Taille des glycines', 'taille');

    $this->actingAs(typesDans($this->jardins->proprietaireA, $this->jardins->a->id))
        ->postJson('/types-intervention', ['libelle' => 'Taille des glycines', 'famille' => 'taille'])
        ->assertCreated();

    expect(DB::table('types_intervention')->where('libelle', 'Taille des glycines')->pluck('jardin_id')->sort()->values()->all())
        ->toBe(collect([$this->jardins->a->id, $this->jardins->b->id])->sort()->values()->all());
});

it('ne rend pas utilisable dans A un type de B', function () {
    $typeB = typeDuJardin($this->jardins->b->id, 'Taille de B', 'taille');
    $fourni = RegleFactory::typeFourni('Tonte');

    app(JardinCourant::class)->positionner($this->jardins->a->id);
    expect(TypeIntervention::query()->find($typeB))->toBeNull()
        ->and(TypeIntervention::query()->find($fourni))->not->toBeNull();

    app(JardinCourant::class)->positionner($this->jardins->b->id);
    expect(TypeIntervention::query()->find($typeB)?->libelle)->toBe('Taille de B');
});

it('ne lit aucun type sans jardin courant', function () {
    app()->forgetScopedInstances();

    expect(fn () => TypeIntervention::query()->count())->toThrow(JardinCourantAbsent::class);
});

it('n\'offre aucune route pour modifier ou supprimer un type', function (string $methode) {
    $fourni = RegleFactory::typeFourni('Tonte');
    $avant = DB::table('types_intervention')->where('id', $fourni)->sole();

    $statut = $this->actingAs(typesDans($this->jardins->proprietaireA, $this->jardins->a->id))
        ->json($methode, "/types-intervention/{$fourni}", ['libelle' => 'Tondre', 'famille' => 'entretien'])
        ->getStatusCode();

    expect($statut)->toBeIn([404, 405])
        ->and(DB::table('types_intervention')->where('id', $fourni)->sole())->toEqual($avant);
})->with(['PUT', 'PATCH', 'DELETE']);

it('refuse la création d\'un type à un membre', function () {
    $this->actingAs(typesDans($this->jardins->membreA, $this->jardins->a->id))
        ->postJson('/types-intervention', ['libelle' => 'Taille des rosiers', 'famille' => 'taille'])
        ->assertForbidden();

    expect(DB::table('types_intervention')->where('libelle', 'Taille des rosiers')->exists())->toBeFalse();
});
