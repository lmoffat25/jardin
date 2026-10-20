<?php

// V46 : chaque jour d'une fenêtre a exactement un état, selon la cascade
// ordonnée du chapitre 3 §7 : manquée, à venir, urgente, optimale, ouverte (RG-40).

use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Etat;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Echeances\Moteur\Regle;
use Tests\Moteur\Temoins\JeuTemoin;

mutates(Moteur::class, Etat::class);

/** @return list<Echeance> */
function echeancesDuJour(Regle $regle, DateTimeImmutable $jour, int $decalage = 0): array
{
    return Moteur::calculer([$regle], Historique::vide(), Decalage::jours($decalage), $jour);
}

/** @return array<string, Etat> date d/m/Y => état attendu, pour chaque jour de $du à $au */
function etatsAttendus(string $du, string $au, callable $etat): array
{
    $attendus = [];
    for ($j = JeuTemoin::jour($du); $j <= JeuTemoin::jour($au); $j = $j->modify('+1 day')) {
        $attendus[$j->format('d/m/Y')] = $etat($j);
    }

    return $attendus;
}

it('donne chaque jour un seul état à la taille du pommier, du 01/09/2026 au 30/04/2027', function () {
    $pommier = JeuTemoin::pommier();
    $attendus = etatsAttendus('2026-09-01', '2027-04-30', fn (DateTimeImmutable $j) => match (true) {
        $j < JeuTemoin::jour('2026-12-01') => Etat::AVenir,
        $j < JeuTemoin::jour('2027-02-01') => Etat::Ouverte,
        $j <= JeuTemoin::jour('2027-03-15') => Etat::Optimale,
        $j <= JeuTemoin::jour('2027-03-31') => Etat::Urgente,
        default => Etat::Manquee,
    });

    $observes = [];
    foreach (array_keys($attendus) as $date) {
        $echeances = echeancesDuJour($pommier, DateTimeImmutable::createFromFormat('!d/m/Y', $date, new DateTimeZone('Europe/Paris')));
        $fenetre = array_values(array_filter($echeances, fn (Echeance $e) => $e->dueLe == JeuTemoin::jour('2026-12-01')));
        $aVenir = array_filter($echeances, fn (Echeance $e) => $e->etat !== Etat::Manquee);

        expect($fenetre)->toHaveCount(1)->and($aVenir)->toHaveCount(1);
        $observes[$date] = $fenetre[0]->etat;
    }

    expect($observes)->toBe($attendus);
});

it('laisse ouverte la fenêtre du cerisier entre la fin de l\'optimale et l\'urgence', function () {
    $attendus = etatsAttendus('2026-07-31', '2026-10-01', fn (DateTimeImmutable $j) => match (true) {
        $j < JeuTemoin::jour('2026-08-01') => Etat::AVenir,
        $j <= JeuTemoin::jour('2026-08-31') => Etat::Optimale,
        $j <= JeuTemoin::jour('2026-09-14') => Etat::Ouverte,
        $j <= JeuTemoin::jour('2026-09-30') => Etat::Urgente,
        default => Etat::Manquee,
    });

    $observes = [];
    foreach (array_keys($attendus) as $date) {
        $jour = DateTimeImmutable::createFromFormat('!d/m/Y', $date, new DateTimeZone('Europe/Paris'));
        $echeances = echeancesDuJour(JeuTemoin::cerisier(), $jour);
        $observes[$date] = $echeances[0]->etat;
    }

    expect($observes)->toBe($attendus);
});

it('fait passer l\'urgence avant l\'optimale quand l\'optimale court jusqu\'à la fin', function () {
    $cerisier = JeuTemoin::cerisier(optimalDebut: '01/09', optimalFin: '30/09');

    expect(echeancesDuJour($cerisier, JeuTemoin::jour('2026-09-14'))[0]->etat)->toBe(Etat::Optimale)
        ->and(echeancesDuJour($cerisier, JeuTemoin::jour('2026-09-15'))[0]->etat)->toBe(Etat::Urgente)
        ->and(echeancesDuJour($cerisier, JeuTemoin::jour('2026-09-30'))[0]->etat)->toBe(Etat::Urgente);
});

it('juge l\'état sur la fenêtre décalée, pas sur celle de la bibliothèque', function () {
    $pommier = JeuTemoin::pommier();

    expect(echeancesDuJour($pommier, JeuTemoin::jour('2026-12-07'), 7)[0]->etat)->toBe(Etat::AVenir)
        ->and(echeancesDuJour($pommier, JeuTemoin::jour('2026-12-08'), 7)[0]->etat)->toBe(Etat::Ouverte)
        ->and(echeancesDuJour($pommier, JeuTemoin::jour('2027-04-07'), 7)[0]->etat)->toBe(Etat::Urgente)
        ->and(echeancesDuJour($pommier, JeuTemoin::jour('2027-04-08'), 7)[0]->etat)->toBe(Etat::Manquee);
});

it('rend une fenêtre sans optimale ouverte jusqu\'à l\'urgence', function () {
    $haie = JeuTemoin::haie();

    expect(echeancesDuJour($haie, JeuTemoin::jour('2026-08-16'))[0]->etat)->toBe(Etat::Ouverte)
        ->and(echeancesDuJour($haie, JeuTemoin::jour('2027-02-27'))[0]->etat)->toBe(Etat::Ouverte)
        ->and(echeancesDuJour($haie, JeuTemoin::jour('2027-02-28'))[0]->etat)->toBe(Etat::Urgente);
});
