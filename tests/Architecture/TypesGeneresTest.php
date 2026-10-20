<?php

// V8 : les types TypeScript générés depuis PHP sont tenus à jour. `make types`
// les écrit ; `make statique` les régénère dans un fichier temporaire et échoue
// s'ils diffèrent du fichier versionné.

use Symfony\Component\Process\Process;

function makeTypes(array $arguments): Process
{
    $process = new Process(['make', '--no-print-directory', ...$arguments], base_path());
    $process->setTimeout(120);
    $process->run();

    return $process;
}

it('génère le type attendu d\'une classe témoin annotée', function () {
    $sortie = tempnam(sys_get_temp_dir(), 'types');

    $process = makeTypes(['types', "TYPES_SORTIE=$sortie", 'TYPES_SOURCES=tests/Architecture/Temoin/Types']);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput())
        ->and((string) file_get_contents($sortie))->toContain(
            "export type PlanteTemoin = {\n"
            ."    id: string;\n"
            ."    nom: string;\n"
            ."    codeCourt: string | null;\n"
            ."    arrosages: number;\n"
            .'};'
        );
    unlink($sortie);
});

it('fait échouer make statique quand le fichier versionné est périmé', function () {
    $perime = tempnam(sys_get_temp_dir(), 'perime');
    file_put_contents($perime, "export type Perime = { ancien: string };\n");

    $process = makeTypes(['statique', "TYPES=$perime", 'TYPES_SOURCES=tests/Architecture/Temoin/Types']);

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getOutput().$process->getErrorOutput())->toContain('types générés périmés : make types');
    unlink($perime);
});

it('versionne des types à jour', function () {
    $sortie = tempnam(sys_get_temp_dir(), 'types');

    expect(makeTypes(['types', "TYPES_SORTIE=$sortie"])->getExitCode())->toBe(0)
        ->and(file_get_contents($sortie))->toBe(file_get_contents(base_path('resources/js/types/generes.d.ts')));
    unlink($sortie);
});
