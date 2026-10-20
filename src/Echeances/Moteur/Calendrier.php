<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Les dates du moteur : à minuit, heure de Paris. Le moteur ne lit jamais
 * l'horloge (TS-4) : la date du jour lui est passée.
 */
final class Calendrier
{
    private const FUSEAU = 'Europe/Paris';

    /** Le 29/02 d'une année ordinaire devient le 28/02 (D-09) ; toute autre date impossible est refusée. */
    public static function date(int $annee, int $mois, int $jour): \DateTimeImmutable
    {
        if ($mois === 2 && $jour === 29 && ! checkdate(2, 29, $annee)) {
            $jour = 28;
        }
        if (! checkdate($mois, $jour, $annee)) {
            throw new \InvalidArgumentException(sprintf('Date impossible : %02d/%02d/%d', $jour, $mois, $annee));
        }

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $annee, $mois, $jour), new \DateTimeZone(self::FUSEAU));
    }

    public static function dernierJourDuMois(int $annee, int $mois): \DateTimeImmutable
    {
        return self::date($annee, $mois, (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $mois)))->format('t'));
    }

    /** Le jour civil d'une date, dans son propre fuseau, ramené à minuit à Paris. */
    public static function jour(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return self::date((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    /**
     * La fenêtre en cours, sinon la prochaine (chapitre 3 §7) : trois années
     * candidates, la précédente d'abord, pour la fenêtre à cheval en cours.
     */
    public static function prochaineFenetre(Fenetre $fenetre, Decalage $decalage, \DateTimeImmutable $aujourdhui): Periode
    {
        $aujourdhui = self::jour($aujourdhui);
        $annee = (int) $aujourdhui->format('Y');
        foreach ([$annee - 1, $annee, $annee + 1] as $anneeDeDebut) {
            $periode = $fenetre->projeter($anneeDeDebut, $decalage);
            if (! $periode->estVide() && $aujourdhui <= $periode->fin) {
                return $periode;
            }
        }

        // Refusée en amont, à la publication de la bibliothèque (B-T13, V104).
        throw new \LogicException('Fenêtre vide trois années de suite');
    }
}
