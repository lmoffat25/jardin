<?php

// V122 (critère 8, CDC §8.1, RG-11, D-61, écran B12) : l'export rend une
// archive lisible sans l'outil : un `donnees.json` de toutes les entités du
// jardin, avec les noms lisibles des zones, espèces et types ; un CSV par
// table, en UTF-8 avec BOM, séparé par `;`, aux en-têtes en français ; les
// images des fonds de plan sous `plans/` ; un `LISEZMOI.txt` qui décrit chaque
// fichier et chaque colonne. En base : chaque ligne de chacune de ces tables
// se retrouve dans l'archive, colonne à colonne. Sans session : connexion.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\DeuxJardins;
use Tests\Support\Export;

uses(RefreshDatabase::class)->group('critere-08');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
    $this->jardins = new DeuxJardins;
    $this->a = $this->jardins->a->id;
    Export::peupler($this->a, 'A', $this->jardins->proprietaireA->id);
    DB::table('utilisateurs')->where('id', $this->jardins->proprietaireA->id)->update(['nom_affiche' => 'Camille Témoin']);
    $this->travelTo(new DateTimeImmutable('2026-10-20 10:00:00', new DateTimeZone('Europe/Paris')));
});

/**
 * Les lignes d'une table de A, relues hors de tout filtre, par identifiant.
 * Les types : ceux du jardin, et les fournis qu'il utilise.
 *
 * @return array<string, array<string, mixed>>
 */
function lignesDeA(string $table): array
{
    $a = test()->a;
    $requete = DB::table($table);
    if ($table === 'types_intervention') {
        $requete->where('jardin_id', $a)
            ->orWhereIn('id', DB::table('regles')->where('jardin_id', $a)->select('type_intervention_id'))
            ->orWhereIn('id', DB::table('interventions')->where('jardin_id', $a)->select('type_intervention_id'));
    } else {
        $requete->where('jardin_id', $a);
    }

    return $requete->get()->mapWithKeys(fn ($l) => [(string) $l->id => (array) $l])->all();
}

it('rend une archive en flux, nommée d\'après le jardin et le jour', function () {
    $reponse = Export::telecharger($this->jardins->membreA, $this->a);

    $reponse->assertOk()->assertHeader('Content-Type', 'application/zip');
    expect($reponse->baseResponse)->toBeInstanceOf(StreamedResponse::class)
        ->and($reponse->headers->get('Content-Disposition'))->toContain('jardin-jardin-a-2026-10-20.zip');
});

it('contient chaque fichier de D-61', function () {
    $fichiers = Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a));

    foreach ([...array_map(fn ($t) => "{$t}.csv", Export::CSV), 'donnees.json', 'LISEZMOI.txt'] as $nom) {
        expect($fichiers)->toHaveKey($nom);
    }
});

it('retrouve chaque ligne de chaque table, colonne à colonne', function (string $table) {
    $csv = Export::csv(Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a))["{$table}.csv"]);
    $attendues = lignesDeA($table);

    expect($attendues)->not->toBeEmpty()
        ->and($csv['lignes'])->toHaveCount(count($attendues));
    $lues = collect($csv['lignes'])->keyBy('id')->all();
    foreach ($attendues as $id => $ligne) {
        expect($lues)->toHaveKey($id);
        foreach ($ligne as $colonne => $valeur) {
            expect($lues[$id])->toHaveKey($colonne);
            expect($lues[$id][$colonne])->toBe(Export::attendu($valeur), "{$table}.{$colonne}");
        }
    }
})->with(Export::CSV);

it('joint les noms lisibles des zones, espèces, types et auteurs', function () {
    $fichiers = Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a));
    $par = fn (string $table, string $cle) => collect(Export::csv($fichiers["{$table}.csv"])['lignes'])->keyBy($cle);

    $plantes = $par('plantes', 'nom');
    expect($plantes['A Pommier'])->toMatchArray(['zone' => 'A Rosiers', 'espece' => 'Pommier témoin', 'espece_nom_latin' => 'Malus témoin'])
        ->and($plantes['A Ancienne'])->toMatchArray(['zone' => 'A Massifs', 'espece' => ''])
        ->and($par('zones', 'nom')['A Rosiers']['zone_parente'])->toBe('A Massifs');

    $regles = collect(Export::csv($fichiers['regles.csv'])['lignes']);
    expect($regles->pluck('type_intervention')->unique()->sort()->values()->all())->toBe(['A Geste maison', 'Tonte'])
        ->and($regles->pluck('plante')->unique()->all())->toBe(['A Pommier']);

    $intervention = Export::csv($fichiers['interventions.csv'])['lignes'][0];
    expect($intervention)->toMatchArray(['type_intervention' => 'A Geste maison', 'plante' => 'A Pommier', 'auteur' => 'Camille Témoin']);
    foreach (['geometries', 'echeances', 'photos'] as $table) {
        expect(Export::csv($fichiers["{$table}.csv"])['lignes'][0]['plante'])->toBe('A Pommier');
    }
    expect(Export::csv($fichiers['fenetres.csv'])['lignes'][0]['type_intervention'])->toBe('A Geste maison')
        ->and(Export::csv($fichiers['intervalles_saisonniers.csv'])['lignes'][0]['type_intervention'])->toBe('Tonte');
});

it('ne met que les types du jardin et les fournis qu\'il utilise', function () {
    $types = collect(Export::csv(Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a))['types_intervention.csv'])['lignes']);

    expect($types->pluck('libelle')->sort()->values()->all())->toBe(['A Geste maison', 'Tonte'])
        ->and($types->keyBy('libelle')['Tonte']['fourni'])->toBe('oui')
        ->and($types->keyBy('libelle')['A Geste maison']['fourni'])->toBe('non');
});

it('range l\'image de chaque fond de plan sous plans/', function () {
    $fichiers = Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a));
    $fond = Export::csv($fichiers['fonds_de_plan.csv'])['lignes'][0];

    expect($fond['image'])->toStartWith('plans/')
        ->and($fichiers)->toHaveKey($fond['image'])
        ->and($fichiers[$fond['image']])->toBe('A image du plan');
});

it('décrit dans donnees.json toutes les entités, avec leurs noms lisibles', function () {
    $donnees = json_decode(Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a))['donnees.json'], true, flags: JSON_THROW_ON_ERROR);

    expect($donnees['jardin'])->toMatchArray(['id' => $this->a, 'nom' => 'Jardin A', 'profil_climatique' => 'Tempéré']);
    foreach (Export::CSV as $table) {
        expect(collect($donnees[$table])->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all())
            ->toBe(collect(array_keys(lignesDeA($table)))->map(fn ($id) => (string) $id)->sort()->values()->all());
    }
    $pommier = collect($donnees['plantes'])->firstWhere('nom', 'A Pommier');
    expect($pommier)->toMatchArray(['zone' => 'A Rosiers', 'espece' => 'Pommier témoin', 'notes' => "Greffé ; \"porte-greffe\" M106\nà surveiller"])
        ->and($donnees['geometries'][0]['coordonnees'])->toBe(['type' => 'Point', 'coordinates' => [4.8357, 45.764]])
        ->and($donnees['interventions'][0]['type_intervention'])->toBe('A Geste maison')
        // jsonb réordonne les clés : leur ordre n'a pas de sens.
        ->and($donnees['fonds_de_plan'][0]['calage'][1])->toEqual(['x' => 400, 'y' => 300, 'lng' => 4.837, 'lat' => 45.7635]);
});

it('décrit chaque fichier et chaque colonne dans LISEZMOI.txt', function () {
    $fichiers = Export::fichiers(Export::telecharger($this->jardins->membreA, $this->a));
    // Une section par fichier : une ligne qui commence par son nom, puis ses
    // colonnes en retrait, « nom : description ».
    $sections = [];
    $courante = null;
    foreach (explode("\n", $fichiers['LISEZMOI.txt']) as $ligne) {
        if (preg_match('/^([a-z_]+\.(csv|json))\b/', $ligne, $m)) {
            $courante = $m[1];
            $sections[$courante] = [];
        } elseif ($courante !== null && preg_match('/^\s+([a-z_]+) : \S/', $ligne, $m)) {
            $sections[$courante][] = $m[1];
        }
    }

    expect($sections)->toHaveKey('donnees.json');
    foreach (Export::CSV as $table) {
        expect($sections)->toHaveKey("{$table}.csv");
        $entetes = Export::csv($fichiers["{$table}.csv"])['entetes'];
        expect(array_values(array_diff($entetes, $sections["{$table}.csv"])))->toBe([], "{$table}.csv : colonnes non décrites");
    }
});

it('renvoie à la connexion sans session', function () {
    $this->get('/export')->assertRedirect('/connexion');
});
