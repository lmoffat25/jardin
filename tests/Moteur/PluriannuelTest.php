<?php

// V48 : une périodicité pluriannuelle saute des fenêtres (CDC §4.2, R2), et
// les fenêtres sautées ne sont jamais rendues manquées.

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Regle::class);

/** @return list<array{string, string}> état, due le */
function pluriannuel(int $periodicite, ?string $tailleLe, string $jour): array
{
    $historique = $tailleLe === null
        ? Historique::vide()
        : Historique::depuis([['plante_id' => 'plante-pommier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour($tailleLe)]]);

    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([JeuTemoin::pommier($periodicite)], $historique, Decalage::jours(0), JeuTemoin::jour($jour)),
    );
}

it('saute deux hivers après une taille, pour une périodicité de trois ans', function () {
    expect(pluriannuel(3, '2027-02-10', '2027-02-15'))->toBe([['a_venir', '01/12/2029']]);
});

it('ne rend jamais manquées les fenêtres sautées', function (string $jour) {
    expect(pluriannuel(3, '2027-02-10', $jour))->toBe([['a_venir', '01/12/2029']]);
})->with(['2028-04-01', '2029-04-01', '2029-11-30']);

it('propose l\'hiver suivant pour une périodicité d\'un an', function () {
    expect(pluriannuel(1, '2027-02-10', '2027-02-15'))->toBe([['a_venir', '01/12/2027']]);
});

it('compte les années depuis la fenêtre de la taille, même faite en fin de fenêtre', function () {
    expect(pluriannuel(2, '2027-03-31', '2027-04-01'))->toBe([['a_venir', '01/12/2028']]);
});

it('rend manquée la fenêtre due, puis propose l\'hiver suivant', function () {
    expect(pluriannuel(3, '2027-02-10', '2030-04-01'))->toBe([
        ['manquee', '01/12/2029'],
        ['a_venir', '01/12/2030'],
    ]);
});

it('refuse une périodicité inférieure à un an', function () {
    JeuTemoin::pommier(0);
})->throws(InvalidArgumentException::class);
