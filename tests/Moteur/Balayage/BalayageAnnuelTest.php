<?php

// V59 : les invariants du chapitre 6 §6.2 tiennent chaque jour d'une année
// ordinaire et d'une année bissextile, pour les cinq profils et chaque règle du
// jeu témoin (C11, TS-8). À part de la suite rapide : `make balayage`.
// Les attendus sont recalculés ici par l'arithmétique des mois, sans appeler
// les méthodes du moteur qu'ils vérifient.

use Src\Echeances\Moteur\Calendrier;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Etat;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Tests\Moteur\Temoins\JeuTemoin;

uses()->group('balayage');

/** L'intervalle de la tonte témoin pour un mois du climat de référence ; 0 l'hiver. */
function intervalleTemoin(int $mois): int
{
    return match (true) {
        $mois >= 3 && $mois <= 6 => 7,
        $mois >= 7 && $mois <= 8 => 14,
        $mois >= 9 && $mois <= 11 => 10,
        default => 0,
    };
}

/** Le mois d'une date ramenée au climat de référence (le décalage retiré). */
function moisDeReference(DateTimeImmutable $date, int $decalage): int
{
    return (int) $date->modify(sprintf('%+d days', -$decalage))->format('n');
}

/** La tonte due attendue : l'intervalle non décalé de la saison de la dernière, reporté hors saison. */
function tonteAttendue(?DateTimeImmutable $derniere, DateTimeImmutable $jour, int $decalage): DateTimeImmutable
{
    $due = $derniere === null ? $jour : $derniere->modify(sprintf('+%d days', intervalleTemoin(moisDeReference($derniere, $decalage))));
    $due = max($due, $jour);
    if (intervalleTemoin(moisDeReference($due, $decalage)) > 0) {
        return $due;
    }
    // Hors saison : le 1er mars suivant du climat de référence, décalé.
    $reference = $due->modify(sprintf('%+d days', -$decalage));
    $annee = (int) $reference->format('Y') + ((int) $reference->format('n') >= 3 ? 1 : 0);

    return Calendrier::date($annee, 3, 1)->modify(sprintf('%+d days', $decalage));
}

/** Vrai si [debut, fin] touche la nidification (16/03 → 15/08) d'une année. */
function toucheNidification(DateTimeImmutable $debut, DateTimeImmutable $fin): bool
{
    for ($annee = (int) $debut->format('Y'); $annee <= (int) $fin->format('Y'); $annee++) {
        if ($debut <= Calendrier::date($annee, 8, 15) && Calendrier::date($annee, 3, 16) <= $fin) {
            return true;
        }
    }

    return false;
}

/** @param  list<Echeance>  $echeances */
function identites(array $echeances): array
{
    return array_map(fn (Echeance $e) => [$e->regleId, $e->dueLe->format('Y-m-d'), $e->etat->value], $echeances);
}

/**
 * Les invariants violés un jour donné, en clair.
 *
 * @return list<string>
 */
function violations(DateTimeImmutable $jour, int $decalage, Historique $historique, ?DateTimeImmutable $derniere): array
{
    $echeances = Moteur::calculer(JeuTemoin::toutes(), $historique, Decalage::jours($decalage), $jour);
    $date = $jour->format('d/m/Y').($derniere === null ? '' : ', fait le '.$derniere->format('d/m/Y'));
    $erreurs = [];

    // Deux calculs identiques, sur des règles reconstruites, mêmes identités (CDC §9, 5 bis).
    $second = Moteur::calculer(JeuTemoin::toutes(), $historique, Decalage::jours($decalage), $jour);
    if (identites($second) !== identites($echeances)) {
        $erreurs[] = "{$date} : deux calculs identiques diffèrent";
    }

    $parRegle = [];
    foreach ($echeances as $e) {
        $parRegle[$e->regleId][] = $e;
    }
    foreach (JeuTemoin::toutes() as $regle) {
        // Une règle active, exactement une échéance à venir (RG-40).
        $aVenir = array_values(array_filter($parRegle[$regle->id] ?? [], fn (Echeance $e) => $e->etat !== Etat::Manquee));
        if (count($aVenir) !== 1) {
            $erreurs[] = sprintf('%s : %s a %d échéances à venir', $date, $regle->id, count($aVenir));
        }
    }

    foreach ($parRegle['regle-haie'] ?? [] as $e) {
        // Aucune taille de haie due du 16/03 au 15/08 (RG-44, critère 4).
        if (toucheNidification($e->periode->debut, $e->periode->fin)) {
            $erreurs[] = sprintf('%s : haie ouverte du %s au %s', $date, $e->periode->debut->format('d/m/Y'), $e->periode->fin->format('d/m/Y'));
        }
    }

    foreach ($parRegle['regle-tonte'] ?? [] as $e) {
        // Une échéance R1 n'est jamais due hors saison (critère 15).
        if (intervalleTemoin(moisDeReference($e->dueLe, $decalage)) === 0) {
            $erreurs[] = sprintf('%s : tonte due hors saison le %s', $date, $e->dueLe->format('d/m/Y'));
        }
        // Le décalage déplace les saisons, jamais la valeur d'un intervalle (RG-25).
        $attendue = tonteAttendue($derniere, $jour, $decalage);
        if ($e->dueLe != $attendue) {
            $erreurs[] = sprintf('%s : tonte due le %s, attendue le %s', $date, $e->dueLe->format('d/m/Y'), $attendue->format('d/m/Y'));
        }
    }

    return $erreurs;
}

it('tient les invariants chaque jour de l\'année', function (int $annee, int $decalage) {
    $erreurs = [];
    $jour = Calendrier::date($annee, 1, 1);
    while ((int) $jour->format('Y') === $annee) {
        // Sans historique, puis tout fait la veille : la dernière parcourt l'année.
        $veille = $jour->modify('-1 day');
        array_push($erreurs, ...violations($jour, $decalage, Historique::vide(), null));
        array_push($erreurs, ...violations($jour, $decalage, JeuTemoin::toutesFaitesLe($veille), $veille));
        $jour = $jour->modify('+1 day');
    }

    expect(array_slice($erreurs, 0, 20))->toBe([]);
})->with([2027, 2028])->with(JeuTemoin::PROFILS);
