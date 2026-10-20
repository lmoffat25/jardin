<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Une saison d'une règle R1 : de `moisDebut` à `moisFin` (à cheval sur l'année
 * si `moisFin` précède), un intervalle de `intervalleJours` ; 0 = hors saison.
 */
final readonly class IntervalleSaisonnier
{
    public function __construct(
        public int $moisDebut,
        public int $moisFin,
        public int $intervalleJours,
    ) {
        foreach ([$moisDebut, $moisFin] as $mois) {
            if ($mois < 1 || $mois > 12) {
                throw new \InvalidArgumentException("Mois impossible : {$mois}");
            }
        }
        if ($intervalleJours < 0) {
            throw new \InvalidArgumentException("Intervalle négatif : {$intervalleJours}");
        }
    }

    /** @return list<int> les mois de la saison, dans l'ordre */
    public function mois(): array
    {
        $mois = [$this->moisDebut];
        while (end($mois) !== $this->moisFin) {
            $mois[] = end($mois) % 12 + 1;
        }

        return $mois;
    }

    /**
     * La saison qui commence en $anneeDeDebut : du 1er du mois de début au
     * dernier jour du mois de fin, décalée sans butoir (chapitre 3 §7, RG-25).
     */
    public function projeter(int $anneeDeDebut, Decalage $decalage): Periode
    {
        $anneeDeFin = $anneeDeDebut + ($this->moisFin < $this->moisDebut ? 1 : 0);

        return Periode::rognee(
            $decalage->appliquer(Calendrier::date($anneeDeDebut, $this->moisDebut, 1)),
            $decalage->appliquer(Calendrier::dernierJourDuMois($anneeDeFin, $this->moisFin)),
        );
    }
}
