<?php

// V33 : le filtre par jardin s'applique sans qu'on l'écrive (D1, RG-21,
// critère 12). Depuis A, aucune lecture ne rend une ligne de B ; une création
// sans `jardin_id` reçoit A ; une écriture vers B lève.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Comptes\Domaine\Jardin;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Src\Support\Jardin\JardinCourantAbsent;

uses(RefreshDatabase::class)->group('critere-12');

/**
 * Deux jardins, une zone et une plante dans chacun ; B est créé d'abord, puis
 * A reste positionné.
 *
 * @return array{a: Jardin, b: Jardin, zoneA: Zone, zoneB: Zone, planteA: Plante, planteB: Plante}
 */
function deuxJardins(): array
{
    test()->seed(ReferenceSeeder::class);
    $courant = app(JardinCourant::class);
    [$a, $b] = Jardin::factory()->count(2)->create()->all();

    $courant->positionner($b->id);
    $zoneB = Zone::factory()->create();
    $planteB = Plante::factory()->for($zoneB)->create();

    $courant->positionner($a->id);
    $zoneA = Zone::factory()->create();
    $planteA = Plante::factory()->for($zoneA)->create();

    return compact('a', 'b', 'zoneA', 'zoneB', 'planteA', 'planteB');
}

it('ne liste que les zones et les plantes du jardin courant', function () {
    ['zoneA' => $zoneA, 'planteA' => $planteA] = deuxJardins();

    expect(Zone::pluck('id')->all())->toBe([$zoneA->id]);
    expect(Plante::pluck('id')->all())->toBe([$planteA->id]);
    expect(Plante::count())->toBe(1);
});

it('ne trouve pas une ligne de B par son identifiant', function () {
    ['zoneB' => $zoneB, 'planteB' => $planteB] = deuxJardins();

    expect(Zone::find($zoneB->id))->toBeNull();
    expect(Plante::find($planteB->id))->toBeNull();
    expect(Plante::whereKey($planteB->id)->exists())->toBeFalse();
});

it('ne rend rien de B par une relation', function () {
    ['a' => $a, 'zoneA' => $zoneA, 'zoneB' => $zoneB, 'planteA' => $planteA] = deuxJardins();

    expect($zoneA->plantes->pluck('id')->all())->toBe([$planteA->id]);
    // La zone de B, chargée sans le filtre, ne rend pas ses plantes depuis A.
    $zoneDeB = Zone::withoutGlobalScopes()->findOrFail($zoneB->id);
    expect($zoneDeB->plantes)->toBeEmpty();
    expect($a->zones->pluck('id')->all())->toBe([$zoneA->id]);
});

it('donne le jardin courant à une création sans jardin_id', function () {
    ['a' => $a] = deuxJardins();

    $zone = Zone::create(['nom' => 'Verger']);

    expect($zone->jardin_id)->toBe($a->id);
    expect(Zone::withoutGlobalScopes()->findOrFail($zone->id)->jardin_id)->toBe($a->id);
});

it('refuse une création vers un autre jardin que le courant', function () {
    ['b' => $b] = deuxJardins();

    expect(fn () => Zone::create(['nom' => 'Intruse', 'jardin_id' => $b->id]))->toThrow(LogicException::class);
    expect(Zone::withoutGlobalScopes()->where('nom', 'Intruse')->exists())->toBeFalse();
});

it('lève à la lecture sans jardin courant', function () {
    deuxJardins();
    app()->forgetScopedInstances();

    expect(fn () => Zone::all())->toThrow(JardinCourantAbsent::class);
    expect(fn () => Plante::count())->toThrow(JardinCourantAbsent::class);
});

it('lève à la création sans jardin courant', function () {
    deuxJardins();
    app()->forgetScopedInstances();

    expect(fn () => Zone::create(['nom' => 'Orpheline']))->toThrow(JardinCourantAbsent::class);
});
