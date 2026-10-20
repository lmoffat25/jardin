<?php

namespace Src\Comptes\Climat;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Commune;
use Src\Comptes\Domaine\ProfilClimatique;

/**
 * Chercher sa commune et lire le profil qu'elle propose (écran B2, RG-23,
 * RG-52). Référence partagée : rien n'y dépend du jardin courant, et rien
 * n'est écrit ; seul `POST /jardins` enregistre la commune et le profil
 * choisis.
 */
final class CommuneController
{
    private const RESULTATS = 10;

    /** Par nom, sans accents ni casse, ou par code postal (entier ou commencé). */
    public function chercher(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $requete = Commune::query()->select(['code_insee', 'nom', 'codes_postaux']);

        if (preg_match('/^\d{2,5}$/', $q)) {
            $requete->where(/** @param Builder<Commune> $w */ fn (Builder $w) => $w->where('codes_postaux', 'like', "{$q}%")->orWhere('codes_postaux', 'like', "% {$q}%"))
                ->orderBy('nom');
        } else {
            $nom = Str::slug($q, ' ');
            if (mb_strlen($nom) < 2) {
                return response()->json([]);
            }
            $requete->where('nom_recherche', 'like', "{$nom}%")
                ->orderByRaw('length(nom_recherche)')
                ->orderBy('nom');
        }

        return response()->json($requete->limit(self::RESULTATS)->get()->map(fn (Commune $c) => [
            'code_insee' => $c->code_insee,
            'nom' => $c->nom,
            'codes_postaux' => explode(' ', $c->codes_postaux),
        ]));
    }

    /** Le profil proposé et sa raison ; inconnue : `404` et les cinq profils en repli. */
    public function profil(string $codeInsee): JsonResponse
    {
        $commune = Commune::query()->find($codeInsee);
        if ($commune === null) {
            return response()->json([
                'message' => 'Commune inconnue : choisissez le profil dans la liste.',
                'profils' => ProfilClimatique::query()->orderBy('ordre_affichage')->get()
                    ->map(fn (ProfilClimatique $p) => ['id' => $p->id, 'libelle' => $p->libelle, 'decalage_jours' => $p->decalage_jours]),
            ], 404);
        }

        return response()->json([
            'profil_climatique_id' => $commune->profil_propose_id,
            'station' => $commune->station,
            'ecart_c' => $commune->ecart_c,
            'decalage_jours' => $commune->decalage_jours,
            'demander_altitude' => ProposerProfil::duReferentiel()->demanderAltitude($commune->altitude_m),
            // Tant qu'aucun coefficient n'est mesuré sur des plantes de jardin (RG-52, Q24).
            'confiance' => 'indicative',
        ]);
    }
}
