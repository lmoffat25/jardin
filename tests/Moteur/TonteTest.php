<?php

// V55 : l'intervalle d'une tonte suit la saison de la dernière tonte (critère 5,
// CDC §4.2, R1) ; une règle R1 a au moins une saison active.

use Src\Echeances\Moteur\Calendrier;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Explication;
use Src\Echeances\Moteur\IntervalleSaisonnier;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Regle::class, IntervalleSaisonnier::class, Calendrier::class, Explication::class);

uses()->group('critere-05');

/** @return list<array{string, string}> état, due le */
function tonteDue(?string $tondueLe, string $jour, int $decalage = 0): array
{
    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([JeuTemoin::tonte()], JeuTemoin::tondueLe($tondueLe), Decalage::jours($decalage), JeuTemoin::jour($jour)),
    );
}

/** @param  list<IntervalleSaisonnier>  $intervalles */
function regleR1(array $intervalles): Regle
{
    return new Regle('r', 'p', 1, 'R1', 1, [], $intervalles, 'verifiee', 'bibliotheque', JeuTemoin::jour('2026-06-01'));
}

it('rend une tonte du 03/04 due le 10/04, et une du 05/10 due le 15/10', function () {
    expect(tonteDue('2027-04-03', '2027-04-04'))->toBe([['a_venir', '10/04/2027']])
        ->and(tonteDue('2027-10-05', '2027-10-06'))->toBe([['a_venir', '15/10/2027']])
        ->and(tonteDue('2027-07-01', '2027-07-02'))->toBe([['a_venir', '15/07/2027']]);
});

it('lit l\'intervalle de la saison de la dernière tonte, pas de celle de l\'échéance', function () {
    expect(tonteDue('2027-06-28', '2027-06-29'))->toBe([['a_venir', '05/07/2027']])
        ->and(tonteDue('2027-08-31', '2027-09-01'))->toBe([['a_venir', '14/09/2027']]);
});

it('rend une tonte ouverte le jour où elle est due', function () {
    expect(tonteDue('2027-04-03', '2027-04-09'))->toBe([['a_venir', '10/04/2027']])
        ->and(tonteDue('2027-04-03', '2027-04-10'))->toBe([['ouverte', '10/04/2027']]);
});

it('rend une pelouse jamais tondue due aujourd\'hui en saison', function () {
    expect(tonteDue(null, '2027-04-20'))->toBe([['ouverte', '20/04/2027']]);
});

it('donne à l\'échéance la saison où elle tombe, et une explication R1', function () {
    $regle = JeuTemoin::tonte();
    [$echeance] = Moteur::calculer([$regle], JeuTemoin::tondueLe('2027-06-28'), Decalage::jours(0), JeuTemoin::jour('2027-06-29'));

    expect($echeance->regleId)->toBe('regle-tonte')
        ->and($echeance->periode->debut->format('d/m/Y'))->toBe('01/07/2027')
        ->and($echeance->periode->fin->format('d/m/Y'))->toBe('31/08/2027')
        ->and($echeance->explication->typeRegle)->toBe('R1')
        ->and($echeance->explication->fenetre)->toBeNull()
        ->and($echeance->explication->saison)->toBe($regle->intervalles[1])
        ->and($echeance->explication->periode)->toBe($echeance->periode)
        ->and($echeance->explication->confiance)->toBe('indicative')
        ->and($echeance->explication->enPhrase('Tonte', 'Pelouse'))
        ->toBe('Tonte · Pelouse : règle de la bibliothèque, tous les 14 jours de juil. à août. Règle indicative.');
});

it('écrit la phrase d\'une saison d\'un mois et d\'un intervalle d\'un jour', function () {
    $regle = regleR1([new IntervalleSaisonnier(5, 5, 1)]);
    [$echeance] = Moteur::calculer([$regle], JeuTemoin::tondueLe(null), Decalage::jours(0), JeuTemoin::jour('2027-05-10'));

    expect($echeance->explication->enPhrase('Arrosage', 'Semis'))
        ->toBe('Arrosage · Semis : règle de la bibliothèque, chaque jour en mai.');
});

it('refuse d\'écrire une explication sans fenêtre ni saison', function () {
    $periode = JeuTemoin::tonte()->saisonDe(JeuTemoin::jour('2027-04-15'), Decalage::jours(0))[1];

    (new Explication('R1', null, $periode, 0, 'verifiee', 'bibliotheque'))->enPhrase('Tonte', 'Pelouse');
})->throws(LogicException::class, 'Explication sans fenêtre ni saison');

it('écrit le rythme d\'un intervalle de deux jours', function () {
    $regle = regleR1([new IntervalleSaisonnier(5, 6, 2)]);
    [$echeance] = Moteur::calculer([$regle], JeuTemoin::tondueLe(null), Decalage::jours(0), JeuTemoin::jour('2027-05-10'));

    expect($echeance->explication->enPhrase('Arrosage', 'Semis'))
        ->toBe('Arrosage · Semis : règle de la bibliothèque, tous les 2 jours de mai à juin.');
});

it('énumère les mois d\'une saison, à cheval sur l\'année ou non', function () {
    expect((new IntervalleSaisonnier(11, 2, 0))->mois())->toBe([11, 12, 1, 2])
        ->and((new IntervalleSaisonnier(3, 5, 7))->mois())->toBe([3, 4, 5])
        ->and((new IntervalleSaisonnier(5, 5, 7))->mois())->toBe([5]);
});

it('limite une saison d\'un mois à ce mois, et l\'ouvre chaque année', function () {
    $regle = regleR1([new IntervalleSaisonnier(5, 5, 1)]);

    expect($regle->intervalleDe(JeuTemoin::jour('2027-05-31'), Decalage::jours(0)))->toBe(1)
        ->and($regle->intervalleDe(JeuTemoin::jour('2027-06-15'), Decalage::jours(0)))->toBe(0)
        ->and($regle->ouvertureSuivante(JeuTemoin::jour('2027-09-15'), Decalage::jours(0))->format('d/m/Y'))->toBe('01/05/2028');
});

it('lit l\'intervalle de chaque saison, et 0 hors saison', function (string $jour, int $intervalle) {
    expect(JeuTemoin::tonte()->intervalleDe(JeuTemoin::jour($jour), Decalage::jours(0)))->toBe($intervalle);
})->with([
    ['2027-01-15', 0],
    ['2027-02-28', 0],
    ['2028-02-29', 0],
    ['2027-03-01', 7],
    ['2027-04-15', 7],
    ['2027-06-30', 7],
    ['2027-07-01', 14],
    ['2027-08-31', 14],
    ['2027-09-01', 10],
    ['2027-11-30', 10],
    ['2027-12-01', 0],
]);

it('donne le dernier jour de chaque mois', function (int $annee, int $mois, string $attendu) {
    expect(Calendrier::dernierJourDuMois($annee, $mois)->format('Y-m-d H:i e'))->toBe($attendu);
})->with([
    [2027, 2, '2027-02-28 00:00 Europe/Paris'],
    [2028, 2, '2028-02-29 00:00 Europe/Paris'],
    [2027, 4, '2027-04-30 00:00 Europe/Paris'],
    [2027, 12, '2027-12-31 00:00 Europe/Paris'],
]);

it('refuse une règle R1 sans aucune saison', function () {
    regleR1([]);
})->throws(InvalidArgumentException::class, 'Règle R1 sans saison active');

it('refuse une règle R1 dont toutes les saisons sont à zéro', function () {
    regleR1([new IntervalleSaisonnier(1, 12, 0)]);
})->throws(InvalidArgumentException::class, 'Règle R1 sans saison active');

it('refuse deux saisons qui partagent un mois', function (array $intervalles, string $message) {
    expect(fn () => regleR1($intervalles))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'même mois' => [fn () => [new IntervalleSaisonnier(3, 6, 7), new IntervalleSaisonnier(6, 8, 14)], 'Mois 6 dans deux saisons'],
    'à cheval' => [fn () => [new IntervalleSaisonnier(11, 2, 0), new IntervalleSaisonnier(2, 4, 7)], 'Mois 2 dans deux saisons'],
]);

it('accepte des saisons qui se suivent, et une seule saison active', function () {
    expect(regleR1([new IntervalleSaisonnier(11, 2, 0), new IntervalleSaisonnier(3, 10, 7)])->intervalles)->toHaveCount(2)
        ->and(regleR1([new IntervalleSaisonnier(1, 12, 3)])->intervalles)->toHaveCount(1);
});

it('refuse une saison impossible', function (int $debut, int $fin, int $intervalle) {
    new IntervalleSaisonnier($debut, $fin, $intervalle);
})->with([
    [0, 5, 7],
    [13, 5, 7],
    [3, 0, 7],
    [3, 13, 7],
    [3, 5, -1],
])->throws(InvalidArgumentException::class);

it('accepte les mois 1 et 12 et un intervalle nul', function () {
    $saison = new IntervalleSaisonnier(1, 12, 0);

    expect([$saison->moisDebut, $saison->moisFin, $saison->intervalleJours])->toBe([1, 12, 0]);
});
