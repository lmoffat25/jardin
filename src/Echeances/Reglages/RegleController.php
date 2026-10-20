<?php

namespace Src\Echeances\Reglages;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Src\Bibliotheque\ReglesTypes\AppliquerReglesTypes;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Domaine\Regle;
use Src\Echeances\Solde\SuspendreEcheance;
use Src\Echeances\TableauDeBord\RegrouperEcheances;

/**
 * Corriger une règle (B9, V102, V104) et sauter une occurrence (B6, V105),
 * réservés au propriétaire (`ReglePolicy`). Une règle de B est introuvable
 * par le filtre du jardin courant : `404` (D1). La réponse JSON porte la
 * prochaine échéance de la règle, recalculée ; un formulaire revient à la
 * fiche de la plante.
 *
 * @phpstan-import-type Correction from ModifierRegle
 */
final class RegleController
{
    /** `PUT /regles/{regle}` : R2 corrige sa périodicité et ses fenêtres, R1 son intervalle et ses saisons. */
    public function corriger(Request $request, string $regle, ModifierRegle $modifier): JsonResponse|RedirectResponse
    {
        $regle = $this->trouver($regle);
        Gate::authorize('corriger', $regle);

        $r1 = $regle->type_regle === 'R1';
        /** @var Correction $correction */
        $correction = $request->validate([
            'periodicite_annees' => [Rule::prohibitedIf($r1), 'integer', 'between:1,50'],
            'intervalle_jours' => [Rule::prohibitedIf(! $r1), 'integer', 'between:1,365'],
            'intervalles' => [Rule::prohibitedIf(! $r1), 'array', 'list', 'min:1'],
            'intervalles.*.mois_debut' => ['required', 'integer', 'between:1,12'],
            'intervalles.*.mois_fin' => ['required', 'integer', 'between:1,12'],
            'intervalles.*.jours' => ['required', 'integer', 'between:0,365'],
            'fenetres' => [Rule::prohibitedIf($r1), 'array', 'list', 'min:1'],
            'fenetres.*' => [new ValiderFenetres],
        ], [
            'periodicite_annees.prohibited' => 'Une règle à intervalle n\'a pas de périodicité en années.',
            'fenetres.prohibited' => 'Une règle à intervalle n\'a pas de fenêtre.',
            'intervalle_jours.prohibited' => 'Une règle à fenêtres n\'a pas d\'intervalle.',
            'intervalles.prohibited' => 'Une règle à fenêtres n\'a pas de saison.',
            'fenetres.min' => 'Une règle à fenêtres en garde au moins une.',
            'fenetres.required' => 'Une règle à fenêtres en garde au moins une.',
        ]);
        if ($correction === []) {
            throw ValidationException::withMessages(['regle' => 'Rien à corriger.']);
        }
        if (isset($correction['intervalles'])) {
            AppliquerReglesTypes::verifierSaisons($correction['intervalles'], 'intervalles');
        }

        $v2 = $modifier->executer($regle, $correction);

        return $request->wantsJson()
            ? response()->json([
                'regle' => ['id' => $v2->id, 'version' => $v2->version],
                'prochaine' => self::rendu($modifier->prochaine($v2)),
            ])
            : redirect("/plantes/{$v2->plante_id}");
    }

    /** `POST /echeances/suspendre` : l'occurrence `(regle_id, due_le)` sautée, avec son motif. */
    public function suspendre(Request $request, SuspendreEcheance $suspendre, ModifierRegle $modifier): JsonResponse|RedirectResponse
    {
        /** @var array{regle_id: string, due_le: string, motif: string} $saisie */
        $saisie = $request->validate([
            'regle_id' => ['required', 'uuid'],
            'due_le' => ['required', 'date_format:Y-m-d'],
            'motif' => ['required', 'string', 'max:200'],
        ], [
            'due_le.date_format' => 'Date invalide : AAAA-MM-JJ.',
            'motif.required' => 'Dites pourquoi cette occurrence est sautée.',
            'motif.max' => 'Le motif tient en 200 caractères.',
        ]);
        $regle = $this->trouver($saisie['regle_id']);
        Gate::authorize('suspendre', $regle);

        $suspendue = $suspendre->executer($regle, new \DateTimeImmutable($saisie['due_le']), $saisie['motif']);

        return $request->wantsJson()
            ? response()->json([
                'echeance' => [
                    'regle_id' => $suspendue->regle_id,
                    'due_le' => $suspendue->due_le->format('Y-m-d'),
                    'etat' => $suspendue->etat,
                    'motif' => $suspendue->getAttribute('motif_suspension'),
                ],
                'prochaine' => self::rendu($modifier->prochaine($regle)),
            ])
            : back();
    }

    /** Par le scope du jardin courant : une règle de B est introuvable (D1). */
    private function trouver(string $id): Regle
    {
        return Str::isUuid($id)
            ? Regle::query()->with(['fenetres', 'intervalles', 'plante', 'typeIntervention'])->findOrFail(strtolower($id))
            : abort(404);
    }

    /** @return array<string, mixed>|null */
    private static function rendu(?EcheanceCalculee $echeance): ?array
    {
        return $echeance === null ? null : RegrouperEcheances::item($echeance);
    }
}
