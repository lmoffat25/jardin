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
            if ($regle->typeRegle === 'R1') {
                $echeances[] = self::intervalleGlissant($regle, $historique, $decalage, $aujourdhui);
            } else {
                array_push($echeances, ...self::fenetreCalendaire($regle, $historique, $decalage, $aujourdhui));
            }
        }

        return $echeances;
    }

    /**
     * R1 : une seule échéance, jamais manquée ni urgente, faute de fenêtre ;
     * `AVenir` avant sa date, `Ouverte` le jour même (en retard, elle a été
     * ramenée à aujourd'hui).
     */
    private static function intervalleGlissant(Regle $regle, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): Echeance
    {
        $due = self::prochaineR1($regle, $historique->derniere($regle->planteId, $regle->typeId), $decalage, $aujourdhui);
        // La date due est toujours en saison (étapes 3 et 4).
        [$saison, $periode] = $regle->saisonDe($due, $decalage) ?? throw new \LogicException('Échéance R1 hors saison');

        return new Echeance(
            $regle->id,
            $due,
            $periode,
            $due > $aujourdhui ? Etat::AVenir : Etat::Ouverte,
            new Explication($regle->typeRegle, null, $periode, $decalage->valeur(), $regle->confiance, $regle->origine, $saison),
        );
    }

    /**
     * Le report hors saison (chapitre 3 §7, étapes 1 à 4, D-10). L'étape 4
     * passe avant l'étape 3 : ramener d'abord une date passée à aujourd'hui,
     * puis la reporter si elle tombe hors saison, rend la même date que l'ordre
     * du pseudo-code (D-90). Deux saisons n'ont aucun mois commun (Regle) :
     * l'ouverture suivante est toujours en saison, un seul report suffit.
     */
    private static function prochaineR1(Regle $regle, ?\DateTimeImmutable $derniere, Decalage $decalage, \DateTimeImmutable $aujourdhui): \DateTimeImmutable
    {
        // 1. jamais faite : aujourd'hui ; 2. sinon l'intervalle de la saison de la dernière.
        $due = $derniere?->modify(sprintf('+%d days', $regle->intervalleDe($derniere, $decalage))) ?? $aujourdhui;
        $due = max($due, $aujourdhui);                                 // 4. en retard : aujourd'hui
        if ($regle->intervalleDe($due, $decalage) === 0) {             // 3. hors saison, y compris au cas 1 (D-10)
            $due = $regle->ouvertureSuivante($due, $decalage);
        }

        return $due;
    }

    /** @return list<Echeance> */
    private static function fenetreCalendaire(Regle $regle, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): array
    {
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
