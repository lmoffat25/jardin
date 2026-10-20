<?php

// V23 : le jeu de référence crée les 5 profils (CDC §5.4) et les types fournis
// (CDC §3.7) ; lancé deux fois, il laisse exactement les mêmes lignes. L'unicité
// des libellés vaut parmi les types fournis et dans chaque jardin (D-38).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{profils: list<array<string, mixed>>, types: list<array<string, mixed>>} */
function referenceEnBase(): array
{
    return [
        'profils' => DB::table('profils_climatiques')->orderBy('id')->get()->map(fn ($l) => (array) $l)->all(),
        'types' => DB::table('types_intervention')->orderBy('id')->get()->map(fn ($l) => (array) $l)->all(),
    ];
}

it('crée les cinq profils avec les décalages du CDC §5.4, dans l\'ordre', function () {
    $this->seed(ReferenceSeeder::class);

    expect(DB::table('profils_climatiques')->orderBy('ordre_affichage')->pluck('decalage_jours', 'libelle')->all())
        ->toBe(['Très doux' => -15, 'Doux' => -7, 'Tempéré' => 0, 'Frais' => 7, 'Montagne' => 21]);
});

it('crée les types fournis du CDC §3.7, sans jardin', function () {
    $this->seed(ReferenceSeeder::class);

    $types = DB::table('types_intervention')->whereNull('jardin_id');
    expect($types->count())->toBe(28)
        ->and(DB::table('types_intervention')->whereNotNull('jardin_id')->count())->toBe(0)
        ->and($types->clone()->where('libelle', 'Taille de formation')->value('famille'))->toBe('taille')
        ->and($types->clone()->where('libelle', 'Arrosage')->value('famille'))->toBe('eau')
        ->and($types->clone()->where('libelle', 'Récolte')->value('famille'))->toBe('recolte')
        ->and($types->clone()->distinct()->pluck('famille')->sort()->values()->all())
        ->toBe(['cycle', 'eau', 'entretien', 'recolte', 'sol', 'taille', 'traitement']);
});

it('laisse les mêmes lignes et les mêmes id quand il est lancé deux fois', function () {
    $this->seed(ReferenceSeeder::class);
    $premier = referenceEnBase();

    $this->seed(ReferenceSeeder::class);

    expect(referenceEnBase())->toBe($premier);
});

it('refuse un second type fourni de même libellé', function () {
    $this->seed(ReferenceSeeder::class);

    refusee(fn () => DB::table('types_intervention')->insert(
        ['jardin_id' => null, 'libelle' => 'Arrosage', 'famille' => 'eau'],
    ));
});

it('accepte un même libellé dans deux jardins, et le refuse deux fois dans un jardin', function () {
    $premier = nouveauJardin();
    $second = nouveauJardin();
    $type = fn (string $jardin) => ['jardin_id' => $jardin, 'libelle' => 'Taille des rosiers', 'famille' => 'taille'];

    DB::table('types_intervention')->insert($type($premier));
    DB::table('types_intervention')->insert($type($second));
    expect(DB::table('types_intervention')->where('libelle', 'Taille des rosiers')->count())->toBe(2);

    refusee(fn () => DB::table('types_intervention')->insert($type($premier)));
});
