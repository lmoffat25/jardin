<?php

// V53 : une occurrence close (suspendue ou manquée matérialisée, fournie par
// l'historique) n'est plus proposée ni rendue manquée une seconde fois ; la
// suivante est proposée (CDC §4.4, D-05). V75 : une occurrence close sous un
// autre profil reste close quand le profil change (D-99).

use Src\Echeances\Moteur\ChoixDeFenetre;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(ChoixDeFenetre::class, Moteur::class, Historique::class);

/** @return list<array{string, string}> état, due le */
function apresCloture(Regle $regle, array $closes, string $jour): array
{
    $historique = Historique::depuis([], array_map(
        fn (string $dueLe) => ['regle_id' => $regle->id, 'due_le' => JeuTemoin::jour($dueLe)],
        $closes,
    ));

    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([$regle], $historique, Decalage::jours(0), JeuTemoin::jour($jour)),
    );
}

it('ne propose plus une fenêtre en cours suspendue, mais la suivante', function () {
    expect(apresCloture(JeuTemoin::pommier(), ['2026-12-01'], '2027-01-15'))->toBe([
        ['a_venir', '01/12/2027'],
    ]);
});

it('passe à l\'autre fenêtre quand celle en cours est suspendue', function () {
    expect(apresCloture(JeuTemoin::deuxPassages(), ['2026-11-01'], '2026-11-10'))->toBe([
        ['a_venir', '01/02/2027'],
    ]);
});

it('saute une fenêtre à venir déjà suspendue', function () {
    expect(apresCloture(JeuTemoin::deuxPassages(), ['2027-02-01'], '2026-12-15'))->toBe([
        ['manquee', '01/11/2026'],
        ['a_venir', '01/11/2027'],
    ]);
});

it('ne rend pas une seconde fois manquée une fenêtre déjà matérialisée manquée', function () {
    expect(apresCloture(JeuTemoin::deuxPassages(), ['2026-11-01'], '2027-03-15'))->toBe([
        ['manquee', '01/02/2027'],
        ['a_venir', '01/11/2027'],
    ]);
});

it('ne rend aucune manquée quand toutes sont closes', function () {
    expect(apresCloture(JeuTemoin::deuxPassages(), ['2026-11-01', '2027-02-01'], '2027-03-15'))->toBe([
        ['a_venir', '01/11/2027'],
    ]);
});

it('ignore les occurrences closes d\'une autre règle', function () {
    $historique = Historique::depuis([], [['regle_id' => 'regle-pommier', 'due_le' => JeuTemoin::jour('2026-11-01')]]);

    [, $periode] = ChoixDeFenetre::prochaine(JeuTemoin::deuxPassages(), $historique, Decalage::jours(0), JeuTemoin::jour('2026-11-10'));

    expect($periode->debut->format('d/m/Y'))->toBe('01/11/2026');
});

/**
 * Closes matérialisées sous le décalage d'un autre profil, lues sous un profil
 * « Très doux » (-15 jours).
 *
 * @param  array<string, int>  $closes  due le => décalage appliqué
 * @return list<array{string, string}> état, due le
 */
function apresChangementDeProfil(array $closes, string $jour): array
{
    $historique = Historique::depuis([], array_map(
        fn (string $dueLe, int $decalage) => ['regle_id' => 'regle-deux-passages', 'due_le' => JeuTemoin::jour($dueLe), 'decalage_j' => $decalage],
        array_keys($closes),
        $closes,
    ));

    return array_map(
        fn (Echeance $e) => [$e->etat->value, $e->dueLe->format('d/m/Y')],
        Moteur::calculer([JeuTemoin::deuxPassages()], $historique, Decalage::jours(-15), JeuTemoin::jour($jour)),
    );
}

it('ne rend pas une seconde fois manquée une occurrence close sous un autre profil (V75)', function () {
    // Manquée en « Frais » (+7) : 08/11/2026. Lue en « Très doux », la même occurrence commence le 17/10/2026.
    expect(apresChangementDeProfil(['2026-11-08' => 7], '2027-03-15'))->toBe([
        ['manquee', '17/01/2027'],
        ['a_venir', '17/10/2027'],
    ])
        // Une close de la fenêtre d'hiver, sous l'autre profil, ne ferme qu'elle.
        ->and(apresChangementDeProfil(['2027-02-08' => 7], '2027-03-15'))->toBe([
            ['manquee', '17/10/2026'],
            ['a_venir', '17/10/2027'],
        ])
        // Celle de l'automne suivant, déjà suspendue, ne ferme que cette année-là.
        ->and(apresChangementDeProfil(['2027-11-08' => 7], '2027-03-15'))->toBe([
            ['manquee', '17/10/2026'],
            ['manquee', '17/01/2027'],
            ['a_venir', '17/01/2028'],
        ]);
});
