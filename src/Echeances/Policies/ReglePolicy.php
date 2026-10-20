<?php

namespace Src\Echeances\Policies;

use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Regle;

/**
 * Qui peut corriger une règle ou en sauter une occurrence (CDC §2.1, D-49,
 * D-58) : le propriétaire de son jardin, par son adhésion `proprietaire`.
 * Laravel la trouve seul, depuis l'espace de noms du modèle
 * (`Src\Echeances\Domaine` → `Src\Echeances\Policies`).
 */
final class ReglePolicy
{
    public function corriger(Utilisateur $utilisateur, Regle $regle): bool
    {
        return $this->estProprietaire($utilisateur, $regle);
    }

    public function suspendre(Utilisateur $utilisateur, Regle $regle): bool
    {
        return $this->estProprietaire($utilisateur, $regle);
    }

    private function estProprietaire(Utilisateur $utilisateur, Regle $regle): bool
    {
        return $utilisateur->adhesions()
            ->where('jardin_id', $regle->getAttribute('jardin_id'))
            ->where('role', 'proprietaire')
            ->exists();
    }
}
