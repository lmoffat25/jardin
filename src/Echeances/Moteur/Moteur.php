<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Le calcul des échéances (chapitre 3 §6 et §7) : pur, sans base ni horloge
 * (A3), il ne connaît que des règles et un historique (A4, A5) et ne persiste
 * rien (A6).
 */
final class Moteur
{
    /**
     * Pour chaque règle : ses occurrences manquées, dans l'ordre, puis son
     * unique échéance à venir (RG-40).
     *
     * @param  list<Regle>  $regles
     * @return list<Echeance>
     */
    public static function calculer(array $regles, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): array
    {
        $aujourdhui = Calendrier::jour($aujourdhui);
        $echeances = [];
        foreach ($regles as $regle) {
            array_push($echeances, ...self::fenetreCalendaire($regle, $historique, $decalage, $aujourdhui));
        }

        return $echeances;
    }

    /** @return list<Echeance> */
    private static function fenetreCalendaire(Regle $regle, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): array
    {
        if ($regle->typeRegle === 'R1') {
            throw new \LogicException('R1 au sprint 3d');
        }
        if (count($regle->fenetres) !== 1) {
            throw new \LogicException('Fenêtres multiples au sprint 3c');
        }
        $fenetre = $regle->fenetres[0];
        $effetLe = Calendrier::jour($regle->effetLe);
        $derniere = $historique->derniere($regle->planteId, $regle->typeId);

        // La fenêtre de la dernière intervention est soldée ; la périodicité saute
        // les n − 1 suivantes, qui ne sont jamais manquées (V48). Sans intervention,
        // on part de la fenêtre commencée à la date d'effet, ou juste avant.
        $annee = $derniere === null
            ? self::anneeDe($fenetre, $decalage, $effetLe)
            : self::anneeDe($fenetre, $decalage, $derniere) + $regle->periodiciteAnnees;

        $echeances = [];
        while (true) {
            $periode = $fenetre->projeter($annee++, $decalage);
            if ($periode->estVide()) {
                // Refusée en amont, à la publication de la bibliothèque (B-T13, V104).
                throw new \LogicException('Fenêtre vidée par ses butoirs');
            }
            $etat = self::etat($periode, $aujourdhui);
            if ($etat !== Etat::Manquee) {
                $echeances[] = self::echeance($regle, $fenetre, $periode, $etat, $decalage);

                return $echeances;
            }
            // Une manquée : postérieure à la date d'effet, et pas déjà close (V47, D-05).
            if ($periode->debut >= $effetLe && ! $historique->estClose($regle->id, $periode->debut)) {
                $echeances[] = self::echeance($regle, $fenetre, $periode, $etat, $decalage);
            }
        }
    }

    /**
     * La cascade du chapitre 3 §7 : l'ordre est normatif, la première branche
     * vraie l'emporte. Une période parcourue ici ne contient aucune intervention :
     * celle qui en contenait une a été sautée.
     */
    private static function etat(Periode $periode, \DateTimeImmutable $jour): Etat
    {
        if ($jour > $periode->fin) {
            return Etat::Manquee;
        }
        if ($jour < $periode->debut) {
            return Etat::AVenir;
        }
        // Urgente dans les 15 derniers jours (X, D-18, provisoire : Q-UX-9).
        if ($jour >= $periode->fin->modify('-15 days')) {
            return Etat::Urgente;
        }
        if ($periode->dansOptimale($jour)) {
            return Etat::Optimale;
        }

        return Etat::Ouverte;
    }

    /** L'année de début de la dernière fenêtre commencée à cette date : celle qui la contient, ou la précédente. */
    private static function anneeDe(Fenetre $fenetre, Decalage $decalage, \DateTimeImmutable $date): int
    {
        $annee = (int) $date->format('Y');

        return $fenetre->projeter($annee, $decalage)->debut <= $date ? $annee : $annee - 1;
    }

    /** `dueLe` est le début de la période projetée, décalée et rognée (D-07). */
    private static function echeance(Regle $regle, Fenetre $fenetre, Periode $periode, Etat $etat, Decalage $decalage): Echeance
    {
        return new Echeance(
            $regle->id,
            $periode->debut,
            $periode,
            $etat,
            new Explication($regle->typeRegle, $fenetre, $periode, $decalage->valeur(), $regle->confiance, $regle->origine),
        );
    }
}
