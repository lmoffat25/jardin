<?php

namespace Src\Support\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Utilisateur;
use Src\Support\Jardin\JardinCourant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `jardin` (A11, RG-43) : positionne le jardin courant de la
 * requête. Le jardin de la session n'est pris que si l'utilisateur y adhère ;
 * sinon, le premier jardin de ses adhésions, par ordre de création. Sans
 * aucun jardin, la requête part vers la création de jardin (B2).
 */
final class PositionnerJardinCourant
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $request->user();

        $jardinId = $this->depuisLaSession($request, $utilisateur) ?? $this->premier($utilisateur);
        if ($jardinId === null) {
            return redirect('/jardins/nouveau');
        }

        $request->session()->put('jardin_id', $jardinId);
        $this->jardinCourant->positionner($jardinId);

        return $next($request);
    }

    private function depuisLaSession(Request $request, Utilisateur $utilisateur): ?string
    {
        $jardinId = $request->session()->get('jardin_id');
        if (! is_string($jardinId) || ! Str::isUuid($jardinId)) {
            return null;
        }

        return $utilisateur->adhesions()->where('jardin_id', $jardinId)->exists() ? $jardinId : null;
    }

    private function premier(Utilisateur $utilisateur): ?string
    {
        $jardinId = $utilisateur->adhesions()
            ->join('jardins', 'jardins.id', '=', 'adhesions.jardin_id')
            ->orderBy('jardins.cree_le')
            ->orderBy('jardins.id')
            ->value('adhesions.jardin_id');

        return is_string($jardinId) ? $jardinId : null;
    }
}
