<?php

// V44 : un butoir rogne, il ne déplace pas (RG-44, RG-45, A25, critère 4).
// Fenêtre de haie 16/08 → 15/03, butoirs 16/08 et 15/03.

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Periode;

mutates(Fenetre::class, Periode::class);

uses()->group('critere-04');

function haie(bool $butoirs, ?JourMois $optDebut = null, ?JourMois $optFin = null): Fenetre
{
    return new Fenetre(
        new JourMois(16, 8),
        new JourMois(15, 3),
        $optDebut,
        $optFin,
        $butoirs ? new JourMois(16, 8) : null,
        $butoirs ? new JourMois(15, 3) : null,
    );
}

function bornesHaie(Periode $p): array
{
    return [$p->debut->format('d/m/Y'), $p->fin->format('d/m/Y')];
}

function optimale(Periode $p): ?array
{
    return $p->optimalDebut === null ? null : [$p->optimalDebut->format('d/m/Y'), $p->optimalFin->format('d/m/Y')];
}

it('rogne la fenêtre aux butoirs après le décalage', function (int $decalage, int $annee, array $attendu) {
    expect(bornesHaie(haie(true)->projeter($annee, Decalage::jours($decalage))))->toBe($attendu);
})->with([
    'Montagne' => [21, 2026, ['06/09/2026', '15/03/2027']],
    'Midi' => [-15, 2026, ['16/08/2026', '28/02/2027']],
    'Midi, fin en année bissextile' => [-15, 2027, ['16/08/2027', '29/02/2028']],
    'Tempéré' => [0, 2026, ['16/08/2026', '15/03/2027']],
]);

it('laisse déborder la fenêtre sans butoir', function (int $decalage, array $attendu) {
    expect(bornesHaie(haie(false)->projeter(2026, Decalage::jours($decalage))))->toBe($attendu);
})->with([
    'Montagne' => [21, ['06/09/2026', '05/04/2027']],
    'Midi' => [-15, ['01/08/2026', '28/02/2027']],
]);

it('rogne d\'un seul côté quand la fenêtre n\'a qu\'un butoir', function () {
    $finSeule = new Fenetre(new JourMois(16, 8), new JourMois(15, 3), butoirFin: new JourMois(15, 3));
    $debutSeul = new Fenetre(new JourMois(16, 8), new JourMois(15, 3), butoirDebut: new JourMois(16, 8));

    expect(bornesHaie($finSeule->projeter(2026, Decalage::jours(-15))))->toBe(['01/08/2026', '28/02/2027'])
        ->and(bornesHaie($finSeule->projeter(2026, Decalage::jours(21))))->toBe(['06/09/2026', '15/03/2027'])
        ->and(bornesHaie($debutSeul->projeter(2026, Decalage::jours(-15))))->toBe(['16/08/2026', '28/02/2027'])
        ->and(bornesHaie($debutSeul->projeter(2026, Decalage::jours(21))))->toBe(['06/09/2026', '05/04/2027']);
});

it('rogne l\'optimale à la fenêtre rognée', function (int $decalage, array $optDebut, array $optFin, ?array $attendu) {
    expect(optimale(haie(true, new JourMois(...$optDebut), new JourMois(...$optFin))->projeter(2026, Decalage::jours($decalage))))->toBe($attendu);
})->with([
    'Montagne, fin de l\'optimale au butoir' => [21, [15, 2], [15, 3], ['08/03/2027', '15/03/2027']],
    'Midi, début de l\'optimale au butoir' => [-15, [16, 8], [15, 9], ['16/08/2026', '31/08/2026']],
    'Tempéré, optimale intacte' => [0, [1, 10], [30, 11], ['01/10/2026', '30/11/2026']],
    'Montagne, optimale réduite au jour du butoir' => [21, [22, 2], [10, 3], ['15/03/2027', '15/03/2027']],
    'Montagne, optimale toute au-delà du butoir' => [21, [1, 3], [15, 3], null],
]);

it('ne rogne pas l\'optimale d\'une fenêtre sans butoir', function () {
    $p = haie(false, new JourMois(15, 2), new JourMois(15, 3))->projeter(2026, Decalage::jours(21));

    expect(optimale($p))->toBe(['08/03/2027', '05/04/2027']);
});
