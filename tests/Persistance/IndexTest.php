<?php

// V31 : les trois index de lecture du chapitre 4 §4 existent et commencent par
// `jardin_id` (S12) ; le plan d'exécution de la requête d'historique du pont,
// `DISTINCT ON` (A9), utilise `idx_interventions_historique`.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class);

it('ouvre les trois index de lecture par jardin_id, colonnes du chapitre 4 §4', function (string $index, string $suite) {
    $definition = DB::table('pg_indexes')->where('indexname', $index)->value('indexdef');

    expect($definition)->toBe("CREATE INDEX {$index} ON public.{$suite}");
})->with([
    ['idx_interventions_historique', 'interventions USING btree (jardin_id, plante_id, type_intervention_id, faite_le DESC)'],
    ['idx_plantes_actives', 'plantes USING btree (jardin_id, zone_id) WHERE ((etat)::text = \'active\'::text)'],
    ['idx_echeances_jardin', 'echeances USING btree (jardin_id, plante_id, due_le DESC)'],
]);

it('lit l\'historique du pont par idx_interventions_historique, sur 2 000 interventions', function () {
    $this->seed(ReferenceSeeder::class);
    $types = array_map(RegleFactory::typeFourni(...), ['Taille d\'entretien/fructification', 'Traitement cuivre', 'Tonte', 'Arrosage']);
    $auteur = Utilisateur::factory()->create()->id;

    // Dix jardins de 200 interventions : 10 plantes, 4 types, 5 dates par couple.
    $jardins = Jardin::factory()->count(10)->create();
    foreach ($jardins as $jardin) {
        $j = ['jardin_id' => $jardin->id];
        $zone = (string) Str::uuid();
        DB::table('zones')->insert($j + ['id' => $zone, 'nom' => 'Zone']);
        $plantes = $interventions = [];
        foreach (range(1, 10) as $p) {
            $plantes[] = $plante = $j + ['id' => (string) Str::uuid(), 'zone_id' => $zone, 'code_court' => sprintf('IDX-%03d', $p), 'nom' => "Plante {$p}"];
            foreach ($types as $type) {
                foreach (range(1, 5) as $d) {
                    $interventions[] = $j + ['id' => (string) Str::uuid(), 'plante_id' => $plante['id'], 'type_intervention_id' => $type, 'auteur_id' => $auteur, 'faite_le' => sprintf('2026-%02d-%02d', $d, $p)];
                }
            }
        }
        DB::table('plantes')->insert($plantes);
        DB::table('interventions')->insert($interventions);
    }
    expect(DB::table('interventions')->count())->toBe(2000);
    DB::statement('ANALYZE interventions');

    app(JardinCourant::class)->positionner($jardins->first()->id);
    $requete = app(CalculEcheances::class)->requeteHistorique();
    $plan = implode("\n", array_column(array_map(fn ($l) => (array) $l, DB::select('EXPLAIN '.$requete->toSql(), $requete->getBindings())), 'QUERY PLAN'));

    // Une ligne par couple (plante, type), la plus récente : mai.
    $lignes = $requete->get();
    expect($requete->toSql())->toContain('distinct on')
        ->and($lignes)->toHaveCount(40)
        ->and($lignes->map(fn ($i) => $i->faite_le->format('m'))->unique()->all())->toBe(['05'])
        ->and($plan)->toContain('idx_interventions_historique');
});
