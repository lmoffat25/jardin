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
     * @param  list<mixed>  $intervalles  R1, typés au sprint 3d
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
        // usort est stable : deux fenêtres de même ordre gardent leur ordre de déclaration.
        usort($fenetres, fn (Fenetre $a, Fenetre $b) => $a->ordre <=> $b->ordre);
        $this->fenetres = $fenetres;
    }

    /** @param  list<string>  $admises */
    private static function exiger(string $valeur, array $admises, string $champ): void
    {
        if (! in_array($valeur, $admises, true)) {
            throw new \InvalidArgumentException("{$champ} : valeur inconnue « {$valeur} »");
        }
    }
}
