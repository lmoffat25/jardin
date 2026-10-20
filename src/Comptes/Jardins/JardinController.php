<?php

namespace Src\Comptes\Jardins;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Comptes\Domaine\Utilisateur;
use Src\Support\Jardin\JardinCourant;

/**
 * Créer un jardin, le modifier, basculer de l'un à l'autre (écran B2, RG-20,
 * RG-22, RG-23, RG-43).
 */
final class JardinController
{
    /**
     * Un code officiel de commune (COG de l'Insee) : cinq chiffres, ou 2A/2B
     * pour la Corse, présent dans le référentiel `communes` (D-83).
     */
    private const COMMUNE_INSEE = 'regex:/^(\d{5}|2[AB]\d{3})$/';

    public function nouveau(): Response
    {
        $profils = ProfilClimatique::query()
            ->orderBy('ordre_affichage')
            ->get()
            ->map(fn (ProfilClimatique $p) => ['id' => $p->id, 'libelle' => $p->libelle, 'decalage_jours' => $p->decalage_jours]);

        return Inertia::render('Bureau/Jardin', ['profils' => $profils]);
    }

    public function creer(Request $request, CreerJardin $creer): RedirectResponse
    {
        $saisie = $this->valider($request);

        /** @var Utilisateur $utilisateur */
        $utilisateur = $request->user();
        $creer->executer($utilisateur, $saisie['nom'], $saisie['profil_climatique_id'], $saisie['commune_insee'] ?? null);

        return redirect('/');
    }

    public function modifier(Request $request, JardinCourant $courant, ChoisirProfilClimatique $choisir): RedirectResponse
    {
        $jardin = Jardin::query()->findOrFail($courant->id());
        Gate::authorize('modifier', $jardin);

        $saisie = $this->valider($request);
        $jardin->nom = $saisie['nom'];
        if (array_key_exists('commune_insee', $saisie)) {
            $jardin->commune_insee = $saisie['commune_insee'];
        }
        $jardin->save();
        $choisir->executer($saisie['profil_climatique_id']);

        return back();
    }

    public function basculer(Request $request, string $jardin, BasculerDeJardin $basculer): RedirectResponse
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $request->user();
        $basculer->executer($utilisateur, $jardin);

        return redirect('/');
    }

    /** @return array{nom: string, profil_climatique_id: int, commune_insee?: string|null} */
    private function valider(Request $request): array
    {
        /** @var array{nom: string, profil_climatique_id: int|string, commune_insee?: string|null} $saisie */
        $saisie = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'profil_climatique_id' => ['required', 'integer', 'exists:profils_climatiques,id'],
            'commune_insee' => ['nullable', 'string', self::COMMUNE_INSEE, 'exists:communes,code_insee'],
        ]);

        return [...$saisie, 'profil_climatique_id' => (int) $saisie['profil_climatique_id']];
    }
}
