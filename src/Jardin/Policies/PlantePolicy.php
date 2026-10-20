<?php

namespace Src\Jardin\Policies;

use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;

/**
 * Qui peut modifier ou archiver une plante (CDC §2.1, D-49, D-54) : le
 * propriétaire de son jardin, par son adhésion `proprietaire`. Laravel la
 * trouve seul, depuis l'espace de noms du modèle (`Src\Jardin\Domaine` →
 * `Src\Jardin\Policies`).
 */
final class PlantePolicy
{
    public function modifier(Utilisateur $utilisateur, Plante $plante): bool
    {
        return $this->estProprietaire($utilisateur, $plante);
    }

    public function archiver(Utilisateur $utilisateur, Plante $plante): bool
    {
        return $this->estProprietaire($utilisateur, $plante);
    }

    private function estProprietaire(Utilisateur $utilisateur, Plante $plante): bool
    {
        return $utilisateur->adhesions()
            ->where('jardin_id', $plante->getAttribute('jardin_id'))
            ->where('role', 'proprietaire')
            ->exists();
    }
}
