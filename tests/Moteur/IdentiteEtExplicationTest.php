<?php

// V49 : l'identité d'une échéance, (regle_id, due_le), est déterministe ; due_le
// est le début de la fenêtre projetée, décalée et rognée (H11, D-07).
// V50 : toute échéance s'explique (RG-06, RG-26, RG-31, D-51, critères 3 et 13).

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Explication;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Explication::class, Regle::class);

uses()->group('critere-03', 'critere-13');

/** @return list<array{string, string}> */
function couples(int $decalage, string $jour): array
{
    $regles = [JeuTemoin::pommier(effetLe: '2025-06-01'), JeuTemoin::cerisier(), JeuTemoin::haie()];
    $historique = Historique::depuis([['plante_id' => 'plante-cerisier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2026-08-20')]]);

    return array_map(
        fn (Echeance $e) => [$e->regleId, $e->dueLe->format(\DATE_ATOM)],
        Moteur::calculer($regles, $historique, Decalage::jours($decalage), JeuTemoin::jour($jour)),
    );
}

function premiere(Regle $regle, int $decalage, string $jour = '2026-09-01'): Echeance
{
    return Moteur::calculer([$regle], Historique::vide(), Decalage::jours($decalage), JeuTemoin::jour($jour))[0];
}

it('rend les mêmes couples (règle, due le) à deux calculs identiques', function () {
    expect(couples(7, '2027-04-01'))->toBe(couples(7, '2027-04-01'))
        ->and(couples(7, '2027-04-01'))->toHaveCount(5);
});

it('date l\'échéance au début de la fenêtre décalée puis rognée', function (int $decalage, string $attendu) {
    expect(premiere(JeuTemoin::haie(), $decalage)->dueLe->format('d/m/Y'))->toBe($attendu);
})->with([
    'Montagne' => [21, '06/09/2026'],
    'Midi, rognée au butoir' => [-15, '16/08/2026'],
    'Tempéré' => [0, '16/08/2026'],
]);

it('porte une explication structurée', function () {
    $echeance = premiere(JeuTemoin::pommier(confiance: 'indicative', origine: 'ajustee'), 15);

    expect($echeance->explication->typeRegle)->toBe('R2')
        ->and($echeance->explication->periode)->toBe($echeance->periode)
        ->and($echeance->explication->fenetre)->toEqual(JeuTemoin::pommier()->fenetres[0])
        ->and($echeance->explication->decalageJours)->toBe(15)
        ->and($echeance->explication->confiance)->toBe('indicative')
        ->and($echeance->explication->origine)->toBe('ajustee');
});

it('dit en une phrase la règle, sa fenêtre et le décalage du climat', function () {
    expect(premiere(JeuTemoin::pommier(), 15)->explication->enPhrase('Taille', 'Pommier'))
        ->toBe('Taille · Pommier : règle de la bibliothèque, fenêtre déc.–mars, décalée de 15 jours pour votre climat.');
});

it('ne mentionne pas le climat quand rien n\'est décalé', function () {
    expect(premiere(JeuTemoin::pommier(), 0)->explication->enPhrase('Taille', 'Pommier'))
        ->toBe('Taille · Pommier : règle de la bibliothèque, fenêtre déc.–mars.');
});

it('dit qu\'un climat plus doux avance la fenêtre', function (int $decalage, string $mention) {
    expect(premiere(JeuTemoin::cerisier(), $decalage)->explication->enPhrase('Taille', 'Cerisier'))
        ->toBe("Taille · Cerisier : règle de la bibliothèque, fenêtre août–sept., {$mention} pour votre climat.");
})->with([
    [-15, 'avancée de 15 jours'],
    [-1, 'avancée de 1 jour'],
    [1, 'décalée de 1 jour'],
    [2, 'décalée de 2 jours'],
]);

it('rappelle qu\'une règle indicative n\'est qu\'indicative, et qu\'une règle ajustée l\'a été', function () {
    expect(premiere(JeuTemoin::pommier(confiance: 'indicative', origine: 'ajustee'), 7)->explication->enPhrase('Taille', 'Pommier'))
        ->toBe('Taille · Pommier : règle ajustée, fenêtre déc.–mars, décalée de 7 jours pour votre climat. Règle indicative.');
});

it('tient en 400 caractères, même avec les noms les plus longs', function () {
    $phrase = premiere(JeuTemoin::pommier(confiance: 'indicative'), -15)->explication
        ->enPhrase(str_repeat('Éclaircissage ', 20), str_repeat('Pommier « Reine des reinettes » ', 20));

    expect(mb_strlen($phrase))->toBeLessThanOrEqual(400)
        ->and($phrase)->toEndWith('fenêtre déc.–mars, avancée de 15 jours pour votre climat. Règle indicative.');
});

it('abrège chacun des douze mois', function (int $mois, string $abrege) {
    $fenetre = new Fenetre(new JourMois(1, $mois), new JourMois(28, $mois));
    $periode = $fenetre->projeter(2026, Decalage::jours(0));

    expect((new Explication('R2', $fenetre, $periode, 0, 'verifiee', 'bibliotheque'))->enPhrase('Taille', 'Pommier'))
        ->toBe("Taille · Pommier : règle de la bibliothèque, fenêtre {$abrege}–{$abrege}.");
})->with([
    [1, 'janv.'], [2, 'févr.'], [3, 'mars'], [4, 'avr.'], [5, 'mai'], [6, 'juin'],
    [7, 'juil.'], [8, 'août'], [9, 'sept.'], [10, 'oct.'], [11, 'nov.'], [12, 'déc.'],
]);

it('écourte le libellé au-delà de 80 caractères et le nom au-delà de 120', function () {
    $phrase = premiere(JeuTemoin::pommier(), 0)->explication->enPhrase(str_repeat('t', 81), str_repeat('p', 121));

    expect($phrase)->toStartWith(str_repeat('t', 79).'… · '.str_repeat('p', 119).'… : ');
});

it('garde entiers un libellé de 80 caractères et un nom de 120', function () {
    $phrase = premiere(JeuTemoin::pommier(), 0)->explication->enPhrase(str_repeat('t', 80), str_repeat('p', 120));

    expect($phrase)->toStartWith(str_repeat('t', 80).' · '.str_repeat('p', 120).' : ');
});

it('n\'écrit pas encore la phrase d\'une règle libre (9b)', function () {
    premiere(JeuTemoin::pommier(origine: 'libre'), 0)->explication->enPhrase('Taille', 'Pommier');
})->throws(LogicException::class);

it('refuse un type de règle, une confiance ou une origine inconnus', function (string $type, string $confiance, string $origine) {
    new Regle('r', 'p', 1, $type, 1, JeuTemoin::pommier()->fenetres, [], $confiance, $origine, JeuTemoin::jour('2026-06-01'));
})->throws(InvalidArgumentException::class)->with([
    ['R3', 'verifiee', 'bibliotheque'],
    ['R2', 'certaine', 'bibliotheque'],
    ['R2', 'verifiee', 'importee'],
]);

it('accepte chaque type, confiance et origine du contrat', function (string $type, string $confiance, string $origine) {
    $fenetres = $type === 'R2' ? JeuTemoin::pommier()->fenetres : [];
    $intervalles = $type === 'R1' ? JeuTemoin::tonte()->intervalles : [];

    expect(new Regle('r', 'p', 1, $type, 1, $fenetres, $intervalles, $confiance, $origine, JeuTemoin::jour('2026-06-01')))->toBeInstanceOf(Regle::class);
})->with([
    ['R1', 'verifiee', 'bibliotheque'],
    ['R2', 'indicative', 'ajustee'],
    ['R2', 'verifiee', 'libre'],
]);

it('calcule les règles R1 et R2 ensemble, dans l\'ordre donné', function () {
    $echeances = Moteur::calculer([JeuTemoin::tonte(), JeuTemoin::pommier()], Historique::vide(), Decalage::jours(0), JeuTemoin::jour('2026-09-01'));

    expect(array_map(fn (Echeance $e) => [$e->regleId, $e->dueLe->format('d/m/Y')], $echeances))->toBe([
        ['regle-tonte', '01/09/2026'],
        ['regle-pommier', '01/12/2026'],
    ]);
});
