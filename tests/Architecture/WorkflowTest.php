<?php

// V6, V14, V15 : le workflow lu comme une donnée. Les jobs du chapitre 6 §8, une
// limite de temps sur chacun (C18), une concurrence par branche (C19), aucun
// déclencheur planifié (C20), des jobs conditionnels qui démarrent toujours, et
// des commandes qui passent toutes par `make` (V6). V66 : les sources de la
// bibliothèque s'interrogent par `make sources`, que rien dans la CI n'appelle.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

function workflow(): array
{
    return Yaml::parseFile(base_path('.github/workflows/verifier.yml'));
}

it('a les cinq jobs du chapitre 6', function () {
    $jobs = workflow()['jobs'] ?? [];

    foreach (['changements', 'statique', 'tests', 'e2e', 'mutation'] as $job) {
        expect($jobs)->toHaveKey($job, message: "job {$job} absent");
    }
    expect(array_keys($jobs))->toHaveCount(5);
});

it('borne chaque job dans le temps', function () {
    foreach (workflow()['jobs'] as $nom => $job) {
        expect($job['timeout-minutes'] ?? null)->toBeInt("job {$nom} sans timeout-minutes")
            ->toBeLessThanOrEqual(20);
    }
});

it('annule la vérification précédente de la même branche', function () {
    $concurrence = workflow()['concurrency'] ?? [];

    expect($concurrence['cancel-in-progress'] ?? false)->toBeTrue()
        ->and($concurrence['group'] ?? '')->toContain('github.head_ref');
});

it('tourne sur les demandes de fusion et sur main, jamais sur une horloge', function () {
    // symfony/yaml lit la clé `on` comme le booléen true.
    $declencheurs = workflow()['on'] ?? workflow()[true] ?? [];

    expect($declencheurs)->toHaveKeys(['pull_request', 'push'])->not->toHaveKey('schedule')
        ->and($declencheurs['push']['branches'] ?? [])->toBe(['main']);
});

it('fait démarrer e2e et mutation sur toute demande de fusion', function (string $nom) {
    $job = workflow()['jobs'][$nom];

    expect($job)->not->toHaveKey('if', message: "job {$nom} sauté par un if : il bloquerait la fusion")
        ->and((array) $job['needs'])->toContain('changements');
    foreach ($job['steps'] as $etape) {
        expect(str_contains($etape['if'] ?? '', 'needs.changements.outputs.'))->toBeTrue("étape de {$nom} sans condition");
    }
})->with(['e2e', 'mutation']);

it('mesure la couverture avec pcov dans le job mutation', function () {
    $php = collect(workflow()['jobs']['mutation']['steps'])
        ->first(fn (array $e) => str_starts_with($e['uses'] ?? '', 'shivammathur/setup-php'));

    expect($php['with']['coverage'] ?? null)->toBe('pcov');
});

it('ne lance que des cibles make', function () {
    foreach (workflow()['jobs'] as $nom => $job) {
        foreach ($job['steps'] ?? [] as $etape) {
            if (isset($etape['run'])) {
                expect($etape['run'])->toStartWith('make ', "job {$nom} : « {$etape['run']} »");
            }
        }
    }
});

/** @return list<string> les cibles make que lancent les étapes du workflow */
function ciblesDeLaCi(): array
{
    $cibles = [];
    foreach (workflow()['jobs'] as $job) {
        foreach ($job['steps'] ?? [] as $etape) {
            if (isset($etape['run']) && preg_match('/^make (\S+)/', $etape['run'], $m)) {
                $cibles[] = $m[1];
            }
        }
    }

    return array_values(array_unique($cibles));
}

/** Ce que `make -n <cible>` lancerait, sous-make compris. */
function commandesDe(string $cible): string
{
    $process = new Process(['make', '--no-print-directory', '-n', $cible], base_path());
    $process->run();

    return $process->getOutput();
}

it('interroge les sources par make sources', function () {
    expect(commandesDe('sources'))->toContain('php artisan bibliotheque:verifier-sources');
});

it('n\'interroge jamais les sources depuis la CI (V66)', function () {
    $cibles = ciblesDeLaCi();

    expect($cibles)->not->toContain('sources')->toContain('test');
    foreach ([...$cibles, 'verifier'] as $cible) {
        expect(str_contains(commandesDe($cible), 'verifier-sources'))->toBeFalse("make {$cible} interroge les sources");
    }
});

it('liste les sources qui ne répondent pas, après un HEAD puis un GET', function () {
    $this->seed(ReferenceSeeder::class);
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::complet()])->assertSuccessful();
    Http::fake([
        'institut.exemple.test/*' => Http::response(),
        'conservatoire.exemple.test/*' => fn (Request $r) => Http::response(status: $r->method() === 'HEAD' ? 405 : 200),
        'vulgarisation.exemple.test/*' => Http::response(status: 404),
    ]);

    $this->artisan('bibliotheque:verifier-sources')
        ->expectsOutput('échec : https://vulgarisation.exemple.test/tonte (HTTP 404)')
        ->doesntExpectOutputToContain('conservatoire')
        ->expectsOutput('sources : 3 interrogées, 1 en échec')
        ->assertFailed();
    Http::assertSentCount(5);
});
