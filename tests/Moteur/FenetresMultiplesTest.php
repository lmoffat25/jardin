<?php

// V51 : une règle à plusieurs fenêtres rend une seule échéance à venir (RG-36, A8).
// V52 : plusieurs fenêtres manquées sont toutes consignées (RG-07, RG-40).

use Src\Echeances\Moteur\ChoixDeFenetre;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(ChoixDeFenetre::class, Moteur::class, Regle::class);

/** @return list<array{string, string, string}> état, due le, libellé de la fenêtre */
function passages(Regle $regle, ?string $traiteLe, string $jour, int $decalage = 0): array
{
    $historique = $traiteLe === null
        ? Historique::vide()
        : Historique::depuis([['plante_id' => $regle->planteId, 'type_id' => 1, 'fait_le' => JeuTemoin::jour($traiteLe)]]);

    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y'), (string) $e->explication->fenetre->libelle],
        Moteur::calculer([$regle], $historique, Decalage::jours($decalage), JeuTemoin::jour($jour)),
    );
}

it('ne rend que la fenêtre de février après un traitement le 10/11', function () {
    expect(passages(JeuTemoin::deuxPassages(), '2026-11-10', '2026-12-15'))->toBe([
        ['a_venir', '01/02/2027', 'fin d\'hiver'],
    ]);
});

it('ne rend que la fenêtre de novembre après un traitement le 10/02', function () {
    expect(passages(JeuTemoin::deuxPassages(), '2027-02-10', '2027-02-15'))->toBe([
        ['a_venir', '01/11/2027', 'chute des feuilles'],
    ]);
});

it('rend la fenêtre en cours, et elle seule, quand rien n\'est fait', function () {
    expect(passages(JeuTemoin::deuxPassages(), null, '2026-11-10'))->toBe([
        ['ouverte', '01/11/2026', 'chute des feuilles'],
    ]);
});

it('rend la plus proche des deux fenêtres à venir', function () {
    expect(passages(JeuTemoin::deuxPassages(), null, '2026-09-01'))->toBe([
        ['a_venir', '01/11/2026', 'chute des feuilles'],
    ]);
});

it('consigne manquées les deux fenêtres fermées sans traitement, puis une seule à venir', function () {
    expect(passages(JeuTemoin::deuxPassages(), null, '2027-03-15'))->toBe([
        ['manquee', '01/11/2026', 'chute des feuilles'],
        ['manquee', '01/02/2027', 'fin d\'hiver'],
        ['a_venir', '01/11/2027', 'chute des feuilles'],
    ]);
});

it('consigne manquée la seule fenêtre de février quand novembre a été traité', function () {
    expect(passages(JeuTemoin::deuxPassages(), '2026-11-10', '2027-03-15'))->toBe([
        ['manquee', '01/02/2027', 'fin d\'hiver'],
        ['a_venir', '01/11/2027', 'chute des feuilles'],
    ]);
});

it('consigne dans l\'ordre chaque fenêtre manquée de plusieurs années', function () {
    expect(array_column(passages(JeuTemoin::deuxPassages(effetLe: '2025-06-01'), null, '2027-03-15'), 1))->toBe([
        '01/11/2025', '01/02/2026', '01/11/2026', '01/02/2027', '01/11/2027',
    ]);
});

it('ne consigne pas les fenêtres commencées avant la date d\'effet', function () {
    expect(passages(JeuTemoin::deuxPassages(effetLe: '2026-11-15'), null, '2027-03-15'))->toBe([
        ['manquee', '01/02/2027', 'fin d\'hiver'],
        ['a_venir', '01/11/2027', 'chute des feuilles'],
    ]);
});

it('décale chacune des fenêtres', function () {
    expect(passages(JeuTemoin::deuxPassages(), '2026-11-20', '2026-12-15', 10))->toBe([
        ['a_venir', '11/02/2027', 'fin d\'hiver'],
    ])->and(passages(JeuTemoin::deuxPassages(), '2026-11-10', '2026-12-15', 10))->toBe([
        ['manquee', '11/11/2026', 'chute des feuilles'],
        ['a_venir', '11/02/2027', 'fin d\'hiver'],
    ]);
});

it('revient sur chaque fenêtre n ans après la dernière intervention', function () {
    expect(passages(JeuTemoin::deuxPassages(2), '2026-11-10', '2026-12-15'))->toBe([
        ['a_venir', '01/02/2028', 'fin d\'hiver'],
    ]);
});

it('départage deux fenêtres ouvertes le même jour par leur ordre déclaré', function () {
    $regle = JeuTemoin::deuxPassages(fenetres: [
        new Fenetre(new JourMois(1, 11), new JourMois(15, 12), libelle: 'seconde', ordre: 2),
        new Fenetre(new JourMois(1, 11), new JourMois(30, 11), libelle: 'première', ordre: 1),
    ]);

    expect(array_map(fn (Fenetre $f) => $f->ordre, $regle->fenetres))->toBe([1, 2])
        ->and(passages($regle, null, '2026-09-01'))->toBe([['a_venir', '01/11/2026', 'première']]);
});

it('garde l\'ordre de déclaration de fenêtres de même ordre', function () {
    $regle = JeuTemoin::deuxPassages(fenetres: [
        new Fenetre(new JourMois(1, 11), new JourMois(15, 12), libelle: 'déclarée d\'abord'),
        new Fenetre(new JourMois(1, 11), new JourMois(30, 11), libelle: 'déclarée ensuite'),
    ]);

    expect(passages($regle, null, '2026-09-01'))->toBe([['a_venir', '01/11/2026', 'déclarée d\'abord']]);
});

it('donne la prochaine fenêtre et sa période', function () {
    $historique = Historique::depuis([['plante_id' => 'plante-deux-passages', 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2026-11-10')]]);

    [$fenetre, $periode] = ChoixDeFenetre::prochaine(JeuTemoin::deuxPassages(), $historique, Decalage::jours(0), JeuTemoin::jour('2027-03-15'));

    expect($fenetre->libelle)->toBe('chute des feuilles')
        ->and($periode->debut->format('d/m/Y'))->toBe('01/11/2027')
        ->and($periode->fin->format('d/m/Y'))->toBe('30/11/2027');
});

it('refuse une règle R2 sans fenêtre', function () {
    new Regle('r', 'p', 1, 'R2', 1, [], [], 'verifiee', 'bibliotheque', JeuTemoin::jour('2026-06-01'));
})->throws(InvalidArgumentException::class);

it('ne choisit aucune fenêtre pour une règle qui n\'en a pas', function () {
    $regle = new Regle('r', 'p', 1, 'R1', 1, [], [], 'verifiee', 'bibliotheque', JeuTemoin::jour('2026-06-01'));

    ChoixDeFenetre::prochaine($regle, Historique::vide(), Decalage::jours(0), JeuTemoin::jour('2026-09-01'));
})->throws(LogicException::class, 'Règle sans fenêtre');
