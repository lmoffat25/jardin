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

        // Les manquées, puis l'unique échéance à venir, toutes fenêtres confondues (A8).
        $echeances = [];
        foreach (ChoixDeFenetre::parcours($regle, $historique, $decalage, $aujourdhui) as [$fenetre, $periode]) {
            $echeances[] = self::echeance($regle, $fenetre, $periode, self::etat($periode, $aujourdhui), $decalage);
        }

        return $echeances;
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
