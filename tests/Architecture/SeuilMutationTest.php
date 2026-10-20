<?php

// V61 : le seuil de mutation versionné ne fait que monter (C10, O16, D-50). Il
// est comparé à celui de `origin/main` ; une référence introuvable échoue (un
// clone superficiel passerait n'importe quel seuil), un fichier absent de
// `main` compte 0. Le baisser est une décision RG-49, signalée dans la demande
// de fusion avec la modification de ce test.

use Symfony\Component\Process\Process;

function gitDans(string $depot, string ...$arguments): Process
{
    $process = new Process(['git', '-C', $depot, ...$arguments]);
    $process->run();

    return $process;
}

/** Le seuil écrit dans `tests/mutation-seuil.txt` de `origin/main` ; 0 s'il n'y est pas. */
function seuilDeMain(string $depot): int
{
    if (! gitDans($depot, 'rev-parse', '--verify', '--quiet', 'origin/main^{commit}')->isSuccessful()) {
        throw new RuntimeException('origin/main introuvable : le seuil de main ne peut pas être lu (actions/checkout sans fetch-depth: 0 ?)');
    }
    $fichier = gitDans($depot, 'show', 'origin/main:tests/mutation-seuil.txt');

    return $fichier->isSuccessful() ? seuilLu($fichier->getOutput()) : 0;
}

function seuilLu(string $contenu): int
{
    $seuil = filter_var(trim($contenu), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);

    return $seuil === false ? throw new UnexpectedValueException("Seuil illisible : « {$contenu} »") : $seuil;
}

/** Un dépôt jetable ; `$main` = contenu du seuil sur origin/main, null = fichier absent, false = pas d'origin/main. */
function depotTemoin(string|null|false $main): string
{
    $depot = sys_get_temp_dir().'/seuil-'.bin2hex(random_bytes(4));
    mkdir("{$depot}/tests", 0777, true);
    gitDans($depot, 'init', '--quiet');
    file_put_contents("{$depot}/LISEZMOI", 'témoin');
    if (is_string($main)) {
        file_put_contents("{$depot}/tests/mutation-seuil.txt", $main);
    }
    gitDans($depot, 'add', '.');
    gitDans($depot, '-c', 'user.name=Témoin', '-c', 'user.email=temoin@exemple.test', 'commit', '--quiet', '-m', 'témoin');
    if ($main !== false) {
        gitDans($depot, 'update-ref', 'refs/remotes/origin/main', 'HEAD');
    }

    return $depot;
}

it('ne baisse pas le seuil de main', function () {
    $versionne = seuilLu((string) file_get_contents(base_path('tests/mutation-seuil.txt')));
    // Dans un worktree sous DDEV, le dépôt commun n'est pas monté : la CI, elle, le voit toujours.
    if (getenv('DANS_CI') !== '1' && ! gitDans(base_path(), 'rev-parse', '--git-dir')->isSuccessful()) {
        $this->markTestSkipped('aucun dépôt git visible ici (worktree sous DDEV) : vérifié en CI');
    }

    expect($versionne)->toBeGreaterThanOrEqual(seuilDeMain(base_path()));
});

it('lit le seuil de main, 0 si le fichier en est absent', function (?string $main, int $attendu) {
    expect(seuilDeMain(depotTemoin($main)))->toBe($attendu);
})->with([
    'seuil écrit' => ['87', 87],
    'fin de ligne' => ["100\n", 100],
    'fichier absent' => [null, 0],
]);

it('échoue quand origin/main est introuvable', function () {
    seuilDeMain(depotTemoin(false));
})->throws(RuntimeException::class, 'origin/main introuvable');

it('refuse un seuil illisible', function (string $contenu) {
    seuilLu($contenu);
})->throws(UnexpectedValueException::class)->with(['', 'cent', '101', '-1', '87.5']);
