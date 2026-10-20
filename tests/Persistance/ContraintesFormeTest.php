<?php

// V30 : la base refuse un butoir donné à moitié (RG-44), une photo orpheline,
// un fond de plan `image` sans fichier ou sans calage, et deux fonds actifs
// dans un même jardin (chap. 4 §3.5 et §3 bis).

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Une fenêtre du 1er février au 31 mars, prête à insérer pour la règle.
 *
 * @param  array<string, mixed>  $champs
 * @return array<string, mixed>
 */
function fenetre(string $regle, array $champs = []): array
{
    return $champs + [
        'id' => (string) Str::uuid(),
        'jardin_id' => DB::table('regles')->where('id', $regle)->value('jardin_id'),
        'regle_id' => $regle,
        'debut_jour' => 1, 'debut_mois' => 2,
        'fin_jour' => 31, 'fin_mois' => 3,
    ];
}

/**
 * Une photo du jardin, prête à insérer.
 *
 * @param  array<string, mixed>  $champs
 * @return array<string, mixed>
 */
function photo(string $jardin, array $champs = []): array
{
    return $champs + [
        'id' => (string) Str::uuid(),
        'jardin_id' => $jardin,
        'empreinte' => str_repeat('a', 64),
        'chemin_original' => 'photos/temoin.jpg',
        'taille_ko' => 120,
    ];
}

/**
 * Un fond de plan du jardin, prêt à insérer : une image calée, inactive.
 *
 * @param  array<string, mixed>  $champs
 * @return array<string, mixed>
 */
function fond(string $jardin, array $champs = []): array
{
    return $champs + [
        'id' => (string) Str::uuid(),
        'jardin_id' => $jardin,
        'nature' => 'image',
        'fichier' => 'plans/temoin.png',
        'calage' => '[]',
    ];
}

it('refuse un butoir donné à moitié', function (array $moitie) {
    $regle = nouvelleRegle(nouveauJardin());
    DB::table('fenetres')->insert(fenetre($regle, [
        'butoir_debut_jour' => 15, 'butoir_debut_mois' => 1,
        'butoir_fin_jour' => 15, 'butoir_fin_mois' => 4,
    ]));

    refusee(fn () => DB::table('fenetres')->insert(fenetre($regle, $moitie)));
})->with([
    'début sans mois' => [['butoir_debut_jour' => 15]],
    'début sans jour' => [['butoir_debut_mois' => 1]],
    'fin sans mois' => [['butoir_fin_jour' => 15]],
    'fin sans jour' => [['butoir_fin_mois' => 4]],
]);

it('refuse une photo rattachée ni à une plante ni à une intervention', function () {
    $jardin = nouveauJardin();
    DB::table('photos')->insert(photo($jardin, ['plante_id' => nouvellePlante($jardin)]));

    refusee(fn () => DB::table('photos')->insert(photo($jardin)));
});

it('refuse un fond de plan image sans fichier ou sans calage', function (string $manquant) {
    $jardin = nouveauJardin();
    DB::table('fonds_de_plan')->insert(fond($jardin));
    DB::table('fonds_de_plan')->insert(fond($jardin, ['nature' => 'orthophoto', 'fichier' => null, 'calage' => null]));

    refusee(fn () => DB::table('fonds_de_plan')->insert(fond($jardin, [$manquant => null])));
})->with(['fichier', 'calage']);

it('refuse un second fond de plan actif dans un même jardin', function () {
    $jardin = nouveauJardin();
    DB::table('fonds_de_plan')->insert(fond($jardin, ['actif' => true]));
    DB::table('fonds_de_plan')->insert(fond($jardin));
    DB::table('fonds_de_plan')->insert(fond(nouveauJardin(), ['actif' => true]));

    refusee(fn () => DB::table('fonds_de_plan')->insert(fond($jardin, ['actif' => true])));
});
