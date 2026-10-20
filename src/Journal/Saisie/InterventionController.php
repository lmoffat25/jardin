<?php

namespace Src\Journal\Saisie;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;
use Src\Comptes\Domaine\Utilisateur;
use Src\Journal\Domaine\Intervention;

/**
 * `POST /interventions` (V93–V97, écrans T2, T3, T4, B8), ouvert au membre
 * (D-49) : une intervention sur une ou plusieurs plantes, chacune sous
 * l'identifiant du client. Rend l'état stocké, identique au premier envoi et
 * aux renvois (D-15).
 *
 * @phpstan-import-type Ligne from SaisieIntervention
 * @phpstan-import-type Position from SaisieIntervention
 */
final class InterventionController
{
    /** Les lignes d'une saisie : un lot de terrain, jamais un inventaire. */
    private const LIGNES_MAX = 500;

    public function enregistrer(Request $request, EnregistrerIntervention $enregistrer): JsonResponse
    {
        /** @var array{lot_saisie_id?: string|null, type_intervention_id: int|string, faite_le: string, note?: string|null, duree_min?: int|string|null, position?: Position|null, lignes: list<Ligne>} $valide */
        $valide = $request->validate([
            'lot_saisie_id' => ['nullable', 'uuid', Rule::requiredIf(fn () => is_array($request->input('lignes')) && count($request->input('lignes')) > 1)],
            'type_intervention_id' => ['required', 'integer'],
            'faite_le' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:2000'],
            'duree_min' => ['nullable', 'integer', 'between:1,1440'],
            'position' => ['nullable', 'array:lng,lat,precision_m'],
            'position.lng' => ['required_with:position', 'numeric', 'between:-180,180'],
            'position.lat' => ['required_with:position', 'numeric', 'between:-90,90'],
            'position.precision_m' => ['nullable', 'numeric', 'min:0'],
            'lignes' => ['required', 'array', 'list', 'max:'.self::LIGNES_MAX],
            'lignes.*' => ['array:id,plante_id'],
            'lignes.*.id' => ['required', 'uuid', 'distinct:ignore_case'],
            'lignes.*.plante_id' => ['required', 'uuid', 'distinct:ignore_case'],
        ], [
            'lot_saisie_id.required' => 'Une saisie sur plusieurs plantes porte un identifiant de lot.',
            'type_intervention_id.required' => 'Choisissez un type d\'intervention.',
            'type_intervention_id.integer' => EnregistrerIntervention::TYPE_INCONNU,
            'faite_le.required' => 'Donnez la date de l\'intervention.',
            'faite_le.date_format' => 'Date invalide : AAAA-MM-JJ.',
            'note.max' => 'La note tient en 2 000 caractères.',
            'duree_min.between' => 'La durée va de 1 à 1 440 minutes.',
            'lignes.required' => 'Choisissez au moins une plante.',
            'lignes.max' => 'Une saisie porte sur 500 plantes au plus.',
            'lignes.*.id.distinct' => 'Deux lignes portent le même identifiant.',
            'lignes.*.plante_id.distinct' => 'Deux lignes portent la même plante.',
        ]);

        $auteur = $request->user();
        if (! $auteur instanceof Utilisateur) {
            throw new LogicException('POST /interventions sans utilisateur connecté.');
        }

        $stockees = $enregistrer->executer(SaisieIntervention::depuis($valide), $auteur);

        return response()->json(['interventions' => $stockees->map(self::rendu(...))->all()]);
    }

    /** @return array{id: string, plante_id: string, type_intervention_id: int, faite_le: string, note: string|null, duree_min: int|null, auteur_id: string} */
    private static function rendu(Intervention $intervention): array
    {
        return [
            'id' => $intervention->id,
            'plante_id' => $intervention->plante_id,
            'type_intervention_id' => $intervention->type_intervention_id,
            'faite_le' => $intervention->faite_le->format('Y-m-d'),
            'note' => $intervention->note,
            'duree_min' => $intervention->duree_min,
            'auteur_id' => $intervention->auteur_id,
        ];
    }
}
