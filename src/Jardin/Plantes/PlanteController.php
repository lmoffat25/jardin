<?php

namespace Src\Jardin\Plantes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Src\Bibliotheque\Especes\RechercheEspece;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\TableauDeBord\RegrouperEcheances;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Zones\ZoneDuJardin;
use Src\Support\Jardin\JardinCourant;

/**
 * La fiche d'une plante (B6 au bureau, T5 au terrain) : la plante, les
 * échéances à venir de ses règles et ses manquées (RG-07, critère 6, V78).
 * Son historique se charge à part (`HistoriqueController`). Et la création
 * d'une plante (B7, T3, V83, V84), sa modification (V132) et son archivage
 * (V88), réservés au propriétaire (`PlantePolicy`).
 *
 * @phpstan-import-type Saisie from CreerPlante
 * @phpstan-import-type Modification from ModifierPlante
 */
final class PlanteController
{
    public function __construct(
        private readonly RegrouperEcheances $regrouper,
        private readonly JardinCourant $jardinCourant,
    ) {}

    /** Un jour et un mois, « JJ/MM », qui existent une année bissextile. */
    private const JOUR_MOIS = 'date_format:d/m';

    /** Les messages communs à la création et à la modification. */
    private const MESSAGES = [
        'nom.required' => 'Donnez un nom à la plante.',
        'nom.unique' => 'Une plante de cette zone porte déjà ce nom.',
        'zone_id.required' => 'Choisissez une zone.',
    ];

    public function creer(Request $request, CreerPlante $creer, RechercheEspece $recherche): JsonResponse|RedirectResponse
    {
        $plante = $creer->executer($this->valider($request, $recherche));

        return $request->wantsJson()
            ? response()->json(['id' => $plante->id, 'code_court' => $plante->code_court], 201)
            : redirect("/plantes/{$plante->id}");
    }

    public function modifier(Request $request, string $plante, ModifierPlante $modifier): JsonResponse|RedirectResponse
    {
        $plante = $this->trouver($plante);
        Gate::authorize('modifier', $plante);

        $zone = $request->input('zone_id');
        /** @var Modification $saisie */
        $saisie = $request->validate([
            'zone_id' => ['bail', 'required', new ZoneDuJardin],
            // Unique dans la zone d'arrivée, la plante elle-même exclue (RG-13).
            'nom' => ['required', 'string', 'max:120', ...(is_string($zone) && Str::isUuid($zone) ? [Rule::unique('plantes', 'nom')->where('zone_id', $zone)->ignore($plante->id)] : [])],
            'repere' => ['nullable', 'string', 'max:200'],
            'variete' => ['nullable', 'string', 'max:120'],
            'plantee_le' => ['nullable', 'date_format:Y-m-d'],
            'age_estime_ans' => ['nullable', 'integer', 'between:0,999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], self::MESSAGES);

        $plante = $modifier->executer($plante, $saisie);

        return $request->wantsJson()
            ? response()->json(['id' => $plante->id, 'code_court' => $plante->code_court])
            : redirect("/plantes/{$plante->id}");
    }

    public function archiver(Request $request, string $plante, ArchiverPlante $archiver): JsonResponse|RedirectResponse
    {
        $plante = $this->trouver($plante);
        Gate::authorize('archiver', $plante);

        /** @var array{motif: string} $saisie */
        $saisie = $request->validate(['motif' => ['required', 'string', 'max:200']], [
            'motif.required' => 'Dites pourquoi la plante est archivée.',
            'motif.max' => 'Le motif tient en 200 caractères.',
        ]);

        $plante = $archiver->executer($plante, $saisie['motif']);

        return $request->wantsJson()
            ? response()->json(['id' => $plante->id, 'code_court' => $plante->code_court])
            : redirect("/plantes/{$plante->id}");
    }

    public function afficher(Request $request, string $plante): Response
    {
        $plante = $this->trouver($plante);
        $zone = $plante->zone ?? throw new LogicException("Plante {$plante->id} sans zone.");
        $jardin = Jardin::query()->findOrFail($this->jardinCourant->id());

        $echeances = $this->regrouper->calculer($jardin)
            ->filter(fn (EcheanceCalculee $e) => $e->plante->id === $plante->id)
            ->map(RegrouperEcheances::item(...))
            ->values()
            ->all();

        $manquees = EcheanceMaterialisee::query()
            ->with('regle.typeIntervention')
            ->where('plante_id', $plante->id)
            ->where('etat', 'manquee')
            ->orderByDesc('due_le')
            ->get()
            ->map(function (EcheanceMaterialisee $m): array {
                $type = $m->regle->typeIntervention ?? throw new LogicException("Échéance {$m->id} sans règle.");

                return [
                    'cle' => ['regle_id' => $m->regle_id, 'due_le' => $m->due_le->format('Y-m-d')],
                    'type' => ['id' => $type->id, 'libelle' => $type->libelle],
                    'periode' => [
                        'debut' => self::jour($m->getAttribute('fenetre_debut')),
                        'fin' => self::jour($m->getAttribute('fenetre_fin')),
                        'optimal_debut' => self::jour($m->getAttribute('optimal_debut')),
                        'optimal_fin' => self::jour($m->getAttribute('optimal_fin')),
                    ],
                    'explication' => $m->explication,
                ];
            })
            ->values()
            ->all();

        return Inertia::render($request->query('contexte') === 'terrain' ? 'Terrain/FichePlante' : 'Bureau/FichePlante', [
            'plante' => [
                'id' => $plante->id,
                'nom' => $plante->nom,
                'code_court' => $plante->code_court,
                'zone' => ['id' => $zone->id, 'nom' => $zone->nom],
            ],
            'echeances' => $echeances,
            'manquees' => $manquees,
        ]);
    }

    /**
     * La saisie d'une plante. Le nom est unique dans sa zone, plantes
     * archivées comprises (contrainte `(zone_id, nom)`) ; la variante est
     * exigée pour une espèce à variantes, refusée sinon (RG-51). Les clés des
     * règles écartées et ajustées sont vérifiées à la copie.
     *
     * @return Saisie
     */
    private function valider(Request $request, RechercheEspece $recherche): array
    {
        $zone = $request->input('zone_id');
        $sansEspece = fn (): bool => $request->input('espece_id') === null;
        /** @var Saisie $saisie */
        $saisie = $request->validate([
            'zone_id' => ['bail', 'required', new ZoneDuJardin],
            'nom' => ['required', 'string', 'max:120', ...(is_string($zone) && Str::isUuid($zone) ? [Rule::unique('plantes', 'nom')->where('zone_id', $zone)] : [])],
            'espece_id' => ['nullable', 'integer', 'exists:especes,id'],
            'repere' => ['nullable', 'string', 'max:200'],
            'variete' => ['nullable', 'string', 'max:120'],
            'plantee_le' => ['nullable', 'date_format:Y-m-d'],
            'regles_ecartees' => [Rule::prohibitedIf($sansEspece), 'array'],
            'regles_ecartees.*' => ['string', 'distinct'],
            'ajustements' => [Rule::prohibitedIf($sansEspece), 'array'],
            'ajustements.*' => ['array'],
            'ajustements.*.periodicite_annees' => ['integer', 'between:1,50'],
            'ajustements.*.intervalle_jours' => ['integer', 'between:1,365'],
            'ajustements.*.fenetres' => ['array'],
            'ajustements.*.fenetres.*' => ['array'],
            'ajustements.*.fenetres.*.debut' => ['string', self::JOUR_MOIS],
            'ajustements.*.fenetres.*.fin' => ['string', self::JOUR_MOIS],
            'ajustements.*.intervalles' => ['array', 'list', 'min:1'],
            'ajustements.*.intervalles.*.mois_debut' => ['required', 'integer', 'between:1,12'],
            'ajustements.*.intervalles.*.mois_fin' => ['required', 'integer', 'between:1,12'],
            'ajustements.*.intervalles.*.jours' => ['required', 'integer', 'between:0,365'],
        ], [...self::MESSAGES, 'espece_id.exists' => 'Espèce inconnue.']);

        $variantes = isset($saisie['espece_id']) ? ($recherche->variantes([(int) $saisie['espece_id']])[(int) $saisie['espece_id']] ?? []) : [];
        /** @var array{variante?: string|null} $variante */
        $variante = $request->validate(
            ['variante' => $variantes === [] ? ['prohibited'] : ['required', 'string', Rule::in($variantes)]],
            [
                'variante.prohibited' => 'Cette espèce n\'a pas de variante de conduite.',
                'variante.required' => 'Choisissez la variante de conduite.',
                'variante.in' => 'Variante de conduite inconnue pour cette espèce.',
            ],
        );

        return [...$saisie, ...$variante, 'espece_id' => isset($saisie['espece_id']) ? (int) $saisie['espece_id'] : null];
    }

    /** Par le scope du jardin courant : une plante de B est introuvable (D1). */
    private function trouver(string $id): Plante
    {
        return Str::isUuid($id) ? Plante::query()->with('zone')->findOrFail($id) : abort(404);
    }

    private static function jour(mixed $date): ?string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : null;
    }
}
