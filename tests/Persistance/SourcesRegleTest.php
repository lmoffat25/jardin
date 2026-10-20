<?php

// V26 : au commit, la base refuse une règle de référence `verifiee` qui a moins
// de deux sources, la même URL deux fois pour une règle, une règle sans
// `confiance`, et deux versions en vigueur d'une même clé (S9, B1, B2, RG-31,
// D-39).
//
// Le test tourne dans la transaction de RefreshDatabase, qui n'est jamais
// validée : `SET CONSTRAINTS … IMMEDIATE` fait passer maintenant les contrôles
// différés que le commit ferait passer (D-93).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

afterEach(function () {
    DB::statement('SET CONSTRAINTS ALL DEFERRED');
});

/** Les contrôles différés passent comme au commit. */
function commit(): void
{
    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
}

/**
 * Une règle de référence insérée sur une espèce neuve ; rend son id.
 *
 * @param  array<string, mixed>  $champs
 */
function regleReference(array $champs = []): int
{
    $espece = DB::table('especes')->insertGetId([
        'cle' => 'espece-'.bin2hex(random_bytes(4)),
        'nom_commun' => 'Espèce témoin',
        'categorie' => 'fruitier',
    ]);

    return DB::table('regles_reference')->insertGetId($champs + [
        'espece_id' => $espece,
        'type_intervention_id' => DB::table('types_intervention')->whereNull('jardin_id')->where('libelle', 'Rabattage')->value('id'),
        'type_regle' => 'R2',
        'parametres' => '{}',
        'cle' => 'regle-'.bin2hex(random_bytes(4)),
        'confiance' => 'verifiee',
    ]);
}

function source(int $regle, string $url): void
{
    DB::table('sources_regle')->insert([
        'regle_reference_id' => $regle,
        'url' => $url,
        'libelle' => 'Source témoin',
        'consultee_le' => '2026-10-20',
    ]);
}

it('refuse au commit une règle vérifiée qui n\'a qu\'une source', function () {
    refusee(function () {
        source(regleReference(), 'https://une.exemple.test/a');
        commit();
    });
});

it('refuse au commit une règle vérifiée sans source', function () {
    refusee(function () {
        regleReference();
        commit();
    });
});

it('accepte une règle vérifiée à deux sources, et une indicative à une seule', function () {
    $verifiee = regleReference();
    source($verifiee, 'https://une.exemple.test/a');
    source($verifiee, 'https://deux.exemple.test/b');
    source(regleReference(['confiance' => 'indicative']), 'https://une.exemple.test/a');
    commit();

    expect(DB::table('sources_regle')->count())->toBe(3);
});

it('refuse au commit le retrait d\'une source qui laisse une vérifiée seule', function () {
    $regle = regleReference();
    source($regle, 'https://une.exemple.test/a');
    source($regle, 'https://deux.exemple.test/b');
    commit();

    refusee(function () {
        DB::table('sources_regle')->where('url', 'https://deux.exemple.test/b')->delete();
        commit();
    });
});

it('refuse au commit une indicative passée vérifiée sans seconde source', function () {
    $regle = regleReference(['confiance' => 'indicative']);
    source($regle, 'https://une.exemple.test/a');
    commit();

    refusee(function () use ($regle) {
        DB::table('regles_reference')->where('id', $regle)->update(['confiance' => 'verifiee']);
        commit();
    });
});

it('refuse deux fois la même URL pour une règle', function () {
    $regle = regleReference(['confiance' => 'indicative']);
    source($regle, 'https://une.exemple.test/a');

    refusee(fn () => source($regle, 'https://une.exemple.test/a'));
});

it('refuse une règle sans confiance, et une confiance inconnue', function () {
    refusee(fn () => regleReference(['confiance' => null]));
    refusee(fn () => regleReference(['confiance' => 'probable']));
});

it('refuse deux versions en vigueur d\'une même clé, et accepte une version remplacée à côté', function () {
    $v1 = regleReference(['cle' => 'regle-temoin', 'confiance' => 'indicative']);
    $espece = DB::table('regles_reference')->where('id', $v1)->value('espece_id');

    refusee(fn () => regleReference(['cle' => 'regle-temoin', 'version' => 2, 'confiance' => 'indicative', 'espece_id' => $espece]));
    refusee(fn () => regleReference(['cle' => 'regle-temoin', 'version' => 1, 'confiance' => 'indicative', 'remplacee_le' => now()]));

    DB::table('regles_reference')->where('id', $v1)->update(['remplacee_le' => now()]);
    regleReference(['cle' => 'regle-temoin', 'version' => 2, 'confiance' => 'indicative', 'espece_id' => $espece]);

    expect(DB::table('regles_reference')->where('cle', 'regle-temoin')->count())->toBe(2);
});

it('refuse deux espèces de même clé', function () {
    DB::table('especes')->insert(['cle' => 'espece-temoin', 'nom_commun' => 'Une', 'categorie' => 'haie']);

    refusee(fn () => DB::table('especes')->insert(['cle' => 'espece-temoin', 'nom_commun' => 'Deux', 'categorie' => 'haie']));
});
