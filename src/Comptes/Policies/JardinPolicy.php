<?php

namespace Src\Comptes\Policies;

use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;

/**
 * Qui peut modifier un jardin (RG-20) : son propriétaire seul, par son
 * adhésion `proprietaire`. Laravel la trouve seul, depuis l'espace de noms du
 * modèle (`Src\Comptes\Domaine` → `Src\Comptes\Policies`).
 */
final class JardinPolicy
{
    public function modifier(Utilisateur $utilisateur, Jardin $jardin): bool
    {
        return $utilisateur->adhesions()
            ->where('jardin_id', $jardin->id)
            ->where('role', 'proprietaire')
            ->exists();
    }
}
