<?php

declare(strict_types=1);

namespace Src\Echeances\Moteur;

/**
 * L'état du jour d'une échéance calculée (CDC §4.3). `soldee` et `suspendue`
 * ne se calculent pas : ils n'existent qu'une fois l'échéance matérialisée.
 */
enum Etat: string
{
    case Manquee = 'manquee';
    case AVenir = 'a_venir';
    case Urgente = 'urgente';
    case Optimale = 'optimale';
    case Ouverte = 'ouverte';
}
