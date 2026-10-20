<?php

// V3, C3 : les versions servies (PHP, PostgreSQL, Node) sont celles de
// `.ddev/config.yaml`, dans DDEV comme dans la CI ; la CI les déclare aussi.

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

function configDdev(): array
{
    return Yaml::parseFile(base_path('.ddev/config.yaml'));
}

function workflowVerifier(): array
{
    return Yaml::parseFile(base_path('.github/workflows/verifier.yml'));
}

function etapeAvec(string $action): array
{
    foreach (workflowVerifier()['jobs']['tests']['steps'] as $etape) {
        if (str_starts_with($etape['uses'] ?? '', $action.'@')) {
            return $etape['with'] ?? [];
        }
    }
    throw new RuntimeException("étape $action absente de verifier.yml");
}

it('sert la version de PHP de DDEV', function () {
    expect(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION)->toBe((string) configDdev()['php_version'])
        ->and((string) etapeAvec('shivammathur/setup-php')['php-version'])->toBe((string) configDdev()['php_version']);
});

it('sert la version majeure de PostgreSQL de DDEV', function () {
    $servie = DB::selectOne('show server_version_num')->server_version_num;
    $attendue = (string) configDdev()['database']['version'];

    expect(configDdev()['database']['type'])->toBe('postgres')
        ->and((string) intdiv((int) $servie, 10000))->toBe($attendue)
        ->and(workflowVerifier()['jobs']['tests']['services']['postgres']['image'])->toBe('postgres:'.$attendue);
});

it('sert la version majeure de Node de DDEV', function () {
    $process = new Process(['node', '--version']);
    $process->mustRun();
    $attendue = (string) configDdev()['nodejs_version'];

    expect(explode('.', ltrim(trim($process->getOutput()), 'v'))[0])->toBe($attendue)
        ->and((string) etapeAvec('actions/setup-node')['node-version'])->toBe($attendue);
});
