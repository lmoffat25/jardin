<?php

// V32 : le jardin courant n'est jamais nul (A11). Sans positionnement, il lève ;
// positionné, il rend l'identifiant positionné.

use Src\Support\Jardin\JardinCourant;
use Src\Support\Jardin\JardinCourantAbsent;

it('lève quand aucun jardin n\'est positionné', function () {
    $courant = app(JardinCourant::class);

    expect($courant->estPositionne())->toBeFalse();
    expect(fn () => $courant->id())->toThrow(JardinCourantAbsent::class);
});

it('rend l\'identifiant positionné', function () {
    $courant = app(JardinCourant::class);
    $courant->positionner('0192f0e2-0000-7000-8000-000000000001');

    expect($courant->estPositionne())->toBeTrue();
    expect(app(JardinCourant::class)->id())->toBe('0192f0e2-0000-7000-8000-000000000001');
});

it('repart à zéro quand le conteneur oublie ses instances de portée', function () {
    app(JardinCourant::class)->positionner('0192f0e2-0000-7000-8000-000000000001');

    // Ce que font une nouvelle requête et un nouveau job.
    app()->forgetScopedInstances();

    expect(app(JardinCourant::class)->estPositionne())->toBeFalse();
});
