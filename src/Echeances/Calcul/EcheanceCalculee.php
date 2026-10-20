<?php

namespace Src\Echeances\Calcul;

use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Echeances\Domaine\Regle;
use Src\Echeances\Moteur\Echeance;
use Src\Jardin\Domaine\Plante;

/**
 * Une échéance du moteur, rattachée par le pont aux modèles qui la fondent :
 * sa règle, sa plante (zone chargée) et son type (chapitre 3 §6).
 */
final readonly class EcheanceCalculee
{
    public function __construct(
        public Echeance $echeance,
        public Regle $regle,
        public Plante $plante,
        public TypeIntervention $type,
    ) {}
}
