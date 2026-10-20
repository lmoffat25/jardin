<?php

// V28 : une seule règle active par plante et par type ; les versions retirées
// restent à côté (S15, D10). V29 : une échéance en base est matérialisée, porte
// son explication, et n'existe qu'une fois par occurrence (S10, S16, RG-06).

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Une ligne d'échéance prête à insérer pour la règle.
 *
 * @param  array<string, mixed>  $champs
 * @return array<string, mixed>
 */
function echeance(string $regle, array $champs = []): array
{
    $ligne = DB::table('regles')->where('id', $regle)->first(['jardin_id', 'plante_id']);

    return $champs + [
        'id' => (string) Str::uuid(),
        'jardin_id' => $ligne->jardin_id,
        'regle_id' => $regle,
        'plante_id' => $ligne->plante_id,
        'due_le' => '2026-03-15',
        'etat' => 'manquee',
        'explication' => 'Taille annuelle, manquée le 15 mars.',
    ];
}

it('refuse une seconde règle active pour une même plante et un même type', function () {
    $jardin = nouveauJardin();
    $plante = nouvellePlante($jardin);
    $type = nouveauType($jardin);
    DB::table('regles')->insert(regle($jardin, $plante, $type));

    refusee(fn () => DB::table('regles')->insert(regle($jardin, $plante, $type, ['version' => 2])));
});

it('accepte une version retirée à côté de la version active', function () {
    $jardin = nouveauJardin();
    $plante = nouvellePlante($jardin);
    $type = nouveauType($jardin);
    DB::table('regles')->insert(regle($jardin, $plante, $type, ['est_active' => false, 'remplacee_le' => now()]));
    DB::table('regles')->insert(regle($jardin, $plante, $type, ['version' => 2]));
    DB::table('regles')->insert(regle($jardin, $plante, nouveauType($jardin)));

    expect(DB::table('regles')->where('plante_id', $plante)->count())->toBe(3);
});

it('refuse une règle active qui porte remplacee_le', function () {
    $jardin = nouveauJardin();

    refusee(fn () => DB::table('regles')->insert(
        regle($jardin, nouvellePlante($jardin), nouveauType($jardin), ['remplacee_le' => now()]),
    ));
});

it('refuse une règle dont l\'origine est inconnue, et une règle sans origine', function () {
    $jardin = nouveauJardin();
    $plante = nouvellePlante($jardin);
    $sansOrigine = regle($jardin, $plante, nouveauType($jardin));
    unset($sansOrigine['origine']);

    refusee(fn () => DB::table('regles')->insert(regle($jardin, $plante, nouveauType($jardin), ['origine' => 'importee'])));
    refusee(fn () => DB::table('regles')->insert($sansOrigine));
    foreach (['bibliotheque', 'ajustee', 'libre'] as $origine) {
        DB::table('regles')->insert(regle($jardin, $plante, nouveauType($jardin), ['origine' => $origine]));
    }
    expect(DB::table('regles')->count())->toBe(3);
});

it('refuse une échéance dans un état calculé', function (string $etat) {
    $regle = nouvelleRegle(nouveauJardin());

    refusee(fn () => DB::table('echeances')->insert(echeance($regle, ['etat' => $etat])));
})->with(['a_venir', 'ouverte', 'optimale', 'urgente']);

it('accepte une échéance manquée, suspendue ou soldée', function () {
    $regle = nouvelleRegle(nouveauJardin());
    foreach (['manquee' => '2024-03-15', 'suspendue' => '2025-03-15', 'soldee' => '2026-03-15'] as $etat => $due) {
        DB::table('echeances')->insert(echeance($regle, ['etat' => $etat, 'due_le' => $due]));
    }

    expect(DB::table('echeances')->where('regle_id', $regle)->count())->toBe(3);
});

it('refuse une échéance sans explication', function () {
    $regle = nouvelleRegle(nouveauJardin());

    refusee(fn () => DB::table('echeances')->insert(echeance($regle, ['explication' => null])));
});

it('refuse deux échéances pour une même règle à la même date', function () {
    $regle = nouvelleRegle(nouveauJardin());
    DB::table('echeances')->insert(echeance($regle));
    DB::table('echeances')->insert(echeance($regle, ['due_le' => '2027-03-15']));

    refusee(fn () => DB::table('echeances')->insert(echeance($regle, ['etat' => 'soldee'])));
});
