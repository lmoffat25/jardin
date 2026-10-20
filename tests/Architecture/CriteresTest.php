<?php

// V12 : `make criteres` lit les numéros du §10 du CDC, cherche les groupes
// `critere-NN` dans les tests, liste les manquants ; 0 pendant le jalon, 1 avec
// LIVRAISON=1 s'il en manque un.

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->dossier = sys_get_temp_dir().'/criteres-'.bin2hex(random_bytes(4));
    File::makeDirectory($this->dossier.'/tests', 0777, true);
    File::put($this->dossier.'/cdc.md', implode("\n", [
        '## 9. Avant', '', '1. Pas un critère.', '',
        '## 10. Critères d\'acceptation', '', '### 10.1 Lot 0', '',
        '1. Premier.', '2. Deuxième,', '   sur deux lignes.', '', '### 10.2 Lot 4', '', '3. Troisième.', '',
        '## 11. Après', '', '4. Pas un critère non plus.', '',
    ]));
    // Groupes assemblés à l'exécution : écrits en clair, ce fichier compterait
    // lui-même comme couvrant ces critères dans le vrai `make criteres`.
    File::put($this->dossier.'/tests/UnTest.php', "<?php\nit('a', fn () => true)->group('".groupe(1)."');\n// critere-02 cité en commentaire ne compte pas\n");
    File::put($this->dossier.'/tests/DeuxTest.php', "<?php\nit('b', fn () => true)->group('lot-4', '".groupe(3)."');\n");
});

afterEach(function () {
    File::deleteDirectory($this->dossier);
});

function groupe(int $numero): string
{
    return sprintf('%s-%02d', 'critere', $numero);
}

function criteres(string $dossier, array $env = []): Process
{
    $process = new Process([base_path('scripts/criteres.sh')], null,
        ['CDC' => $dossier.'/cdc.md', 'TESTS' => $dossier.'/tests', ...$env]);
    $process->run();

    return $process;
}

it('liste les critères du §10 sans test, et rend 0 pendant le jalon', function () {
    $process = criteres($this->dossier);

    expect($process->getExitCode())->toBe(0)
        ->and(trim($process->getOutput()))->toBe('criteres : 1 critère(s) sur 3 sans test : 2');
});

it('rend 1 avec LIVRAISON=1 s\'il en manque un', function () {
    expect(criteres($this->dossier, ['LIVRAISON' => '1'])->getExitCode())->toBe(1);
});

it('rend 0 avec LIVRAISON=1 quand tous ont un test', function () {
    File::append($this->dossier.'/tests/UnTest.php', "it('c', fn () => true)->group('".groupe(2)."');\n");
    $process = criteres($this->dossier, ['LIVRAISON' => '1']);

    expect($process->getExitCode())->toBe(0)
        ->and(trim($process->getOutput()))->toBe('criteres : les 3 critères ont un test');
});

it('lit les 24 critères du vrai CDC', function () {
    $process = new Process([base_path('scripts/criteres.sh')]);
    $process->run();

    expect($process->getOutput())->toMatch('/sur 24 /');
});
