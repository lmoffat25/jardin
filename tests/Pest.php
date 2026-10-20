<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Routes', 'Architecture', 'Persistance');

/**
 * L'écriture est refusée par une contrainte d'intégrité (SQLSTATE de classe 23).
 * Elle tourne dans un point de sauvegarde : son échec n'interrompt pas la
 * transaction du test, et les écritures suivantes restent possibles.
 */
function refusee(callable $ecriture): void
{
    try {
        DB::transaction($ecriture);
    } catch (QueryException $e) {
        expect((string) $e->getCode())->toStartWith('23');

        return;
    }

    test()->fail('écriture acceptée, alors qu\'une contrainte devait la refuser');
}

/** Un utilisateur fictif ; rend son id. */
function nouvelUtilisateur(): string
{
    $id = (string) Str::uuid();
    DB::table('utilisateurs')->insert([
        'id' => $id,
        'courriel' => "{$id}@exemple.test",
        'mot_de_passe_hache' => 'x',
    ]);

    return $id;
}

/** Un jardin fictif sur un profil témoin ; rend son id. */
function nouveauJardin(): string
{
    DB::table('profils_climatiques')->insertOrIgnore(
        ['id' => 1, 'libelle' => 'Profil témoin', 'decalage_jours' => 0, 'ordre_affichage' => 1],
    );
    $id = (string) Str::uuid();
    DB::table('jardins')->insert([
        'id' => $id,
        'proprietaire_id' => nouvelUtilisateur(),
        'nom' => 'Jardin témoin',
        'profil_climatique_id' => 1,
    ]);

    return $id;
}

/** Une zone fictive du jardin ; rend son id. */
function nouvelleZone(string $jardin, string $nom = 'Zone témoin'): string
{
    $id = (string) Str::uuid();
    DB::table('zones')->insert(['id' => $id, 'jardin_id' => $jardin, 'nom' => $nom]);

    return $id;
}

/** Une plante fictive du jardin, dans une zone neuve ; rend son id. */
function nouvellePlante(string $jardin): string
{
    $id = (string) Str::uuid();
    DB::table('plantes')->insert([
        'id' => $id,
        'jardin_id' => $jardin,
        'zone_id' => nouvelleZone($jardin, "Zone {$id}"),
        'code_court' => 'PLA-'.substr($id, 0, 8),
        'nom' => 'Plante témoin',
    ]);

    return $id;
}

/** Un type d'intervention propre au jardin ; rend son id. */
function nouveauType(string $jardin): int
{
    return DB::table('types_intervention')->insertGetId(
        ['jardin_id' => $jardin, 'libelle' => 'Geste '.Str::uuid(), 'famille' => 'taille'],
    );
}

/**
 * Une ligne de règle R2 prête à insérer : active, version 1, origine libre.
 *
 * @param  array<string, mixed>  $champs
 * @return array<string, mixed>
 */
function regle(string $jardin, string $plante, int $type, array $champs = []): array
{
    return $champs + [
        'id' => (string) Str::uuid(),
        'jardin_id' => $jardin,
        'plante_id' => $plante,
        'type_intervention_id' => $type,
        'type_regle' => 'R2',
        'periodicite_annees' => 1,
        'confiance' => 'indicative',
        'origine' => 'libre',
    ];
}

/** Une règle insérée sur une plante neuve du jardin ; rend son id. */
function nouvelleRegle(string $jardin): string
{
    $ligne = regle($jardin, nouvellePlante($jardin), nouveauType($jardin));
    DB::table('regles')->insert($ligne);

    return $ligne['id'];
}
