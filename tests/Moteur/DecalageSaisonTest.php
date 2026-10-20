<?php

// V57 : le décalage climatique déplace les bornes de saison d'une règle R1,
// jamais la valeur de son intervalle (RG-25, chapitre 3 §7).

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\IntervalleSaisonnier;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Regle::class, IntervalleSaisonnier::class);

/** @return list<array{string, string}> état, due le */
function tonteDecalee(?string $tondueLe, string $jour, int $decalage): array
{
    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([JeuTemoin::tonte()], JeuTemoin::tondueLe($tondueLe), Decalage::jours($decalage), JeuTemoin::jour($jour)),
    );
}

it('ouvre la saison quinze jours plus tôt dans le Midi, plus tard en montagne', function () {
    expect(tonteDecalee(null, '2027-01-15', -15))->toBe([['a_venir', '14/02/2027']])
        ->and(tonteDecalee(null, '2028-01-15', -15))->toBe([['a_venir', '15/02/2028']])
        ->and(tonteDecalee(null, '2027-01-15', 0))->toBe([['a_venir', '01/03/2027']])
        ->and(tonteDecalee(null, '2027-01-15', 15))->toBe([['a_venir', '16/03/2027']]);
});

it('garde l\'intervalle de 7 jours dans le Midi', function () {
    expect(tonteDecalee('2027-02-20', '2027-02-21', -15))->toBe([['a_venir', '27/02/2027']])
        ->and(tonteDecalee('2027-02-20', '2027-02-21', 0))->toBe([['a_venir', '01/03/2027']]);
});

it('ferme la saison quinze jours plus tôt dans le Midi', function () {
    expect(tonteDecalee('2027-11-10', '2027-11-11', -15))->toBe([['a_venir', '15/02/2028']])
        ->and(tonteDecalee('2027-11-10', '2027-11-11', 0))->toBe([['a_venir', '20/11/2027']]);
});

it('ne modifie la valeur d\'aucun intervalle, quel que soit le profil', function (int $decalage) {
    $tonte = JeuTemoin::tonte();
    $valeurs = array_map(
        fn (string $jour) => $tonte->intervalleDe(JeuTemoin::jour($jour), Decalage::jours($decalage)),
        ['2027-01-15', '2027-04-15', '2027-07-25', '2027-10-15'],
    );

    expect($valeurs)->toBe([0, 7, 14, 10])
        ->and(tonteDecalee('2027-04-03', '2027-04-04', $decalage))->toBe([['a_venir', '10/04/2027']]);
})->with([-15, -7, 0, 7, 15]);

it('décale les deux bornes de chaque saison, du premier au dernier jour du mois', function (int $decalage, string $jour, int $intervalle) {
    expect(JeuTemoin::tonte()->intervalleDe(JeuTemoin::jour($jour), Decalage::jours($decalage)))->toBe($intervalle);
})->with([
    [-15, '2027-02-13', 0],
    [-15, '2027-02-14', 7],
    [-15, '2027-06-15', 7],
    [-15, '2027-06-16', 14],
    [15, '2027-03-15', 0],
    [15, '2027-03-16', 7],
    [15, '2027-07-15', 7],
    [15, '2027-07-16', 14],
    [15, '2027-12-15', 10],
    [15, '2027-12-16', 0],
]);

it('lit une saison décalée sur l\'année voisine', function (array $intervalles, int $decalage, string $jour, int $intervalle) {
    $regle = new Regle('r', 'p', 1, 'R1', 1, [], $intervalles, 'verifiee', 'bibliotheque', JeuTemoin::jour('2026-06-01'));

    expect($regle->intervalleDe(JeuTemoin::jour($jour), Decalage::jours($decalage)))->toBe($intervalle);
})->with([
    'janvier avancé en décembre' => [fn () => [new IntervalleSaisonnier(1, 3, 7)], -15, '2027-12-20', 7],
    'décembre retardé en janvier' => [fn () => [new IntervalleSaisonnier(10, 12, 7)], 15, '2028-01-10', 7],
    'hiver retardé en mars' => [fn () => [new IntervalleSaisonnier(11, 2, 20)], 15, '2028-03-10', 20],
]);

it('ouvre une saison décalée sur l\'année voisine', function () {
    $regle = new Regle('r', 'p', 1, 'R1', 1, [], [new IntervalleSaisonnier(1, 3, 7)], 'verifiee', 'bibliotheque', JeuTemoin::jour('2026-06-01'));

    expect($regle->ouvertureSuivante(JeuTemoin::jour('2027-12-01'), Decalage::jours(-15))->format('d/m/Y'))->toBe('17/12/2027')
        ->and($regle->ouvertureSuivante(JeuTemoin::jour('2027-12-20'), Decalage::jours(-15))->format('d/m/Y'))->toBe('17/12/2028');
});

it('dit dans la phrase de combien la saison est déplacée', function (int $decalage, string $fin) {
    [$echeance] = Moteur::calculer([JeuTemoin::tonte()], JeuTemoin::tondueLe('2027-04-03'), Decalage::jours($decalage), JeuTemoin::jour('2027-04-04'));

    expect($echeance->explication->decalageJours)->toBe($decalage)
        ->and($echeance->explication->enPhrase('Tonte', 'Pelouse'))
        ->toBe("Tonte · Pelouse : règle de la bibliothèque, tous les 7 jours de mars à juin{$fin}. Règle indicative.");
})->with([
    [-15, ', saison avancée de 15 jours pour votre climat'],
    [1, ', saison décalée de 1 jour pour votre climat'],
    [0, ''],
]);
