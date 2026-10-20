<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/** Une règle active, telle que le pont la traduit pour le moteur (A5). */
final readonly class Regle
{
    /** @var list<Fenetre> R2, triées par ordre déclaré (RG-36) */
    public array $fenetres;

    /**
     * @param  list<Fenetre>  $fenetres  R2
     * @param  list<IntervalleSaisonnier>  $intervalles  R1 : des saisons sans mois commun, une au moins active
     */
    public function __construct(
        public string $id,
        public string $planteId,
        public int $typeId,
        public string $typeRegle,
        public int $periodiciteAnnees,
        array $fenetres,
        public array $intervalles,
        public string $confiance,
        public string $origine,
        public \DateTimeImmutable $effetLe,
    ) {
        self::exiger($typeRegle, ['R1', 'R2'], 'Type de règle');
        self::exiger($confiance, ['verifiee', 'indicative'], 'Confiance');
        self::exiger($origine, ['bibliotheque', 'ajustee', 'libre'], 'Origine');
        if ($periodiciteAnnees < 1) {
            throw new \InvalidArgumentException("Périodicité inférieure à un an : {$periodiciteAnnees}");
        }
        if ($typeRegle === 'R2' && $fenetres === []) {
            throw new \InvalidArgumentException('Règle R2 sans fenêtre');
        }
        if ($typeRegle === 'R1') {
            self::exigerSaisons($intervalles);
        }
        // usort est stable : deux fenêtres de même ordre gardent leur ordre de déclaration.
        usort($fenetres, fn (Fenetre $a, Fenetre $b) => $a->ordre <=> $b->ordre);
        $this->fenetres = $fenetres;
    }

    /**
     * L'intervalle de la saison qui contient $date, bornes décalées ; 0 hors
     * saison. La valeur n'est jamais décalée (RG-25).
     */
    public function intervalleDe(\DateTimeImmutable $date, Decalage $decalage): int
    {
        return $this->saisonDe($date, $decalage)[0]->intervalleJours ?? 0;
    }

    /**
     * La saison qui contient $date, et sa période décalée ; null hors de toute
     * saison déclarée. Trois années de début : une saison à cheval ou décalée
     * déborde sur l'année voisine.
     *
     * @return array{0: IntervalleSaisonnier, 1: Periode}|null
     */
    public function saisonDe(\DateTimeImmutable $date, Decalage $decalage): ?array
    {
        $jour = Calendrier::jour($date);
        $annee = (int) $jour->format('Y');
        foreach ($this->intervalles as $saison) {
            foreach ([$annee - 1, $annee, $annee + 1] as $anneeDeDebut) {
                $periode = $saison->projeter($anneeDeDebut, $decalage);
                if ($periode->contient($jour)) {
                    return [$saison, $periode];
                }
            }
        }

        return null;
    }

    /** Le premier jour, strictement après $date, d'une saison d'intervalle non nul. */
    public function ouvertureSuivante(\DateTimeImmutable $date, Decalage $decalage): \DateTimeImmutable
    {
        $jour = Calendrier::jour($date);
        $annee = (int) $jour->format('Y');
        $ouvertures = [];
        $actives = array_filter($this->intervalles, fn (IntervalleSaisonnier $s) => $s->intervalleJours > 0);
        foreach ($actives as $saison) {
            foreach ([$annee, $annee + 1, $annee + 2] as $anneeDeDebut) {
                $debut = $saison->projeter($anneeDeDebut, $decalage)->debut;
                if ($debut > $jour) {
                    $ouvertures[] = $debut;
                }
            }
        }

        // Jamais vide : une règle R1 a une saison active (constructeur).
        return min($ouvertures ?: throw new \LogicException('Règle sans saison active'));
    }

    /**
     * Une règle R1 a au moins une saison active (sinon elle ne produirait
     * jamais rien, RG-40), et deux saisons n'ont aucun mois commun (D-90).
     *
     * @param  list<IntervalleSaisonnier>  $saisons
     */
    private static function exigerSaisons(array $saisons): void
    {
        $mois = array_merge([], ...array_map(fn (IntervalleSaisonnier $s) => $s->mois(), $saisons));
        foreach (array_count_values($mois) as $m => $fois) {
            if ($fois > 1) {
                throw new \InvalidArgumentException("Mois {$m} dans deux saisons");
            }
        }
        if (array_filter($saisons, fn (IntervalleSaisonnier $s) => $s->intervalleJours > 0) === []) {
            throw new \InvalidArgumentException('Règle R1 sans saison active');
        }
    }

    /** @param  list<string>  $admises */
    private static function exiger(string $valeur, array $admises, string $champ): void
    {
        if (! in_array($valeur, $admises, true)) {
            throw new \InvalidArgumentException("{$champ} : valeur inconnue « {$valeur} »");
        }
    }
}
