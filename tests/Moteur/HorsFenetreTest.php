<?php

// V54 : une intervention hors de toute fenêtre est prise comme dernière
// occurrence sans solder de fenêtre ; la prochaine échéance est la fenêtre
// suivante (H9, RG-05).

use Src\Echeances\Moteur\ChoixDeFenetre;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(ChoixDeFenetre::class, Moteur::class);

/** @return list<array{string, string}> état, due le */
function apresTailleDeJuin(Regle $regle, string $jour): array
{
    $historique = Historique::depuis([['plante_id' => $regle->planteId, 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2027-06-15')]]);

    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([$regle], $historique, Decalage::jours(0), JeuTemoin::jour($jour)),
    );
}

it('propose la fenêtre suivante après une taille de juin', function () {
    expect(apresTailleDeJuin(JeuTemoin::pommier(), '2027-07-01'))->toBe([['a_venir', '01/12/2027']]);
});

it('ne rend pas manquées les fenêtres d\'avant la taille de juin', function () {
    expect(apresTailleDeJuin(JeuTemoin::pommier(effetLe: '2025-06-01'), '2027-07-01'))->toBe([['a_venir', '01/12/2027']]);
});

it('ne solde aucune fenêtre par une taille de juin', function () {
    expect(apresTailleDeJuin(JeuTemoin::pommier(), '2028-04-01'))->toBe([
        ['manquee', '01/12/2027'],
        ['a_venir', '01/12/2028'],
    ]);
});

it('propose la plus proche des fenêtres suivantes, et laisse les deux dues', function () {
    expect(apresTailleDeJuin(JeuTemoin::deuxPassages(), '2027-07-01'))->toBe([['a_venir', '01/11/2027']])
        ->and(apresTailleDeJuin(JeuTemoin::deuxPassages(), '2028-03-15'))->toBe([
            ['manquee', '01/11/2027'],
            ['manquee', '01/02/2028'],
            ['a_venir', '01/11/2028'],
        ]);
});
