<?php

// V71 : le calcul complet d'un jardin fait le même nombre de requêtes, au plus
// dix, pour 5 et pour 500 plantes à 3 règles ; la liste attendue est écrite ici
// (A9, RG-19, D-44). V74 : il ne lit que ce jardin (D1, critère 12). Et la date
// du jour vient de l'horloge, à Paris (D-42).

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-12');

/**
 * Les requêtes du calcul, dans l'ordre, par la table qu'elles lisent : le
 * profil du jardin, les règles, leurs fenêtres et leurs saisons, leurs plantes,
 * les zones et les types, la dernière intervention par couple en une requête
 * `DISTINCT ON` (A9), les échéances matérialisées.
 */
const REQUETES_ATTENDUES = [
    'profils_climatiques',
    'regles',
    'fenetres',
    'intervalles_saisonniers',
    'plantes',
    'zones',
    'types_intervention',
    'interventions',
    'echeances',
];

/**
 * $n plantes dans dix zones, chacune à trois règles : une taille R2 faite le
 * 01/03/2025 puis le 01/03/2026, un traitement R2 dont l'occurrence 2026 est close, une tonte R1.
 * Écrites en masse, hors modèles : la mise en place ne compte pas.
 */
function peuplerEnMasse(Jardin $jardin, int $n): void
{
    $taille = RegleFactory::typeFourni('Taille d\'entretien/fructification');
    $cuivre = RegleFactory::typeFourni('Traitement cuivre');
    $tonte = RegleFactory::typeFourni('Tonte');
    $auteur = Utilisateur::factory()->create()->id;
    $j = ['jardin_id' => $jardin->id];

    $zones = [];
    foreach (range(1, 10) as $z) {
        $zones[] = $j + ['id' => (string) Str::uuid(), 'nom' => "Zone {$z}"];
    }
    DB::table('zones')->insert($zones);

    $lignes = ['plantes' => [], 'regles' => [], 'fenetres' => [], 'intervalles_saisonniers' => [], 'interventions' => [], 'echeances' => []];
    foreach (range(1, $n) as $p) {
        $plante = (string) Str::uuid();
        $lignes['plantes'][] = $j + ['id' => $plante, 'zone_id' => $zones[$p % 10]['id'], 'code_court' => sprintf('MAS-%04d', $p), 'nom' => "Plante {$p}"];
        $regle = fn (int $type, array $champs) => $j + $champs + ['id' => (string) Str::uuid(), 'plante_id' => $plante, 'type_intervention_id' => $type, 'confiance' => 'verifiee', 'origine' => 'bibliotheque', 'creee_le' => '2026-01-01 12:00:00+01'];
        $lignes['regles'][] = $r1 = $regle($taille, ['type_regle' => 'R2', 'periodicite_annees' => 1, 'intervalle_jours' => null]);
        $lignes['regles'][] = $r2 = $regle($cuivre, ['type_regle' => 'R2', 'periodicite_annees' => 1, 'intervalle_jours' => null]);
        $lignes['regles'][] = $r3 = $regle($tonte, ['type_regle' => 'R1', 'periodicite_annees' => null, 'intervalle_jours' => 7]);
        foreach ([[$r1, 1, 2, 31, 3], [$r2, 1, 11, 15, 12]] as [$r, $dj, $dm, $fj, $fm]) {
            $lignes['fenetres'][] = $j + ['id' => (string) Str::uuid(), 'regle_id' => $r['id'], 'debut_jour' => $dj, 'debut_mois' => $dm, 'fin_jour' => $fj, 'fin_mois' => $fm];
        }
        $lignes['intervalles_saisonniers'][] = $j + ['id' => (string) Str::uuid(), 'regle_id' => $r3['id'], 'mois_debut' => 3, 'mois_fin' => 10, 'intervalle_jours' => 7];
        foreach (['2026-03-01', '2025-03-01'] as $faiteLe) {
            $lignes['interventions'][] = $j + ['id' => (string) Str::uuid(), 'plante_id' => $plante, 'type_intervention_id' => $taille, 'auteur_id' => $auteur, 'faite_le' => $faiteLe];
        }
        $lignes['echeances'][] = $j + ['id' => (string) Str::uuid(), 'regle_id' => $r2['id'], 'plante_id' => $plante, 'due_le' => '2026-11-01', 'etat' => 'suspendue', 'explication' => 'Échéance témoin.'];
    }
    foreach ($lignes as $table => $aEcrire) {
        foreach (array_chunk($aEcrire, 500) as $paquet) {
            DB::table($table)->insert($paquet);
        }
    }
}

/**
 * Le calcul du jardin, relu de la base, et la table lue par chaque requête.
 *
 * @return array{0: Collection<int, EcheanceCalculee>, 1: list<string>, 2: list<array{query: string, bindings: array<mixed>}>}
 */
function calculerEnComptant(string $jardinId): array
{
    app(JardinCourant::class)->positionner($jardinId);
    $jardin = Jardin::query()->findOrFail($jardinId);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $echeances = app(CalculEcheances::class)->pourJardin($jardin);
    DB::disableQueryLog();
    $requetes = DB::getQueryLog();

    $tables = array_map(function (array $q): string {
        preg_match('/\bfrom "([a-z_]+)"/', $q['query'], $m);

        return $m[1] ?? $q['query'];
    }, $requetes);

    return [$echeances, $tables, $requetes];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-10-20 10:00:00', new DateTimeZone('Europe/Paris')));
});

it('calcule 5 et 500 plantes à trois règles en un même nombre de requêtes, les attendues, dix au plus', function () {
    expect(count(REQUETES_ATTENDUES))->toBeLessThanOrEqual(10);

    foreach ([5, 500] as $n) {
        $jardin = Jardin::factory()->create();
        peuplerEnMasse($jardin, $n);

        [$echeances, $tables] = calculerEnComptant($jardin->id);

        expect($tables)->toBe(REQUETES_ATTENDUES, "{$n} plantes")
            ->and($echeances)->toHaveCount(3 * $n);
    }
});

it('rend chaque échéance avec sa règle, sa plante, sa zone et son type, sans requête de plus', function () {
    $jardin = Jardin::factory()->create();
    peuplerEnMasse($jardin, 5);
    [$echeances] = calculerEnComptant($jardin->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    foreach ($echeances as $e) {
        expect($e)->toBeInstanceOf(EcheanceCalculee::class)
            ->and($e->regle->id)->toBe($e->echeance->regleId)
            ->and($e->plante->id)->toBe($e->regle->plante_id)
            ->and($e->type->id)->toBe($e->regle->type_intervention_id)
            ->and($e->plante->zone->jardin_id)->toBe($jardin->id);
    }
    DB::disableQueryLog();
    expect(DB::getQueryLog())->toBe([]);
});

it('tient compte de la dernière intervention et des occurrences closes lues en base', function () {
    $jardin = Jardin::factory()->create();
    peuplerEnMasse($jardin, 5);
    [$echeances] = calculerEnComptant($jardin->id);

    $parType = $echeances->groupBy(fn (EcheanceCalculee $e) => $e->type->libelle)
        ->map(fn ($groupe) => $groupe->map(fn (EcheanceCalculee $e) => $e->echeance->dueLe->format('Y-m-d'))->unique()->values()->all());

    // Taille faite en dernier le 01/03/2026 : la suivante est en 2027. Cuivre : l'occurrence
    // du 01/11/2026 est close, la suivante est celle de 2027. Tonte jamais faite :
    // aujourd'hui, en saison.
    expect($parType->all())->toBe([
        'Taille d\'entretien/fructification' => ['2027-02-01'],
        'Traitement cuivre' => ['2027-11-01'],
        'Tonte' => ['2026-10-20'],
    ]);
});

it('ne calcule que les règles actives des plantes actives', function () {
    $jardin = Jardin::factory()->create();
    app(JardinCourant::class)->positionner($jardin->id);
    $plante = Plante::factory()->create();
    $active = Regle::factory()->for($plante)->create();
    $retiree = Regle::factory()->for($plante)->create(['est_active' => false, 'remplacee_le' => now()]);
    $archivee = Plante::factory()->create(['etat' => 'archivee', 'archivee_le' => now()]);
    $surArchivee = Regle::factory()->for($archivee)->create();
    foreach ([$active, $retiree, $surArchivee] as $regle) {
        Fenetre::factory()->for($regle)->create();
    }

    [$echeances] = calculerEnComptant($jardin->id);

    expect($echeances->map(fn (EcheanceCalculee $e) => $e->regle->id)->unique()->values()->all())->toBe([$active->id]);
});

it('lit une règle R1 sans saison déclarée comme valant toute l\'année, à son intervalle (D-98)', function () {
    $jardin = Jardin::factory()->create();
    app(JardinCourant::class)->positionner($jardin->id);
    $regle = Regle::factory()->r1(10)->create();
    Intervention::factory()->create(['plante_id' => $regle->plante_id, 'type_intervention_id' => $regle->type_intervention_id, 'faite_le' => '2026-10-15']);

    [$echeances] = calculerEnComptant($jardin->id);

    expect($echeances->sole()->echeance->dueLe->format('Y-m-d'))->toBe('2026-10-25');
});

it('ne lit, pour le jardin A, aucune règle, intervention ni échéance de B, même plante, même type', function () {
    [$a, $b] = Jardin::factory()->count(2)->create()->all();
    foreach ([$b, $a] as $jardin) {
        app(JardinCourant::class)->positionner($jardin->id);
        $plante = Plante::factory()->create(['nom' => 'Pommier homonyme']);
        $regle = Regle::factory()->for($plante)->create();
        Fenetre::factory()->for($regle)->create();
        $tonte = Regle::factory()->r1()->for($plante)->create();
        IntervalleSaisonnier::factory()->for($tonte)->create();
        Intervention::factory()->for($plante)->create();
        EcheanceMaterialisee::factory()->for($regle)->create(['plante_id' => $plante->id]);
    }

    [$echeances, $tables, $requetes] = calculerEnComptant($a->id);

    expect($echeances)->toHaveCount(2)
        ->and($echeances->map(fn (EcheanceCalculee $e) => $e->regle->jardin_id)->unique()->all())->toBe([$a->id])
        ->and($echeances->map(fn (EcheanceCalculee $e) => $e->plante->jardin_id)->unique()->all())->toBe([$a->id]);

    // Chaque lecture d'une table de jardin est bornée par A, jamais par B.
    foreach ($requetes as $i => $q) {
        if (in_array($tables[$i], ['regles', 'fenetres', 'intervalles_saisonniers', 'plantes', 'zones', 'interventions', 'echeances'], true)) {
            expect($q['query'])->toContain('"jardin_id" = ?', $tables[$i])
                ->and($q['bindings'])->toContain($a->id)
                ->and($q['bindings'])->not->toContain($b->id);
        }
    }
});

it('ne matérialise dans A qu\'une échéance qui porte le jardin_id de A', function () {
    [$a, $b] = Jardin::factory()->count(2)->create()->all();
    app(JardinCourant::class)->positionner($a->id);

    expect(EcheanceMaterialisee::factory()->create()->jardin_id)->toBe($a->id)
        ->and(fn () => EcheanceMaterialisee::factory()->create(['jardin_id' => $b->id]))->toThrow(LogicException::class);
});

it('refuse de calculer un autre jardin que le jardin courant', function () {
    [$a, $b] = Jardin::factory()->count(2)->create()->all();
    app(JardinCourant::class)->positionner($a->id);

    expect(fn () => app(CalculEcheances::class)->pourJardin($b))->toThrow(LogicException::class);
});

it('prend la date du jour à Paris, pas en UTC', function () {
    $jardin = Jardin::factory()->create();
    app(JardinCourant::class)->positionner($jardin->id);
    IntervalleSaisonnier::factory()->create();

    // 31/03 à 22 h 30 UTC : déjà le 01/04 à Paris.
    $this->travelTo(new DateTimeImmutable('2026-03-31 22:30:00', new DateTimeZone('UTC')));
    [$echeances] = calculerEnComptant($jardin->id);

    expect($echeances->sole()->echeance->dueLe->format('Y-m-d'))->toBe('2026-04-01');
});
