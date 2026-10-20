<?php

// V56 : hors saison, l'échéance est reportée à l'ouverture suivante, jamais
// supprimée (critère 15, A24, RG-40, D-10). V58 : une tonte en retard est due
// aujourd'hui, sauf hors saison (chapitre 3 §7, étape 4).

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\IntervalleSaisonnier;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Regle::class);

uses()->group('critere-15');

/**
 * @param  list<IntervalleSaisonnier>|null  $intervalles
 * @return list<array{string, string}> état, due le
 */
function tonteReportee(?string $tondueLe, string $jour, ?array $intervalles = null): array
{
    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([JeuTemoin::tonte($intervalles)], JeuTemoin::tondueLe($tondueLe), Decalage::jours(0), JeuTemoin::jour($jour)),
    );
}

it('garde une tonte du 15/11 due le 25/11, encore en saison', function () {
    expect(tonteReportee('2027-11-15', '2027-11-16'))->toBe([['a_venir', '25/11/2027']]);
});

it('reporte au 01/03 une tonte qui tomberait en décembre', function () {
    expect(tonteReportee('2027-11-25', '2027-11-26'))->toBe([['a_venir', '01/03/2028']]);
});

it('reporte une tonte du 15/12, d\'intervalle nul, au lieu de n\'en rendre aucune', function () {
    expect(tonteReportee('2027-12-15', '2027-12-16'))->toBe([['a_venir', '01/03/2028']]);
});

it('propose, sans aucune tonte, l\'ouverture de la saison en janvier (D-10)', function () {
    expect(tonteReportee(null, '2028-01-15'))->toBe([['a_venir', '01/03/2028']]);
});

it('ne rend jamais une tonte due en hiver', function (?string $tondueLe) {
    $jour = JeuTemoin::jour('2027-12-01');
    while ($jour <= JeuTemoin::jour('2028-02-29')) {
        expect(tonteReportee($tondueLe, $jour->format('Y-m-d')))->toBe([['a_venir', '01/03/2028']]);
        $jour = $jour->modify('+1 day');
    }
})->with([
    'tondue le 15/11' => ['2027-11-15'],
    'tondue le 25/11' => ['2027-11-25'],
    'tondue le 15/12' => ['2027-12-15'],
    'jamais tondue' => [null],
]);

it('rend une tonte en retard due aujourd\'hui, saison ouverte', function () {
    expect(tonteReportee('2027-04-03', '2027-04-20'))->toBe([['ouverte', '20/04/2027']])
        ->and(tonteReportee('2027-11-15', '2027-11-30'))->toBe([['ouverte', '30/11/2027']]);
});

it('rend une tonte en retard due à l\'ouverture suivante, saison fermée', function () {
    expect(tonteReportee('2027-11-15', '2027-12-10'))->toBe([['a_venir', '01/03/2028']]);
});

it('traite un mois sans saison déclarée comme hors saison', function () {
    $avrilSeptembre = [new IntervalleSaisonnier(4, 9, 7)];

    expect(tonteReportee('2027-09-25', '2027-09-26', $avrilSeptembre))->toBe([['a_venir', '01/04/2028']])
        ->and(tonteReportee(null, '2027-03-31', $avrilSeptembre))->toBe([['a_venir', '01/04/2027']])
        ->and(tonteReportee(null, '2027-04-01', $avrilSeptembre))->toBe([['ouverte', '01/04/2027']]);
});

it('lit une saison active à cheval sur l\'année', function () {
    $hiverActif = [new IntervalleSaisonnier(11, 2, 20), new IntervalleSaisonnier(5, 7, 7)];

    expect(tonteReportee('2028-01-20', '2028-01-21', $hiverActif))->toBe([['a_venir', '09/02/2028']])
        ->and(tonteReportee('2028-02-20', '2028-02-21', $hiverActif))->toBe([['a_venir', '01/05/2028']])
        ->and(tonteReportee('2027-07-31', '2027-08-01', $hiverActif))->toBe([['a_venir', '01/11/2027']]);
});

it('donne l\'ouverture suivante, strictement après la date', function (string $jour, string $attendu) {
    $regle = JeuTemoin::tonte([new IntervalleSaisonnier(5, 7, 7), new IntervalleSaisonnier(10, 10, 0)]);

    expect($regle->ouvertureSuivante(JeuTemoin::jour($jour), Decalage::jours(0))->format('d/m/Y'))->toBe($attendu);
})->with([
    ['2027-04-30', '01/05/2027'],
    ['2027-05-01', '01/05/2028'],
    ['2027-09-15', '01/05/2028'],
    ['2027-12-31', '01/05/2028'],
]);
