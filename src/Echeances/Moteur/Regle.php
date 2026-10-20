<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/** Une règle active, telle que le pont la traduit pour le moteur (A5). */
final readonly class Regle
{
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
        public array $fenetres,
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
    }

    /** @param  list<string>  $admises */
    private static function exiger(string $valeur, array $admises, string $champ): void
    {
        if (! in_array($valeur, $admises, true)) {
            throw new \InvalidArgumentException("{$champ} : valeur inconnue « {$valeur} »");
        }
    }
}
