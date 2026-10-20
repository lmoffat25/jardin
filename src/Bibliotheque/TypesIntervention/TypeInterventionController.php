<?php

namespace Src\Bibliotheque\TypesIntervention;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\Bibliotheque\Domaine\TypeIntervention;

/**
 * Le catalogue des types d'intervention (V91) : la liste, pour le choix du
 * type au terrain (T2) et le catalogue du bureau (B11), et la création d'un
 * type propre au jardin, réservée au propriétaire (D-49). Aucune route ne
 * modifie un type, fourni ou non.
 */
final class TypeInterventionController
{
    public function lister(CatalogueTypes $catalogue): JsonResponse
    {
        return response()->json($catalogue->lister());
    }

    public function creer(Request $request, CatalogueTypes $catalogue): JsonResponse
    {
        /** @var array{libelle: string, famille: string} $saisie */
        $saisie = $request->validate([
            'libelle' => ['required', 'string', 'max:80'],
            'famille' => ['required', 'string', Rule::in(TypeIntervention::FAMILLES)],
        ], [
            'libelle.required' => 'Donnez un libellé au type.',
            'libelle.max' => 'Le libellé tient en 80 caractères.',
            'famille.required' => 'Choisissez une famille.',
            'famille.in' => 'Famille inconnue.',
        ]);

        $type = $catalogue->creer($saisie['libelle'], $saisie['famille']);

        return response()->json(CatalogueTypes::rendu($type), 201);
    }
}
