<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * D'où vient une échéance (RG-06) : la règle, sa fenêtre projetée (R2) ou la
 * saison où elle tombe (R1), le décalage du climat (RG-26), la confiance
 * (RG-31) et l'origine de la règle (D-51).
 */
final readonly class Explication
{
    public function __construct(
        public string $typeRegle,
        public ?Fenetre $fenetre,
        public Periode $periode,
        public int $decalageJours,
        public string $confiance,
        public string $origine,
        public ?IntervalleSaisonnier $saison = null,
    ) {}

    /**
     * La phrase, en 400 caractères au plus (`echeances.explication`) : le libellé
     * et le nom sont écourtés à leur longueur en base (80 et 120).
     */
    public function enPhrase(string $libelleType, string $nomPlante): string
    {
        if ($this->origine === 'libre') {
            throw new \LogicException('La phrase d\'une règle libre est écrite au sprint 9b (Q-UX-7).');
        }

        $phrase = sprintf(
            '%s · %s : %s, %s',
            mb_strimwidth($libelleType, 0, 80, '…'),
            mb_strimwidth($nomPlante, 0, 120, '…'),
            $this->origine === 'ajustee' ? 'règle ajustée' : 'règle de la bibliothèque',
            $this->fenetre !== null ? self::fenetre($this->fenetre) : self::saison($this->saison ?? throw new \LogicException('Explication sans fenêtre ni saison')),
        );
        $sens = match ($this->decalageJours <=> 0) {
            1 => 'décalée',
            -1 => 'avancée',
            default => null,
        };
        if ($sens !== null) {
            $jours = abs($this->decalageJours);
            // R1 : seule la saison bouge, jamais l'intervalle (RG-25, Q-UX-19).
            $phrase .= sprintf(', %s%s de %d %s pour votre climat', $this->fenetre === null ? 'saison ' : '', $sens, $jours, $jours > 1 ? 'jours' : 'jour');
        }

        return $phrase.'.'.($this->confiance === 'indicative' ? ' Règle indicative.' : '');
    }

    private static function fenetre(Fenetre $fenetre): string
    {
        return sprintf('fenêtre %s–%s', self::mois($fenetre->debut->mois), self::mois($fenetre->fin->mois));
    }

    /** « tous les 7 jours de mars à juin » (Q-UX-19). */
    private static function saison(IntervalleSaisonnier $saison): string
    {
        $rythme = $saison->intervalleJours > 1 ? "tous les {$saison->intervalleJours} jours" : 'chaque jour';

        return $saison->moisDebut === $saison->moisFin
            ? sprintf('%s en %s', $rythme, self::mois($saison->moisDebut))
            : sprintf('%s de %s à %s', $rythme, self::mois($saison->moisDebut), self::mois($saison->moisFin));
    }

    private static function mois(int $mois): string
    {
        return match ($mois) {
            1 => 'janv.',
            2 => 'févr.',
            3 => 'mars',
            4 => 'avr.',
            5 => 'mai',
            6 => 'juin',
            7 => 'juil.',
            8 => 'août',
            9 => 'sept.',
            10 => 'oct.',
            11 => 'nov.',
            default => 'déc.',
        };
    }
}
