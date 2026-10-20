<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * Une fenêtre R2 telle que la bibliothèque l'écrit : des jours/mois dans le
 * climat de référence, une optimale et des butoirs facultatifs (RG-44).
 */
final readonly class Fenetre
{
    public function __construct(
        public JourMois $debut,
        public JourMois $fin,
        public ?JourMois $optimalDebut = null,
        public ?JourMois $optimalFin = null,
        public ?JourMois $butoirDebut = null,
        public ?JourMois $butoirFin = null,
        public ?string $libelle = null,
        public int $ordre = 1,
    ) {}

    public function traverseAnnee(): bool
    {
        return $this->fin->avant($this->debut);
    }

    /** La fenêtre qui commence en $anneeDeDebut, décalée puis rognée à ses butoirs (A25). */
    public function projeter(int $anneeDeDebut, Decalage $decalage): Periode
    {
        $anneeDeFin = $anneeDeDebut + ($this->traverseAnnee() ? 1 : 0);

        // 1. Décaler les deux bornes et l'optimale (RG-25, RG-26).
        $debut = $decalage->appliquer($this->dateDans($anneeDeDebut, $this->debut));
        $fin = $decalage->appliquer(Calendrier::date($anneeDeFin, $this->fin->mois, $this->fin->jour));
        $optimalDebut = $this->optimalDebut === null ? null : $decalage->appliquer($this->dateDans($anneeDeDebut, $this->optimalDebut));
        $optimalFin = $this->optimalFin === null ? null : $decalage->appliquer($this->dateDans($anneeDeDebut, $this->optimalFin));

        // 2. Rogner ensuite : un butoir est une date fixe, jamais décalée (RG-44, A25) ;
        //    celui de début sur l'année de début (D-09), celui de fin à sa première
        //    occurrence depuis le début de la fenêtre (D-96).
        if ($this->butoirDebut !== null) {
            $debut = max($debut, Calendrier::date($anneeDeDebut, $this->butoirDebut->mois, $this->butoirDebut->jour));
        }
        if ($this->butoirFin !== null) {
            $fin = min($fin, $this->dateDans($anneeDeDebut, $this->butoirFin));
        }

        // 3. L'optimale est contenue dans la fenêtre rognée (D-09).
        return Periode::rognee($debut, $fin, $optimalDebut, $optimalFin);
    }

    /** Un jour/mois de la fenêtre qui commence en $anneeDeDebut : l'année suivante s'il précède le début. */
    private function dateDans(int $anneeDeDebut, JourMois $jourMois): \DateTimeImmutable
    {
        $annee = $anneeDeDebut + ($jourMois->avant($this->debut) ? 1 : 0);

        return Calendrier::date($annee, $jourMois->mois, $jourMois->jour);
    }
}
