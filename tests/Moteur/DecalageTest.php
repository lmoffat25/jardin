<?php

// V43 : le décalage climatique déplace les deux bornes et l'optimale (RG-25,
// RG-26) ; « Frais » et « Très doux » s'écartent de 22 jours (critère 13).

use Src\Echeances\Moteur\Calendrier;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Periode;

mutates(Decalage::class, Fenetre::class);

uses()->group('critere-13');

const PROFILS = ['Très doux' => -15, 'Doux' => -7, 'Tempéré' => 0, 'Frais' => 7, 'Montagne' => 21];

function taillePommier(): Fenetre
{
    return new Fenetre(new JourMois(1, 12), new JourMois(31, 3), new JourMois(1, 2), new JourMois(15, 3));
}

/** @return array{string, string, string, string} */
function quatreDates(Periode $p): array
{
    return array_map(fn (DateTimeImmutable $d) => $d->format('d/m/Y'), [$p->debut, $p->fin, $p->optimalDebut, $p->optimalFin]);
}

it('décale les deux bornes et l\'optimale pour chacun des cinq profils', function (string $profil, array $attendu) {
    expect(quatreDates(taillePommier()->projeter(2026, Decalage::jours(PROFILS[$profil]))))->toBe($attendu);
})->with([
    ['Très doux', ['16/11/2026', '16/03/2027', '17/01/2027', '28/02/2027']],
    ['Doux', ['24/11/2026', '24/03/2027', '25/01/2027', '08/03/2027']],
    ['Tempéré', ['01/12/2026', '31/03/2027', '01/02/2027', '15/03/2027']],
    ['Frais', ['08/12/2026', '07/04/2027', '08/02/2027', '22/03/2027']],
    ['Montagne', ['22/12/2026', '21/04/2027', '22/02/2027', '05/04/2027']],
]);

it('écarte « Frais » et « Très doux » de 22 jours sur les bornes et l\'optimale', function () {
    $frais = taillePommier()->projeter(2026, Decalage::jours(PROFILS['Frais']));
    $tresDoux = taillePommier()->projeter(2026, Decalage::jours(PROFILS['Très doux']));

    foreach (['debut', 'fin', 'optimalDebut', 'optimalFin'] as $borne) {
        expect($tresDoux->{$borne}->diff($frais->{$borne})->days)->toBe(22)
            ->and($tresDoux->{$borne} < $frais->{$borne})->toBeTrue();
    }
});

it('garde sa valeur, signe compris', function (int $jours) {
    expect(Decalage::jours($jours)->valeur())->toBe($jours);
})->with([-15, 0, 21]);

it('déplace une date du nombre de jours, à minuit, à travers le changement d\'heure', function (int $jours, string $attendu) {
    $d = Decalage::jours($jours)->appliquer(Calendrier::date(2027, 3, 20));

    expect($d->format('d/m/Y H:i'))->toBe($attendu);
})->with([
    [21, '10/04/2027 00:00'],
    [-15, '05/03/2027 00:00'],
    [0, '20/03/2027 00:00'],
]);

it('ne modifie pas la date reçue', function () {
    $date = Calendrier::date(2027, 3, 20);
    Decalage::jours(7)->appliquer($date);

    expect($date->format('d/m/Y'))->toBe('20/03/2027');
});

it('décale la fenêtre trouvée pour aujourd\'hui, pas seulement sa projection', function () {
    // Le 25/03/2027 en Frais (+7) : la fenêtre 2026 court jusqu'au 07/04, elle est encore en cours.
    $p = Calendrier::prochaineFenetre(taillePommier(), Decalage::jours(7), Calendrier::date(2027, 3, 25));

    expect(quatreDates($p)[0])->toBe('08/12/2026');
});
