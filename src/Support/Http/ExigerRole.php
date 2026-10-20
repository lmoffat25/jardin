<?php

namespace Src\Support\Http;

use Closure;
use Illuminate\Http\Request;
use Src\Comptes\Domaine\Utilisateur;
use Src\Support\Jardin\JardinCourant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `role:proprietaire` (CDC §2.1, RG-20, D-49) : l'utilisateur a, dans
 * le jardin courant, une adhésion de ce rôle ; sinon `403`. Il suit `metier`,
 * qui a positionné le jardin courant. Un membre lit, enregistre des
 * interventions, des « fait » et des photos, et exporte ; toute autre écriture
 * passe par ce middleware.
 */
final class ExigerRole
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $request->user();

        $permis = $utilisateur->adhesions()
            ->where('jardin_id', $this->jardinCourant->id())
            ->where('role', $role)
            ->exists();

        abort_unless($permis, 403);

        return $next($request);
    }
}
