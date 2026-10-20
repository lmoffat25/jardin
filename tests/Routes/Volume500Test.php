<?php

// V79 (RG-19, O7) : à 500 plantes et 1 500 règles, le tableau de bord rend sa
// réponse avec le même nombre de requêtes qu'à 20 plantes, et sous le seuil de
// temps écrit dans `decisions-agents.md` (D-100). Lancé par `make balayage`,
// hors de la suite rapide (D-25).

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;

uses(RefreshDatabase::class)->group('volume', 'critere-11');

/** Le seuil du tableau de bord à 500 plantes, en millisecondes (D-100). */
const SEUIL_TABLEAU_MS = 1000;

/**
 * Un jardin de $n plantes dans dix zones, chacune à trois règles : une taille
 * R2 faite le 01/03/2026, un cuivre R2, une tonte R1 ; écrit en masse, hors
 * modèles. Rend son membre.
 */
function jardinEnVolume(int $n): Utilisateur
{
    $membre = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $membre->id]);
    Adhesion::create(['utilisateur_id' => $membre->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
    $types = ['taille' => RegleFactory::typeFourni('Taille d\'entretien/fructification'), 'cuivre' => RegleFactory::typeFourni('Traitement cuivre'), 'tonte' => RegleFactory::typeFourni('Tonte')];
    $j = ['jardin_id' => $jardin->id];

    $zones = array_map(fn (int $z) => $j + ['id' => (string) Str::uuid(), 'nom' => "Zone {$z}"], range(1, 10));
    DB::table('zones')->insert($zones);

    $lignes = ['plantes' => [], 'regles' => [], 'fenetres' => [], 'intervalles_saisonniers' => [], 'interventions' => []];
    foreach (range(1, $n) as $p) {
        $plante = (string) Str::uuid();
        $lignes['plantes'][] = $j + ['id' => $plante, 'zone_id' => $zones[$p % 10]['id'], 'code_court' => sprintf('VOL-%04d', $p), 'nom' => "Plante {$p}"];
        $regle = fn (string $type, array $champs) => $j + $champs + ['id' => (string) Str::uuid(), 'plante_id' => $plante, 'type_intervention_id' => $types[$type], 'confiance' => 'verifiee', 'origine' => 'bibliotheque'];
        $lignes['regles'][] = $taille = $regle('taille', ['type_regle' => 'R2', 'periodicite_annees' => 1, 'intervalle_jours' => null]);
        $lignes['regles'][] = $cuivre = $regle('cuivre', ['type_regle' => 'R2', 'periodicite_annees' => 1, 'intervalle_jours' => null]);
        $lignes['regles'][] = $tonte = $regle('tonte', ['type_regle' => 'R1', 'periodicite_annees' => null, 'intervalle_jours' => 7]);
        foreach ([[$taille, 1, 2, 31, 3], [$cuivre, 1, 11, 15, 12]] as [$r, $dj, $dm, $fj, $fm]) {
            $lignes['fenetres'][] = $j + ['id' => (string) Str::uuid(), 'regle_id' => $r['id'], 'debut_jour' => $dj, 'debut_mois' => $dm, 'fin_jour' => $fj, 'fin_mois' => $fm];
        }
        $lignes['intervalles_saisonniers'][] = $j + ['id' => (string) Str::uuid(), 'regle_id' => $tonte['id'], 'mois_debut' => 3, 'mois_fin' => 10, 'intervalle_jours' => 7];
        $lignes['interventions'][] = $j + ['id' => (string) Str::uuid(), 'plante_id' => $plante, 'type_intervention_id' => $types['taille'], 'auteur_id' => $membre->id, 'faite_le' => '2026-03-01'];
    }
    foreach ($lignes as $table => $rangs) {
        foreach (array_chunk($rangs, 500) as $paquet) {
            DB::table($table)->insert($paquet);
        }
    }

    return $membre;
}

/** @return array{int, float} le nombre de requêtes et la durée en ms de `GET /` */
function mesurerTableau(Utilisateur $membre): array
{
    // Chaque mesure part d'une session neuve : le jardin de la mesure d'avant n'y reste pas.
    app()->forgetScopedInstances();
    test()->flushSession();
    $requetes = 0;
    DB::listen(function () use (&$requetes) {
        $requetes++;
    });

    $debut = hrtime(true);
    test()->withoutVite()->actingAs($membre)->get('/')->assertOk();
    $ms = (hrtime(true) - $debut) / 1e6;

    return [$requetes, $ms];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    // Le cuivre de l'automne 2026 est manqué : le premier affichage l'écrit.
    $this->travelTo(new DateTimeImmutable('2027-04-15 08:00:00', new DateTimeZone('Europe/Paris')));
});

it('rend le tableau de bord de 500 plantes avec autant de requêtes qu\'à 20, sous le seuil', function () {
    [$requetes20] = mesurerTableau(jardinEnVolume(20));
    $membre = jardinEnVolume(500);

    // Le premier affichage écrit les manquées ; le second, celui d'un lundi ordinaire, n'écrit rien.
    [$requetes500] = mesurerTableau($membre);
    [, $ms] = mesurerTableau($membre);
    fwrite(STDERR, sprintf("\nV79 : %d requêtes, %.0f ms à 500 plantes (seuil %d ms)\n", $requetes500, $ms, SEUIL_TABLEAU_MS));

    expect($requetes500)->toBe($requetes20)
        ->and($ms)->toBeLessThan(SEUIL_TABLEAU_MS);
});
