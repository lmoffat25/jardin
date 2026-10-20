<?php

// V7 : une route rend un composant Inertia nommé, reçu avec ses props ; le
// bundle n'a qu'un point d'entrée applicatif.

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Process\Process;

it('rend le composant Temoin/Page avec ses props', function () {
    Route::middleware('web')->get('/temoin-inertia', fn () => Inertia::render('Temoin/Page', ['nom' => 'Prunier n°3']));

    // Le composant et ses props, pas les fichiers construits : le second test s'en charge.
    $this->withoutVite()->get('/temoin-inertia')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Temoin/Page')
            ->where('nom', 'Prunier n°3'));
});

it('produit un seul point d\'entrée applicatif', function () {
    $sortie = sys_get_temp_dir().'/vite-'.bin2hex(random_bytes(4));
    $build = new Process(['npx', 'vite', 'build', '--outDir', $sortie, '--emptyOutDir'], base_path(), null, null, 120);
    $build->mustRun();

    $manifeste = json_decode(File::get($sortie.'/manifest.json'), true);
    $entrees = array_keys(array_filter($manifeste, fn (array $morceau) => $morceau['isEntry'] ?? false));
    File::deleteDirectory($sortie);

    expect($entrees)->toBe(['resources/js/app.tsx']);
});

it('passe le typage strict de TypeScript', function () {
    $tsc = new Process(['npx', 'tsc', '--noEmit'], base_path(), null, null, 120);
    $tsc->run();

    expect($tsc->getExitCode())->toBe(0, $tsc->getOutput());
    expect(json_decode(File::get(base_path('tsconfig.json')), true)['compilerOptions'])
        ->toMatchArray(['strict' => true, 'noUncheckedIndexedAccess' => true]);
});
