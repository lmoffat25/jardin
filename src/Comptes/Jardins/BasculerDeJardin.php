<?php

namespace Src\Comptes\Jardins;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Utilisateur;

/**
 * Change le jardin courant de la session (RG-43), vers un jardin où
 * l'utilisateur a une adhésion (RG-20). Sinon `403`, la session reste telle
 * quelle ; un jardin inconnu est refusé comme un jardin étranger, sans dire
 * s'il existe.
 */
final class BasculerDeJardin
{
    /** @throws AuthorizationException */
    public function executer(Utilisateur $u, string $jardinId): void
    {
        $membre = Str::isUuid($jardinId) && $u->adhesions()->where('jardin_id', $jardinId)->exists();
        if (! $membre) {
            throw new AuthorizationException;
        }

        session()->put('jardin_id', $jardinId);
    }
}
