<?php

// V67 : toute espèce annoncée a une règle sourcée, ou sort de la liste (B-T10,
// B5, D-52, D-63). La liste de départ du CDC §5.1 est écrite ici, nom du CDC
// par nom du CDC ; chaque espèce y a une règle chargée en base (une par
// variante de conduite, RG-51), ou une entrée de `retraits.yaml` dont la raison
// est recopiée dans `decisions-agents.md`.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

/** La liste de départ du CDC §5.1 : nom du CDC => clé de l'espèce et ses variantes de conduite. */
const LISTE_DE_DEPART = [
    'pommier' => ['pommier', []],
    'poirier' => ['poirier', []],
    'prunier' => ['prunier', []],
    'cerisier' => ['cerisier', []],
    'vigne' => ['vigne', []],
    'groseillier à grappes' => ['groseillier-a-grappes', []],
    'cassissier' => ['cassissier', []],
    'haie persistante' => ['haie-persistante', []],
    'haie caduque' => ['haie-caduque', []],
    'rosier' => ['rosier', []],
    'arbuste à floraison printanière' => ['arbuste-floraison-printaniere', []],
    'arbuste à floraison estivale' => ['arbuste-floraison-estivale', []],
    'pelouse' => ['pelouse', []],
    'pêcher' => ['pecher', []],
    'framboisier' => ['framboisier', ['non-remontant', 'remontant']],
];

/**
 * Charge le dossier, et rend les espèces de la liste qui n'ont ni règle en
 * vigueur ni retrait raisonné.
 *
 * @param  array<string, array{0: string, 1: list<string>}>  $liste
 * @return list<string>
 */
function especesIncompletes(string $dossier, array $liste, string $decisions): array
{
    test()->artisan('bibliotheque:charger', ['dossier' => $dossier])->assertSuccessful();

    $retraits = [];
    $fautes = [];
    $fichier = "{$dossier}/retraits.yaml";
    foreach (is_file($fichier) ? (Yaml::parseFile($fichier)['retraits'] ?? []) : [] as $retrait) {
        $cle = (string) ($retrait['retiree'] ?? '');
        if (trim((string) ($retrait['raison'] ?? '')) === '') {
            $fautes[] = "{$cle} retirée sans raison";

            continue;
        }
        if (! str_contains($decisions, "`{$cle}`")) {
            $fautes[] = "{$cle} : raison du retrait absente de decisions-agents.md";
        }
        $retraits[$cle] = true;
    }

    foreach ($liste as [$cle, $variantes]) {
        if (isset($retraits[$cle])) {
            continue;
        }
        $regles = DB::table('regles_reference')
            ->join('especes', 'especes.id', '=', 'regles_reference.espece_id')
            ->where('especes.cle', $cle)
            ->whereNull('regles_reference.remplacee_le')
            ->pluck('regles_reference.variante');
        if ($regles->isEmpty()) {
            $fautes[] = "{$cle} sans règle";

            continue;
        }
        foreach ($variantes as $variante) {
            if (! $regles->contains(fn (?string $v) => $v === null || $v === $variante)) {
                $fautes[] = "{$cle} sans règle pour la variante {$variante}";
            }
        }
    }

    return $fautes;
}

/**
 * L'espèce sans ses règles communes : seules restent celles d'une variante.
 *
 * @param  array<string, mixed>  $espece
 * @return array<string, mixed>
 */
function sansRegleCommune(array $espece): array
{
    $espece['regles'] = array_values(array_filter($espece['regles'], fn (array $r) => isset($r['variante'])));

    return $espece;
}

it('a une règle chargée ou un retrait raisonné pour chaque espèce de la liste de départ (B-T10)', function () {
    $decisions = (string) file_get_contents(base_path('docs/plan/decisions-agents.md'));

    expect(especesIncompletes(base_path('bibliotheque'), LISTE_DE_DEPART, $decisions))->toBe([]);
});

it('donne au framboisier une taille par variante de conduite (RG-51)', function () {
    test()->artisan('bibliotheque:charger')->assertSuccessful();

    $variantes = DB::table('regles_reference')
        ->join('especes', 'especes.id', '=', 'regles_reference.espece_id')
        ->join('types_intervention', 'types_intervention.id', '=', 'regles_reference.type_intervention_id')
        ->where('especes.cle', 'framboisier')
        ->where('types_intervention.famille', 'taille')
        ->whereNull('regles_reference.remplacee_le')
        ->orderBy('regles_reference.variante')
        ->pluck('regles_reference.variante')
        ->all();

    expect($variantes)->toBe(['non-remontant', 'remontant']);
});

it('reprend la liste de départ du CDC §5.1', function () {
    $cdc = (string) file_get_contents(base_path('docs/cahier-des-charges-fonctionnel.md'));
    $section = substr($cdc, (int) strpos($cdc, '### 5.1'), (int) strpos($cdc, '### 5.2') - (int) strpos($cdc, '### 5.1'));
    $depart = substr($section, (int) strpos($section, '**Contenu de départ'), (int) strpos($section, '**Hors de la liste de départ**') - (int) strpos($section, '**Contenu de départ'));
    $depart = mb_strtolower(str_replace('*', '', $depart));

    $absents = array_values(array_filter(array_keys(LISTE_DE_DEPART), fn (string $nom) => ! str_contains($depart, $nom)));

    expect($depart)->not->toBe('')
        ->and($absents)->toBe([]);
});

it('rougit sur un témoin incomplet', function (array $especes, array $retraits, array $liste, string $decisions, string $faute) {
    $dossier = TemoinBibliotheque::dossier(['temoins' => $especes]);
    file_put_contents("{$dossier}/retraits.yaml", Yaml::dump(['retraits' => $retraits], 4, 2));

    expect(especesIncompletes($dossier, $liste, $decisions))->toContain($faute);
})->with([
    'espèce sans règle' => [
        [TemoinBibliotheque::fruitier()], [],
        ['fruitier' => ['fruitier-temoin', []], 'absente' => ['espece-absente', []]], '',
        'espece-absente sans règle',
    ],
    'variante sans règle' => [
        [sansRegleCommune(TemoinBibliotheque::framboisier())], [],
        ['framboisier' => ['framboisier-temoin', ['remontant', 'bifere']]], '',
        'framboisier-temoin sans règle pour la variante bifere',
    ],
    'retrait sans raison' => [
        [], [['retiree' => 'espece-absente', 'raison' => ' ']],
        ['absente' => ['espece-absente', []]], '`espece-absente`',
        'espece-absente retirée sans raison',
    ],
    'raison non recopiée' => [
        [], [['retiree' => 'espece-absente', 'raison' => 'aucune source témoin']],
        ['absente' => ['espece-absente', []]], '',
        'espece-absente : raison du retrait absente de decisions-agents.md',
    ],
]);

it('accepte une espèce retirée avec sa raison recopiée', function () {
    $dossier = TemoinBibliotheque::dossier(['temoins' => [TemoinBibliotheque::fruitier()]]);
    file_put_contents("{$dossier}/retraits.yaml", Yaml::dump(['retraits' => [['retiree' => 'espece-absente', 'raison' => 'aucune source témoin']]], 4, 2));

    expect(especesIncompletes($dossier, ['fruitier' => ['fruitier-temoin', []], 'absente' => ['espece-absente', []]], '`espece-absente`'))->toBe([]);
});
