<?php

// V27 : toute table métier porte `jardin_id NOT NULL` (RG-22, D-02), sauf une
// liste fermée relue ici. V129 : l'entretien ne désigne jamais une zone (RG-34).

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/** Les tables dispensées de `jardin_id NOT NULL`, et pourquoi. Liste fermée. */
const TABLES_SANS_JARDIN = [
    // Référence, partagée par tous les jardins.
    'profils_climatiques', 'familles_botaniques', 'especes', 'regles_reference',
    'sources_regle', // 4a : rattachée à regles_reference
    'communes', // 2f : référentiel importé (RG-52, D-66)
    // Le compte et le jardin lui-même.
    'utilisateurs', 'jardins',
    // Tables techniques de Laravel.
    'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
];

/**
 * Les tables du schéma public, hors liste, qui n'ont pas `jardin_id NOT NULL`.
 * `types_intervention` est attendue avec un `jardin_id` nullable (NULL = fourni).
 *
 * @return list<string>
 */
function tablesSansJardin(): array
{
    $fautives = [];
    foreach (Schema::getTables('public') as $table) {
        $nom = $table['name'];
        if (in_array($nom, TABLES_SANS_JARDIN, true)) {
            continue;
        }
        $colonne = collect(Schema::getColumns($nom))->firstWhere('name', 'jardin_id');
        $attendueNullable = $nom === 'types_intervention';
        if ($colonne === null || $colonne['type_name'] !== 'uuid' || $colonne['nullable'] !== $attendueNullable) {
            $fautives[] = $nom;
        }
    }

    return $fautives;
}

it('trouve jardin_id NOT NULL dans toute table hors de la liste fermée', function () {
    expect(Schema::hasTable('regles'))->toBeTrue('table regles inexistante')
        ->and(tablesSansJardin())->toBe([]);
});

it('porte jardin_id sur fenetres et intervalles_saisonniers (D-02)', function () {
    foreach (['fenetres', 'intervalles_saisonniers'] as $table) {
        expect(Schema::hasColumn($table, 'jardin_id'))->toBeTrue("{$table} sans jardin_id");
    }
});

it('signale une table nouvelle sans jardin_id', function () {
    DB::statement('CREATE TABLE temoin_sans_jardin (id uuid PRIMARY KEY)');
    DB::statement('CREATE TABLE temoin_jardin_nullable (id uuid PRIMARY KEY, jardin_id uuid)');

    expect(tablesSansJardin())->toBe(['temoin_jardin_nullable', 'temoin_sans_jardin']);
});

it('ne met de zone ni sur les règles, ni sur les interventions, ni sur les échéances (V129)', function () {
    foreach (['regles', 'interventions', 'echeances'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("table {$table} inexistante")
            ->and(Schema::hasColumn($table, 'zone_id'))->toBeFalse("{$table}.zone_id existe");
    }

    $zone = collect(Schema::getColumns('plantes'))->firstWhere('name', 'zone_id');
    expect($zone['nullable'])->toBeFalse();
});
