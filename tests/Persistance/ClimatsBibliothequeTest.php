<?php

// V69 : la bibliothèque tient dans les cinq climats (B-T12, B-T13, B-T14, B7,
// B8). Chaque fenêtre passe par la projection du moteur (Fenetre::projeter),
// pour chaque profil du jeu de référence et trois années de début, pas par une
// copie du calcul. Chaque contrôle rougit sur un témoin fautif.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LectureBibliotheque;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

/**
 * Chaque fenêtre R2 des espèces retenues, et le nom qui la désigne.
 *
 * @param  Closure(array<string, mixed>): bool  $retenue
 * @return array{0: list<array{0: string, 1: array{debut: string, fin: string, optimal: list<string>|null, butoirs: list<string|null>|null, libelle: string|null}}>, 1: int}
 */
function fenetresRetenues(string $dossier, Closure $retenue): array
{
    [$especes] = LectureBibliotheque::lire($dossier);
    $retenues = array_values(array_filter($especes, $retenue));
    $fenetres = [];
    foreach ($retenues as $espece) {
        foreach ($espece['regles'] as $regle) {
            foreach ($regle['parametres']['fenetres'] as $k => $fenetre) {
                $fenetres[] = ["{$regle['cle']}, fenêtre {$k}", $fenetre];
            }
        }
    }

    return [$fenetres, count($retenues)];
}

/** @param array<string, mixed> $espece */
function estHaie(array $espece): bool
{
    return $espece['colonnes']['categorie'] === 'haie';
}

/** B-T12 : toute fenêtre de haie porte le butoir de début 16/08 et celui de fin 15/03 (RG-44). */
function haiesSansButoirs(string $dossier): array
{
    [$fenetres, $haies] = fenetresRetenues($dossier, estHaie(...));
    if ($haies === 0) {
        return ['aucune haie trouvée'];
    }
    $fautes = [];
    foreach ($fenetres as [$nom, $fenetre]) {
        if ($fenetre['butoirs'] !== ['16/08', '15/03']) {
            $fautes[] = "{$nom} : butoirs ".json_encode($fenetre['butoirs']);
        }
    }

    return $fautes;
}

/** B-T13 : projetée puis rognée, toute fenêtre dure au moins 14 jours et garde son optimale. */
function fenetresRogneesATort(string $dossier): array
{
    [$fenetres] = fenetresRetenues($dossier, fn () => true);
    if ($fenetres === []) {
        return ['aucune fenêtre trouvée'];
    }
    $fautes = [];
    foreach ($fenetres as [$nom, $fenetre]) {
        foreach (LectureBibliotheque::profils() as $profil => $decalage) {
            foreach (LectureBibliotheque::ANNEES as $annee) {
                $periode = LectureBibliotheque::projeter($fenetre, $annee, $decalage);
                $jours = $periode->estVide() ? 0 : $periode->debut->diff($periode->fin)->days + 1;
                if ($jours < 14) {
                    $fautes[] = "{$nom}, profil {$profil}, {$annee} : {$jours} jours";
                } elseif ($fenetre['optimal'] !== null && $periode->optimalDebut === null) {
                    $fautes[] = "{$nom}, profil {$profil}, {$annee} : optimale perdue";
                }
            }
        }
    }

    return $fautes;
}

/** B-T14 : aucune fenêtre de haie projetée ne touche le 16/03 → 15/08, dans aucun profil. */
function haiesProjeteesEnNidification(string $dossier): array
{
    [$fenetres, $haies] = fenetresRetenues($dossier, estHaie(...));
    if ($haies === 0) {
        return ['aucune haie trouvée'];
    }
    $fautes = [];
    foreach ($fenetres as [$nom, $fenetre]) {
        foreach (LectureBibliotheque::profils() as $profil => $decalage) {
            foreach (LectureBibliotheque::ANNEES as $annee) {
                foreach (LectureBibliotheque::jours(LectureBibliotheque::projeter($fenetre, $annee, $decalage)) as $jour) {
                    if (LectureBibliotheque::entre($jour, '16/03', '15/08')) {
                        $fautes[] = "{$nom}, profil {$profil} : ouverte le {$jour->format('d/m/Y')}";
                        break;
                    }
                }
            }
        }
    }

    return $fautes;
}

/**
 * Une haie témoin, une taille indicative à une fenêtre.
 *
 * @param  array<string, mixed>  $fenetre
 * @return array<string, mixed>
 */
function haieTemoin(array $fenetre): array
{
    return [
        'cle' => 'haie', 'nom_commun' => 'Haie témoin', 'categorie' => 'haie',
        'regles' => [[
            'cle' => 'haie-taille', 'type_intervention' => 'Taille de haie', 'type_regle' => 'R2', 'confiance' => 'indicative',
            'sources' => [['url' => 'https://vulgarisation.exemple.test/haie', 'libelle' => 'Fiche témoin', 'consultee_le' => '2026-10-20']],
            'fenetres' => [$fenetre], 'periodicite_annees' => 1,
        ]],
    ];
}

it('borne toute haie aux butoirs 16/08 et 15/03 (B-T12)', function () {
    expect(haiesSansButoirs(base_path('bibliotheque')))->toBe([]);
});

it('garde à toute fenêtre 14 jours et son optimale dans les cinq profils (B-T13)', function () {
    expect(fenetresRogneesATort(base_path('bibliotheque')))->toBe([]);
});

it('ne propose aucune haie du 16/03 au 15/08 dans les cinq profils (B-T14)', function () {
    expect(haiesProjeteesEnNidification(base_path('bibliotheque')))->toBe([]);
});

it('rougit sur un témoin fautif', function (Closure $controle, array $especes, string $faute) {
    expect($controle(TemoinBibliotheque::dossier(['temoins' => $especes])))->toContain($faute);
})->with([
    'B-T12 sans haie' => [haiesSansButoirs(...), [TemoinBibliotheque::fruitier()], 'aucune haie trouvée'],
    'B-T12 haie sans butoirs' => [
        haiesSansButoirs(...),
        [haieTemoin(['debut' => '16/08', 'fin' => '30/09'])],
        'haie-taille, fenêtre 0 : butoirs null',
    ],
    'B-T13 rognée sous 14 jours' => [
        fenetresRogneesATort(...),
        [haieTemoin(['debut' => '01/08', 'fin' => '31/08', 'butoirs' => ['16/08', '15/03']])],
        'haie-taille, fenêtre 0, profil Très doux, 2026 : 1 jours',
    ],
    'B-T13 optimale perdue' => [
        fenetresRogneesATort(...),
        [haieTemoin(['debut' => '16/08', 'fin' => '30/09', 'optimal' => ['16/08', '20/08'], 'butoirs' => ['16/08', '15/03']])],
        'haie-taille, fenêtre 0, profil Très doux, 2026 : optimale perdue',
    ],
    'B-T14 butoir de fin trop tardif' => [
        haiesProjeteesEnNidification(...),
        [haieTemoin(['debut' => '01/10', 'fin' => '15/03', 'butoirs' => ['16/08', '31/03']])],
        'haie-taille, fenêtre 0, profil Frais : ouverte le 16/03/2027',
    ],
]);
