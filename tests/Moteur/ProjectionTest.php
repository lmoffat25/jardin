<?php

// V42 et V45 : une fenêtre jour/mois est projetée en dates absolues sur la
// bonne année (A7, TS-6 n° 1 et 2), et le 29 février n'est pas une erreur (D-09).

use Src\Echeances\Moteur\Calendrier;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Periode;

mutates(Calendrier::class, Fenetre::class, Periode::class, JourMois::class);

function jour(string $date): DateTimeImmutable
{
    [$j, $m, $a] = array_map('intval', explode('/', $date));

    return Calendrier::date($a, $m, $j);
}

function bornes(Periode $p): array
{
    return [$p->debut->format('d/m/Y'), $p->fin->format('d/m/Y')];
}

function hiver(): Fenetre
{
    return new Fenetre(new JourMois(1, 12), new JourMois(31, 3));
}

it('prend la fenêtre de l\'année précédente quand elle est en cours (15/01/2027)', function () {
    $p = Calendrier::prochaineFenetre(hiver(), Decalage::jours(0), jour('15/01/2027'));

    expect(bornes($p))->toBe(['01/12/2026', '31/03/2027']);
});

it('prend la fenêtre suivante quand la courante est passée (15/06/2027)', function () {
    $p = Calendrier::prochaineFenetre(hiver(), Decalage::jours(0), jour('15/06/2027'));

    expect(bornes($p))->toBe(['01/12/2027', '31/03/2028']);
});

it('garde la fenêtre jusqu\'à son dernier jour inclus, puis passe à la suivante', function (string $aujourdhui, array $attendu) {
    expect(bornes(Calendrier::prochaineFenetre(hiver(), Decalage::jours(0), jour($aujourdhui))))->toBe($attendu);
})->with([
    'premier jour' => ['01/12/2026', ['01/12/2026', '31/03/2027']],
    'veille' => ['30/11/2026', ['01/12/2026', '31/03/2027']],
    'dernier jour' => ['31/03/2027', ['01/12/2026', '31/03/2027']],
    'lendemain' => ['01/04/2027', ['01/12/2027', '31/03/2028']],
]);

it('projette une fenêtre qui ne traverse pas l\'année sur l\'année en cours ou la suivante', function (string $aujourdhui, array $attendu) {
    $ete = new Fenetre(new JourMois(1, 6), new JourMois(30, 9));

    expect(bornes(Calendrier::prochaineFenetre($ete, Decalage::jours(0), jour($aujourdhui))))->toBe($attendu);
})->with([
    'avant' => ['15/01/2027', ['01/06/2027', '30/09/2027']],
    'pendant' => ['15/07/2027', ['01/06/2027', '30/09/2027']],
    'après' => ['15/10/2027', ['01/06/2028', '30/09/2028']],
]);

it('dit qu\'une fenêtre traverse l\'année quand sa fin précède son début', function (int $finJour, int $finMois, bool $traverse) {
    expect((new Fenetre(new JourMois(1, 12), new JourMois($finJour, $finMois)))->traverseAnnee())->toBe($traverse);
})->with([
    'fin en mars' => [31, 3, true],
    'fin la veille du début' => [30, 11, true],
    'fin le jour du début' => [1, 12, false],
    'fin en fin d\'année' => [31, 12, false],
]);

it('lit le jour civil d\'aujourd\'hui, quel que soit son fuseau', function () {
    // 31/03/2027 à minuit UTC : 02:00 à Paris, toujours le dernier jour de la fenêtre.
    $aujourdhui = new DateTimeImmutable('2027-03-31 00:00:00', new DateTimeZone('UTC'));

    expect(bornes(Calendrier::prochaineFenetre(hiver(), Decalage::jours(0), $aujourdhui)))->toBe(['01/12/2026', '31/03/2027']);
});

it('refuse une fenêtre vide trois années de suite (B-T13)', function () {
    $videe = new Fenetre(new JourMois(1, 3), new JourMois(31, 3), butoirDebut: new JourMois(1, 4));

    Calendrier::prochaineFenetre($videe, Decalage::jours(0), jour('15/01/2027'));
})->throws(LogicException::class);

it('projette l\'optimale dans l\'année de la fenêtre où elle tombe', function () {
    $taille = new Fenetre(new JourMois(1, 12), new JourMois(31, 3), new JourMois(15, 12), new JourMois(15, 3));
    $p = $taille->projeter(2026, Decalage::jours(0));

    expect([$p->optimalDebut->format('d/m/Y'), $p->optimalFin->format('d/m/Y')])->toBe(['15/12/2026', '15/03/2027']);
});

it('rend une période sans optimale quand la fenêtre n\'en a pas', function () {
    $p = hiver()->projeter(2026, Decalage::jours(0));

    expect($p->optimalDebut)->toBeNull()->and($p->optimalFin)->toBeNull();
});

it('ne garde pas une optimale dont une borne manque', function (bool $debutSeul) {
    $d = jour('01/02/2027');
    $p = Periode::rognee(jour('01/12/2026'), jour('31/03/2027'), $debutSeul ? $d : null, $debutSeul ? null : $d);

    expect($p->optimalDebut)->toBeNull()->and($p->optimalFin)->toBeNull();
})->with(['début seul' => true, 'fin seule' => false]);

it('est la première fenêtre de sa règle, sans libellé, par défaut', function () {
    expect(hiver()->ordre)->toBe(1)->and(hiver()->libelle)->toBeNull();
});

it('contient ses deux bornes et rien au-delà', function (string $date, bool $contient) {
    expect(hiver()->projeter(2026, Decalage::jours(0))->contient(jour($date)))->toBe($contient);
})->with([
    ['30/11/2026', false],
    ['01/12/2026', true],
    ['15/01/2027', true],
    ['31/03/2027', true],
    ['01/04/2027', false],
]);

it('contient le jour civil, quel que soit le fuseau de la date', function () {
    $finUtc = new DateTimeImmutable('2027-03-31 00:00:00', new DateTimeZone('UTC'));
    $veilleTokyo = new DateTimeImmutable('2026-12-01 00:00:00', new DateTimeZone('Asia/Tokyo'));

    expect(hiver()->projeter(2026, Decalage::jours(0))->contient($finUtc))->toBeTrue()
        ->and(hiver()->projeter(2026, Decalage::jours(0))->contient($veilleTokyo))->toBeTrue();
});

it('est vide quand le début dépasse la fin, pas quand ils coïncident', function () {
    $unJour = new Fenetre(new JourMois(1, 3), new JourMois(1, 3));
    $videe = new Fenetre(new JourMois(1, 3), new JourMois(31, 3), butoirDebut: new JourMois(1, 4));

    expect($unJour->projeter(2027, Decalage::jours(0))->estVide())->toBeFalse()
        ->and(hiver()->projeter(2026, Decalage::jours(0))->estVide())->toBeFalse()
        ->and($videe->projeter(2027, Decalage::jours(0))->estVide())->toBeTrue();
});

it('construit des dates à minuit, heure de Paris', function () {
    $d = Calendrier::date(2027, 7, 14);

    expect($d->format('Y-m-d H:i:s'))->toBe('2027-07-14 00:00:00')
        ->and($d->getTimezone()->getName())->toBe('Europe/Paris');
});

// V45

it('projette une borne au 29/02 au 29/02 les années bissextiles, au 28/02 sinon', function (int $annee, string $fin) {
    $fevrier = new Fenetre(new JourMois(1, 2), new JourMois(29, 2));
    $p = $fevrier->projeter($annee, Decalage::jours(0));

    expect(bornes($p))->toBe(['01/02/'.$annee, $fin.'/02/'.$annee])
        ->and($p->estVide())->toBeFalse();
})->with([
    [2026, '28'], [2027, '28'], [2028, '29'], [2029, '28'],
    [2030, '28'], [2031, '28'], [2032, '29'], [2100, '28'], [2000, '29'],
]);

it('ramène le 29/02 au 28/02 des années ordinaires', function () {
    expect(Calendrier::date(2027, 2, 29)->format('d/m/Y'))->toBe('28/02/2027')
        ->and(Calendrier::date(2028, 2, 29)->format('d/m/Y'))->toBe('29/02/2028');
});

it('refuse toute autre date impossible', function (int $annee, int $mois, int $jour) {
    Calendrier::date($annee, $mois, $jour);
})->throws(InvalidArgumentException::class)->with([
    [2028, 2, 30], [2027, 4, 31], [2027, 13, 1], [2027, 0, 1], [2027, 1, 0], [2027, 1, 32],
]);

it('refuse un jour/mois qui n\'existe aucune année', function (int $jour, int $mois) {
    new JourMois($jour, $mois);
})->throws(InvalidArgumentException::class)->with([
    [30, 2], [31, 4], [0, 1], [32, 1], [1, 0], [1, 13],
]);

it('accepte le 29/02 et les fins de mois comme jour/mois', function (int $jour, int $mois) {
    expect((new JourMois($jour, $mois))->jour)->toBe($jour);
})->with([[29, 2], [31, 12], [30, 4], [1, 1]]);
