<?php

// V5 : `make verifier` enchaîne les six cibles dans l'ordre du chapitre 6 §4,
// et la première qui échoue arrête l'enchaînement avec un code non nul.

use Symfony\Component\Process\Process;

const CIBLES = ['statique', 'test', 'balayage', 'e2e', 'mutation', 'criteres'];

function lancerMake(array $arguments): Process
{
    $process = new Process(['make', '--no-print-directory', ...$arguments], base_path());
    $process->run();

    return $process;
}

it('lance les six cibles dans l\'ordre', function () {
    $process = lancerMake(['-n', 'verifier']);

    expect($process->getExitCode())->toBe(0);
    preg_match_all('/make(?:\s+--no-print-directory)?\s+(\w+)$/m', $process->getOutput(), $m);
    expect($m[1])->toBe(CIBLES);
});

it('déclare toutes les cibles du chapitre 6, même vides', function (string $cible) {
    expect(lancerMake(['-n', $cible])->getExitCode())->toBe(0);
})->with(['setup', 'moteur', ...CIBLES]);

it('s\'arrête à la première cible en échec', function () {
    $process = lancerMake(['verifier', 'ECHEC=statique']);

    expect($process->getExitCode())->not->toBe(0)
        ->and($process->getOutput())->toContain('échec forcé : statique')
        ->and($process->getOutput())->not->toContain('pest')
        ->and($process->getOutput())->not->toContain('les six cibles sont passées');
});
