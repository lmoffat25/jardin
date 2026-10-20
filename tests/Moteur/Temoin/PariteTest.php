<?php

use Src\Echeances\Moteur\Temoin\Parite;

mutates(Parite::class);

it('dit pair pour 0, 2 et -4', function (int $nombre) {
    expect((new Parite)->estPaire($nombre))->toBeTrue();
})->with([0, 2, -4]);

it('dit impair pour 1, 3 et -5', function (int $nombre) {
    expect((new Parite)->estPaire($nombre))->toBeFalse();
})->with([1, 3, -5]);
