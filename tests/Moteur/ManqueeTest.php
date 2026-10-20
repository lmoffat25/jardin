<?php

// V47 : une fenêtre close sans intervention est manquée (RG-07, critère 6) ;
// aucune manquée pour une fenêtre commencée avant la date d'effet de la règle.

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Etat;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Historique::class);

uses()->group('critere-06');

/** @return list<array{string, string, string, string}> état, due le, début, fin */
function resume(Regle $regle, Historique $historique, string $jour): array
{
    return array_map(fn (Echeance $e) => [
        $e->etat->value,
        $e->dueLe->format('d/m/Y'),
        $e->periode->debut->format('d/m/Y'),
        $e->periode->fin->format('d/m/Y'),
    ], Moteur::calculer([$regle], $historique, Decalage::jours(0), JeuTemoin::jour($jour)));
}

function taillesDuPommier(string ...$dates): Historique
{
    return Historique::depuis(array_map(
        fn (string $date) => ['plante_id' => 'plante-pommier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour($date)],
        $dates,
    ));
}

it('consigne manquée la taille non faite au 1er avril et propose l\'hiver suivant', function () {
    expect(resume(JeuTemoin::pommier(), Historique::vide(), '2027-04-01'))->toBe([
        ['manquee', '01/12/2026', '01/12/2026', '31/03/2027'],
        ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
    ]);
});

it('ne rend aucune manquée pour une fenêtre commencée avant la date d\'effet de la règle', function () {
    expect(resume(JeuTemoin::pommier(effetLe: '2027-01-15'), Historique::vide(), '2027-04-01'))->toBe([
        ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
    ]);
});

it('propose la fenêtre en cours à une règle créée pendant celle-ci', function () {
    expect(resume(JeuTemoin::pommier(effetLe: '2027-01-15'), Historique::vide(), '2027-01-20'))->toBe([
        ['ouverte', '01/12/2026', '01/12/2026', '31/03/2027'],
    ]);
});

it('rend manquée une fenêtre qui commence le jour même de la date d\'effet', function () {
    expect(resume(JeuTemoin::pommier(effetLe: '2026-12-01'), Historique::vide(), '2027-04-01')[0][0])->toBe('manquee');
});

it('consigne chaque hiver manqué, dans l\'ordre', function () {
    expect(resume(JeuTemoin::pommier(effetLe: '2025-06-01'), Historique::vide(), '2027-04-01'))->toBe([
        ['manquee', '01/12/2025', '01/12/2025', '31/03/2026'],
        ['manquee', '01/12/2026', '01/12/2026', '31/03/2027'],
        ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
    ]);
});

it('ne rend pas manquée une fenêtre où la taille a été faite', function () {
    expect(resume(JeuTemoin::pommier(), taillesDuPommier('2027-02-10'), '2027-04-01'))->toBe([
        ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
    ]);
});

it('propose la fenêtre suivante dès que la taille est faite dans la fenêtre en cours', function () {
    expect(resume(JeuTemoin::pommier(), taillesDuPommier('2026-12-10'), '2027-01-15'))->toBe([
        ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
    ]);
});

it('solde la fenêtre d\'une taille faite le jour de son ouverture', function () {
    expect(resume(JeuTemoin::pommier(), taillesDuPommier('2026-12-01'), '2026-12-02'))->toBe([
        ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
    ]);
});

it('ne compte que la dernière intervention de la plante et du type de la règle', function () {
    $historique = Historique::depuis([
        ['plante_id' => 'plante-pommier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2026-01-10')],
        ['plante_id' => 'plante-pommier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2027-02-10')],
        ['plante_id' => 'plante-pommier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2026-02-10')],
        ['plante_id' => 'plante-pommier', 'type_id' => 2, 'fait_le' => JeuTemoin::jour('2027-12-10')],
        ['plante_id' => 'plante-poirier', 'type_id' => 1, 'fait_le' => JeuTemoin::jour('2027-12-10')],
    ]);

    expect($historique->derniere('plante-pommier', 1)?->format('d/m/Y'))->toBe('10/02/2027')
        ->and($historique->derniere('plante-pommier', 3))->toBeNull()
        ->and(resume(JeuTemoin::pommier(), $historique, '2027-04-01'))->toBe([
            ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
        ]);
});

it('ne rend pas une seconde fois manquée une occurrence déjà close dans l\'historique', function () {
    $historique = Historique::depuis([], [
        ['regle_id' => 'regle-pommier', 'due_le' => JeuTemoin::jour('2026-12-01')],
    ]);

    expect($historique->estClose('regle-pommier', JeuTemoin::jour('2026-12-01')))->toBeTrue()
        ->and($historique->estClose('regle-pommier', JeuTemoin::jour('2027-12-01')))->toBeFalse()
        ->and($historique->estClose('regle-cerisier', JeuTemoin::jour('2026-12-01')))->toBeFalse()
        ->and(resume(JeuTemoin::pommier(), $historique, '2027-04-01'))->toBe([
            ['a_venir', '01/12/2027', '01/12/2027', '31/03/2028'],
        ]);
});

it('rend les échéances de chaque règle, une à venir par règle', function () {
    $echeances = Moteur::calculer(
        [JeuTemoin::pommier(), JeuTemoin::cerisier(), JeuTemoin::haie()],
        Historique::vide(),
        Decalage::jours(0),
        JeuTemoin::jour('2027-04-01'),
    );

    expect(array_map(fn (Echeance $e) => [$e->regleId, $e->etat], $echeances))->toBe([
        ['regle-pommier', Etat::Manquee],
        ['regle-pommier', Etat::AVenir],
        ['regle-cerisier', Etat::Manquee],
        ['regle-cerisier', Etat::AVenir],
        ['regle-haie', Etat::Manquee],
        ['regle-haie', Etat::AVenir],
    ]);
});
