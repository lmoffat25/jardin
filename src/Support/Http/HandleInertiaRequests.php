<?php

namespace Src\Support\Http;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Support\Jardin\JardinCourant;

/**
 * Les props partagées de toutes les pages (chapitre 3 §4, D-34) : l'utilisateur,
 * le jardin courant (RG-43 : visible en permanence) et les jardins de ses
 * adhésions. Ce middleware tourne dans le groupe `web`, avant le middleware
 * `jardin` : les props sont des fermetures, lues au rendu de la page, quand le
 * jardin courant est positionné.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'utilisateur' => fn () => $this->utilisateur($request),
            'jardinCourant' => fn () => $this->jardinCourant(),
            'jardins' => fn () => $this->jardins($request),
        ];
    }

    /** @return array{id: string, courriel: string}|null */
    private function utilisateur(Request $request): ?array
    {
        $utilisateur = $request->user();

        return $utilisateur instanceof Utilisateur
            ? ['id' => $utilisateur->id, 'courriel' => $utilisateur->courriel]
            : null;
    }

    /** @return array{id: string, nom: string, profil: array{id: int, libelle: string, decalage_jours: int}}|null */
    private function jardinCourant(): ?array
    {
        $courant = app(JardinCourant::class);
        if (! $courant->estPositionne()) {
            return null;
        }

        $jardin = Jardin::query()->findOrFail($courant->id());
        // La clé étrangère est NOT NULL : un jardin a toujours son profil.
        $profil = $jardin->profilClimatique()->firstOrFail();

        return [
            'id' => $jardin->id,
            'nom' => $jardin->nom,
            'profil' => [
                'id' => $profil->id,
                'libelle' => $profil->libelle,
                'decalage_jours' => $profil->decalage_jours,
            ],
        ];
    }

    /** @return list<array{id: string, nom: string}> */
    private function jardins(Request $request): array
    {
        $utilisateur = $request->user();
        if (! $utilisateur instanceof Utilisateur) {
            return [];
        }

        return array_values(Jardin::query()
            ->whereIn('id', $utilisateur->adhesions()->select('jardin_id'))
            ->orderBy('cree_le')
            ->orderBy('id')
            ->get(['id', 'nom'])
            ->map(fn (Jardin $jardin) => ['id' => $jardin->id, 'nom' => $jardin->nom])
            ->all());
    }
}
