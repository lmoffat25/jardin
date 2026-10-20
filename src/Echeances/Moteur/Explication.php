<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * D'où vient une échéance (RG-06) : la règle, sa fenêtre projetée, le décalage
 * du climat (RG-26), la confiance (RG-31) et l'origine de la règle (D-51).
 */
final readonly class Explication
{
    public function __construct(
        public string $typeRegle,
        public Fenetre $fenetre,
        public Periode $periode,
        public int $decalageJours,
        public string $confiance,
        public string $origine,
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
            '%s · %s : %s, fenêtre %s–%s',
            mb_strimwidth($libelleType, 0, 80, '…'),
            mb_strimwidth($nomPlante, 0, 120, '…'),
            $this->origine === 'ajustee' ? 'règle ajustée' : 'règle de la bibliothèque',
            self::mois($this->fenetre->debut),
            self::mois($this->fenetre->fin),
        );
        $sens = match ($this->decalageJours <=> 0) {
            1 => 'décalée',
            -1 => 'avancée',
            default => null,
        };
        if ($sens !== null) {
            $jours = abs($this->decalageJours);
            $phrase .= sprintf(', %s de %d %s pour votre climat', $sens, $jours, $jours > 1 ? 'jours' : 'jour');
        }

        return $phrase.'.'.($this->confiance === 'indicative' ? ' Règle indicative.' : '');
    }

    private static function mois(JourMois $jourMois): string
    {
        return match ($jourMois->mois) {
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
