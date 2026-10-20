<?php

// V62 : la bibliothèque se charge, et se recharge sans modifier aucune ligne ;
// un fichier mal formé est refusé en entier, la base reste telle qu'elle était
// (RG-24, chap. 4 §5). V63 : une règle corrigée dans les fichiers crée une
// version suivante, l'ancienne reçoit `remplacee_le`, et aucune ligne de
// `regles`, `fenetres` ou `echeances` d'aucun jardin ne bouge (RG-24, RG-39).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

/**
 * L'emplacement et la transaction d'écriture de chaque ligne des tables : une
 * ligne réécrite, même à l'identique, change de `ctid` et de `xmin`.
 *
 * @param  list<string>  $tables
 * @return array<string, list<string>>
 */
function empreintes(array $tables): array
{
    $empreintes = [];
    foreach ($tables as $table) {
        $empreintes[$table] = array_map(
            fn (object $l) => "{$l->ctid} {$l->xmin} {$l->ligne}",
            DB::select("SELECT ctid::text, xmin::text, md5(t::text) AS ligne FROM {$table} t ORDER BY ctid"),
        );
    }

    return $empreintes;
}

const TABLES_BIBLIOTHEQUE = ['familles_botaniques', 'especes', 'regles_reference', 'sources_regle'];

/** @return list<object> les versions d'une règle de référence, de la plus ancienne à la plus récente */
function versions(string $cle): array
{
    return DB::table('regles_reference')->where('cle', $cle)->orderBy('version')->get()->all();
}

it('charge familles, espèces, règles et sources depuis les fichiers', function () {
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();

    expect(DB::table('familles_botaniques')->pluck('nom')->all())->toBe(['Famille témoin'])
        ->and(DB::table('especes')->orderBy('cle')->pluck('cle')->all())->toBe(['fruitier-temoin', 'pelouse-temoin']);

    [$taille] = versions('fruitier-temoin-taille');
    $parametres = json_decode($taille->parametres, true);
    expect($taille->version)->toBe(1)
        ->and($taille->remplacee_le)->toBeNull()
        ->and($taille->confiance)->toBe('verifiee')
        ->and($taille->type_regle)->toBe('R2')
        ->and($taille->type_intervention_id)->toBe(DB::table('types_intervention')->where('libelle', 'Taille d\'entretien/fructification')->value('id'))
        ->and($parametres['fenetres'][0])->toMatchArray(['debut' => '01/02', 'fin' => '31/03', 'optimal' => ['15/02', '15/03'], 'butoirs' => null])
        ->and($parametres['periodicite_annees'])->toBe(1)
        ->and(DB::table('sources_regle')->where('regle_reference_id', $taille->id)->orderBy('url')->pluck('url')->all())
        ->toBe(['https://conservatoire.exemple.test/fruitier', 'https://institut.exemple.test/fruitier']);

    [$tonte] = versions('pelouse-temoin-tonte');
    // toEqual : jsonb ne garde pas l'ordre des clés.
    expect(json_decode($tonte->parametres, true)['intervalles'])->toEqual([
        ['mois_debut' => 4, 'mois_fin' => 9, 'jours' => 7],
        ['mois_debut' => 10, 'mois_fin' => 3, 'jours' => 0],
    ]);
});

it('recharge les mêmes fichiers sans modifier aucune ligne', function () {
    $dossier = TemoinBibliotheque::complet();
    $this->artisan('bibliotheque:charger', ['dossier' => $dossier])->assertSuccessful();
    $avant = empreintes(TABLES_BIBLIOTHEQUE);

    $this->artisan('bibliotheque:charger', ['dossier' => $dossier])->assertSuccessful();

    expect($avant['regles_reference'])->toHaveCount(2)
        ->and(empreintes(TABLES_BIBLIOTHEQUE))->toBe($avant);
});

it('refuse en entier un fichier mal formé, et la base reste telle qu\'elle était', function (Closure $fautif, string $message) {
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();
    $avant = empreintes(TABLES_BIBLIOTHEQUE);
    $corrige = TemoinBibliotheque::pelouse();
    $corrige['nom_commun'] = 'Pelouse renommée';
    $dossier = TemoinBibliotheque::dossier(['fruitiers' => [TemoinBibliotheque::fruitier()], 'pelouses' => [$corrige]]);
    $fautif($dossier);

    $this->artisan('bibliotheque:charger', ['dossier' => $dossier])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(empreintes(TABLES_BIBLIOTHEQUE))->toBe($avant);
})->with([
    'YAML illisible' => [fn (string $d) => file_put_contents("{$d}/zz-casse.yaml", "especes:\n  - cle: casse\n   nom_commun: [\n"), 'zz-casse.yaml'],
    'date irréelle' => [function (string $d) {
        $espece = TemoinBibliotheque::fruitier();
        $espece['cle'] = 'autre-fruitier';
        $espece['regles'][0]['cle'] = 'autre-fruitier-taille';
        $espece['regles'][0]['fenetres'][0]['fin'] = '31/02';
        file_put_contents("{$d}/zz-fautif.yaml", Yaml::dump(['especes' => [$espece]], 8, 2));
    }, 'zz-fautif.yaml : especes[0].regles[0].fenetres[0].fin'],
    'type inconnu' => [function (string $d) {
        $espece = TemoinBibliotheque::pelouse();
        $espece['cle'] = 'autre-pelouse';
        $espece['regles'][0]['cle'] = 'autre-pelouse-tonte';
        $espece['regles'][0]['type_intervention'] = 'Coupe au ciseau';
        file_put_contents("{$d}/zz-fautif.yaml", Yaml::dump(['especes' => [$espece]], 8, 2));
    }, 'zz-fautif.yaml : especes[0].regles[0].type_intervention'],
]);

it('n\'écrit rien d\'un dossier mal formé sur une base vide', function () {
    $dossier = TemoinBibliotheque::complet();
    file_put_contents("{$dossier}/zz-casse.yaml", "especes: [\n");

    $this->artisan('bibliotheque:charger', ['dossier' => $dossier])->assertFailed();

    foreach (TABLES_BIBLIOTHEQUE as $table) {
        expect(DB::table($table)->count())->toBe(0, "{$table} écrite");
    }
});

it('crée une version suivante d\'une règle corrigée sans toucher aucun jardin', function () {
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();
    [$v1] = versions('fruitier-temoin-taille');

    // Un jardin a reçu la règle de la version 1, avec sa fenêtre et une échéance.
    $jardin = nouveauJardin();
    $plante = nouvellePlante($jardin);
    $regle = regle($jardin, $plante, $v1->type_intervention_id, ['regle_reference_id' => $v1->id, 'origine' => 'bibliotheque']);
    DB::table('regles')->insert($regle);
    DB::table('fenetres')->insert([
        'id' => (string) Str::uuid(), 'jardin_id' => $jardin, 'regle_id' => $regle['id'],
        'debut_jour' => 1, 'debut_mois' => 2, 'fin_jour' => 31, 'fin_mois' => 3,
    ]);
    DB::table('echeances')->insert([
        'id' => (string) Str::uuid(), 'jardin_id' => $jardin, 'regle_id' => $regle['id'], 'plante_id' => $plante,
        'due_le' => '2026-02-01', 'etat' => 'manquee', 'explication' => 'Taille témoin, manquée.',
    ]);
    $jardins = empreintes(['regles', 'fenetres', 'echeances']);

    $corrige = TemoinBibliotheque::fruitier();
    $corrige['regles'][0]['fenetres'][0]['fin'] = '15/03';
    $corrige['regles'][0]['fenetres'][0]['optimal'] = ['15/02', '01/03'];
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier(['fruitiers' => [$corrige], 'pelouses' => [TemoinBibliotheque::pelouse()]])])
        ->assertSuccessful();

    [$ancienne, $nouvelle] = versions('fruitier-temoin-taille');
    expect($ancienne->id)->toBe($v1->id)
        ->and($ancienne->remplacee_le)->not->toBeNull()
        ->and($ancienne->parametres)->toBe($v1->parametres)
        ->and($nouvelle->version)->toBe(2)
        ->and($nouvelle->remplacee_le)->toBeNull()
        ->and(json_decode($nouvelle->parametres, true)['fenetres'][0]['fin'])->toBe('15/03')
        ->and(DB::table('sources_regle')->where('regle_reference_id', $nouvelle->id)->count())->toBe(2)
        ->and(DB::table('sources_regle')->where('regle_reference_id', $ancienne->id)->count())->toBe(2)
        ->and(empreintes(['regles', 'fenetres', 'echeances']))->toBe($jardins);
});

it('remplace les sources seules sur la version en vigueur, sans version nouvelle', function () {
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();
    [$v1] = versions('fruitier-temoin-taille');

    $corrige = TemoinBibliotheque::fruitier();
    $corrige['regles'][0]['sources'][1] = ['url' => 'https://ouvrage.exemple.test/taille', 'libelle' => 'Ouvrage témoin', 'consultee_le' => '2026-10-21'];
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier(['fruitiers' => [$corrige], 'pelouses' => [TemoinBibliotheque::pelouse()]])])
        ->assertSuccessful();

    expect(versions('fruitier-temoin-taille'))->toHaveCount(1)
        ->and(DB::table('regles_reference')->where('id', $v1->id)->value('remplacee_le'))->toBeNull()
        ->and(DB::table('sources_regle')->where('regle_reference_id', $v1->id)->orderBy('url')->pluck('url')->all())
        ->toBe(['https://institut.exemple.test/fruitier', 'https://ouvrage.exemple.test/taille']);
});

it('retire de la bibliothèque une règle absente des fichiers, sans version nouvelle', function () {
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();

    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier(['fruitiers' => [TemoinBibliotheque::fruitier()]])])
        ->assertSuccessful();

    $tonte = versions('pelouse-temoin-tonte');
    expect($tonte)->toHaveCount(1)
        ->and($tonte[0]->remplacee_le)->not->toBeNull()
        ->and(DB::table('especes')->where('cle', 'pelouse-temoin')->exists())->toBeTrue();
});
