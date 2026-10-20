<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Le parcours des occurrences d'une règle R2, toutes fenêtres confondues
 * (RG-36, chapitre 3 §7) : chaque fenêtre est projetée indépendamment, la plus
 * proche passe d'abord, et la règle ne rend qu'une occurrence à venir (A8).
 */
final class ChoixDeFenetre
{
    /**
     * Les occurrences à rendre, dans l'ordre : les manquées, puis la prochaine,
     * toujours la dernière de la liste (RG-40).
     *
     * Chaque fenêtre repart de celle de ses occurrences commencée à la dernière
     * intervention ou juste avant, plus `n` ans (D-88) ; sans intervention, de
     * celle commencée à la date d'effet ou juste avant. Une occurrence close
     * (suspendue, manquée, soldée : D-05) n'est jamais rendue, même close sous un
     * autre profil (D-99). Une manquée est
     * finie avant aujourd'hui et commence au plus tôt à la date d'effet (V47).
     *
     * @return non-empty-list<array{0: Fenetre, 1: Periode}>
     */
    public static function parcours(Regle $regle, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): array
    {
        $fenetres = $regle->fenetres;
        if ($fenetres === []) {
            // Une règle R2 en a toujours une (Regle) ; une R1 n'a rien à faire ici.
            throw new \LogicException('Règle sans fenêtre');
        }
        $aujourdhui = Calendrier::jour($aujourdhui);
        $effetLe = Calendrier::jour($regle->effetLe);
        $derniere = $historique->derniere($regle->planteId, $regle->typeId);

        $annees = [];
        foreach ($fenetres as $i => $fenetre) {
            $annees[$i] = $derniere === null
                ? self::anneeDe($fenetre, $decalage, $effetLe)
                : self::anneeDe($fenetre, $decalage, $derniere) + $regle->periodiciteAnnees;
        }

        $occurrences = [];
        while (true) {
            [$i, $periode] = self::plusProche($fenetres, $annees, $decalage);
            $close = $historique->occurrenceClose($regle->id, $fenetres[$i], $annees[$i], $decalage);
            $annees[$i]++;
            if (! $close && $aujourdhui <= $periode->fin) {
                $occurrences[] = [$fenetres[$i], $periode];

                return $occurrences;
            }
            if (! $close && $periode->debut >= $effetLe) {
                $occurrences[] = [$fenetres[$i], $periode];
            }
        }
    }

    /**
     * La prochaine occurrence de la règle, la seule à venir (A8).
     *
     * @return array{0: Fenetre, 1: Periode}
     */
    public static function prochaine(Regle $regle, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): array
    {
        $occurrences = self::parcours($regle, $historique, $decalage, $aujourdhui);

        return $occurrences[count($occurrences) - 1];
    }

    /**
     * La fenêtre dont l'occurrence courante commence le plus tôt ; à égalité,
     * la première de la liste, triée par ordre déclaré (Regle).
     *
     * @param  non-empty-list<Fenetre>  $fenetres
     * @param  array<int, int>  $annees
     * @return array{0: int, 1: Periode}
     */
    private static function plusProche(array $fenetres, array $annees, Decalage $decalage): array
    {
        $choix = null;
        foreach ($fenetres as $i => $fenetre) {
            $periode = $fenetre->projeter($annees[$i], $decalage);
            if ($periode->estVide()) {
                // Refusée en amont, à la publication de la bibliothèque (B-T13, V104).
                throw new \LogicException('Fenêtre vidée par ses butoirs');
            }
            if ($choix === null || $periode->debut < $choix[1]->debut) {
                $choix = [$i, $periode];
            }
        }

        return $choix;
    }

    /** L'année de début de la dernière occurrence commencée à cette date : celle qui la contient, ou la précédente. */
    private static function anneeDe(Fenetre $fenetre, Decalage $decalage, \DateTimeImmutable $date): int
    {
        $annee = (int) $date->format('Y');

        return $fenetre->projeter($annee, $decalage)->debut <= $date ? $annee : $annee - 1;
    }
}
