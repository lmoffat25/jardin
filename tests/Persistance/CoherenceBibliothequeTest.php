<?php

// V68 : la cohérence agronomique de la bibliothèque (B-T1, B-T2, B-T3, protocole
// §3). Chaque contrôle tourne sur les fichiers du dépôt, projetés dans les cinq
// profils (B8), et rougit sur un témoin fautif. Un contrôle qui ne trouve
// aucune espèce à contrôler échoue : un contrôle vide ne prouve rien.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LectureBibliotheque;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

/**
 * Chaque fenêtre de taille des espèces retenues, projetée dans chaque profil
 * et chaque année : `$jour` dit si un jour est interdit à l'espèce.
 *
 * @param  Closure(array<string, mixed>): bool  $retenue
 * @param  Closure(array<string, mixed>, DateTimeImmutable): bool  $interdit
 * @return list<string> les fautes, ou $absente quand aucune espèce n'est retenue
 */
function taillesInterdites(string $dossier, string $absente, Closure $retenue, Closure $interdit, bool $projeter = true): array
{
    [$especes, $types] = LectureBibliotheque::lire($dossier);
    $retenues = array_values(array_filter($especes, $retenue));
    if ($retenues === []) {
        return [$absente];
    }
    $profils = $projeter ? LectureBibliotheque::profils() : ['Tempéré' => 0];

    $fautes = [];
    foreach ($retenues as $espece) {
        foreach ($espece['regles'] as $regle) {
            if ($types[$regle['type_intervention']] !== 'taille') {
                continue;
            }
            foreach ($regle['parametres']['fenetres'] as $k => $fenetre) {
                foreach ($profils as $profil => $decalage) {
                    foreach (LectureBibliotheque::ANNEES as $annee) {
                        foreach (LectureBibliotheque::jours(LectureBibliotheque::projeter($fenetre, $annee, $decalage)) as $jour) {
                            if ($interdit($espece, $jour)) {
                                $fautes[] = "{$regle['cle']}, fenêtre {$k}, profil {$profil} : ouverte le {$jour->format('d/m/Y')}";

                                continue 3;
                            }
                        }
                    }
                }
            }
        }
    }

    return $fautes;
}

/** B-T1 : un fruitier à noyau (genre Prunus) ; le pêcher et le nectarinier (Prunus persica) seuls taillés en février (Q20). */
function noyauxTaillesEnHiver(string $dossier): array
{
    return taillesInterdites(
        $dossier,
        'aucun fruitier à noyau trouvé',
        fn (array $e) => $e['colonnes']['categorie'] === 'fruitier' && str_starts_with((string) ($e['colonnes']['nom_latin'] ?? ''), 'Prunus '),
        fn (array $e, DateTimeImmutable $jour) => LectureBibliotheque::entre($jour, '01/12', '31/01')
            || (LectureBibliotheque::entre($jour, '01/02', '29/02') && ! str_starts_with((string) $e['colonnes']['nom_latin'], 'Prunus persica')),
    );
}

/** B-T2 : une haie, sur les valeurs écrites (B-T14 les projette). */
function haiesTailleesEnNidification(string $dossier): array
{
    return taillesInterdites(
        $dossier,
        'aucune haie trouvée',
        fn (array $e) => $e['colonnes']['categorie'] === 'haie',
        fn (array $e, DateTimeImmutable $jour) => LectureBibliotheque::entre($jour, '16/03', '15/08'),
        projeter: false,
    );
}

/**
 * B-T3 : un arbuste qui fleurit au printemps n'est pas taillé l'hiver qui
 * précède, du 01/11 à la fin février (D-95) : la taille hivernale supprime la
 * floraison de l'année.
 */
function arbustesPrintaniersTaillesAvantFloraison(string $dossier): array
{
    return taillesInterdites(
        $dossier,
        'aucun arbuste à floraison printanière trouvé',
        fn (array $e) => $e['colonnes']['categorie'] === 'arbuste' && ($e['colonnes']['periode_floraison'] ?? null) === 'printemps',
        fn (array $e, DateTimeImmutable $jour) => LectureBibliotheque::entre($jour, '01/11', '29/02'),
    );
}

/**
 * Une espèce témoin de la catégorie, une taille indicative à une fenêtre.
 *
 * @param  array<string, mixed>  $fenetre
 * @return array<string, mixed>
 */
function especeTaillee(string $cle, string $categorie, array $colonnes, array $fenetre, string $type = 'Taille d\'entretien/fructification'): array
{
    return [
        'cle' => $cle, 'nom_commun' => "Espèce {$cle}", 'categorie' => $categorie, ...$colonnes,
        'regles' => [[
            'cle' => "{$cle}-taille", 'type_intervention' => $type, 'type_regle' => 'R2', 'confiance' => 'indicative',
            'sources' => [['url' => "https://vulgarisation.exemple.test/{$cle}", 'libelle' => 'Fiche témoin', 'consultee_le' => '2026-10-20']],
            'fenetres' => [$fenetre], 'periodicite_annees' => 1,
        ]],
    ];
}

it('ne taille aucun fruitier à noyau en décembre ni en janvier, ni en février hors pêcher (B-T1)', function () {
    expect(noyauxTaillesEnHiver(base_path('bibliotheque')))->toBe([]);
});

it('ne taille aucune haie du 16/03 au 15/08 (B-T2)', function () {
    expect(haiesTailleesEnNidification(base_path('bibliotheque')))->toBe([]);
});

it('ne taille aucun arbuste à floraison printanière avant sa floraison (B-T3)', function () {
    expect(arbustesPrintaniersTaillesAvantFloraison(base_path('bibliotheque')))->toBe([]);
});

it('charge la bibliothèque du dépôt sur une base vide', function () {
    $this->artisan('bibliotheque:charger')->assertSuccessful();

    expect(DB::table('especes')->count())->toBe(count(LectureBibliotheque::lire(base_path('bibliotheque'))[0]));
});

it('rougit sur un témoin fautif', function (Closure $controle, array $especes, string $faute) {
    expect($controle(TemoinBibliotheque::dossier(['temoins' => $especes])))->toContain($faute);
})->with([
    'B-T1 sans fruitier à noyau' => [
        noyauxTaillesEnHiver(...),
        [especeTaillee('pepins', 'fruitier', ['nom_latin' => 'Malus exemplaris'], ['debut' => '01/12', 'fin' => '31/03'])],
        'aucun fruitier à noyau trouvé',
    ],
    'B-T1 noyau taillé en janvier' => [
        noyauxTaillesEnHiver(...),
        [especeTaillee('noyau', 'fruitier', ['nom_latin' => 'Prunus exemplaris'], ['debut' => '15/01', 'fin' => '15/03'])],
        'noyau-taille, fenêtre 0, profil Très doux : ouverte le 31/12/2025',
    ],
    'B-T1 noyau taillé en février' => [
        noyauxTaillesEnHiver(...),
        [especeTaillee('noyau', 'fruitier', ['nom_latin' => 'Prunus exemplaris'], ['debut' => '20/02', 'fin' => '31/03'])],
        'noyau-taille, fenêtre 0, profil Très doux : ouverte le 05/02/2026',
    ],
    'B-T1 pêcher taillé en janvier' => [
        noyauxTaillesEnHiver(...),
        [especeTaillee('pecher', 'fruitier', ['nom_latin' => 'Prunus persica exemplaris'], ['debut' => '15/02', 'fin' => '31/03'])],
        'pecher-taille, fenêtre 0, profil Très doux : ouverte le 31/01/2026',
    ],
    'B-T2 sans haie' => [
        haiesTailleesEnNidification(...),
        [especeTaillee('arbuste', 'arbuste', [], ['debut' => '01/11', 'fin' => '15/03'])],
        'aucune haie trouvée',
    ],
    'B-T2 haie taillée en avril' => [
        haiesTailleesEnNidification(...),
        [especeTaillee('haie', 'haie', [], ['debut' => '01/03', 'fin' => '15/04'], 'Taille de haie')],
        'haie-taille, fenêtre 0, profil Tempéré : ouverte le 16/03/2026',
    ],
    'B-T3 sans arbuste printanier' => [
        arbustesPrintaniersTaillesAvantFloraison(...),
        [especeTaillee('estival', 'arbuste', ['periode_floraison' => 'été'], ['debut' => '01/02', 'fin' => '31/03'])],
        'aucun arbuste à floraison printanière trouvé',
    ],
    'B-T3 printanier taillé en hiver' => [
        arbustesPrintaniersTaillesAvantFloraison(...),
        [especeTaillee('printanier', 'arbuste', ['periode_floraison' => 'printemps'], ['debut' => '10/03', 'fin' => '30/04'])],
        'printanier-taille, fenêtre 0, profil Très doux : ouverte le 23/02/2026',
    ],
]);
