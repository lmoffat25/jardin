<?php

namespace Src\Bibliotheque\Especes;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\Bibliotheque\Chargement\LireBibliotheque;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\Domaine\RegleReference;
use Src\Bibliotheque\Domaine\SourceRegle;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\IntervalleSaisonnier;
use Src\Echeances\Moteur\JourMois;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;

/**
 * Chercher une espèce et lire ses règles proposées (CDC §5.1, écran B7,
 * V65). Référence partagée en lecture seule : seul le profil du jardin
 * courant entre dans la réponse, par le décalage des périodes (RG-25).
 *
 * @phpstan-import-type Parametres from LireBibliotheque
 */
final class EspeceController
{
    public function __construct(
        private readonly RechercheEspece $recherche,
        private readonly Horloge $horloge,
    ) {}

    public function rechercher(Request $request): JsonResponse
    {
        /** @var array{q: string} $saisie */
        $saisie = $request->validate(['q' => ['required', 'string', 'max:120']], ['q.required' => 'Saisissez un nom d\'espèce.']);
        $especes = $this->recherche->chercher(trim($saisie['q']));
        $variantes = $this->recherche->variantes(array_values($especes->map(fn (Espece $e) => $e->id)->all()));

        return response()->json($especes->map(fn (Espece $e) => [
            'id' => $e->id,
            'cle' => $e->cle,
            'nom_commun' => $e->nom_commun,
            'nom_latin' => $e->nom_latin,
            'categorie' => $e->categorie,
            'variantes' => $variantes[$e->id] ?? [],
        ]));
    }

    /**
     * Les règles en vigueur de l'espèce : communes, plus celles de la variante
     * demandée (RG-51). Les périodes sont celles qui commencent l'année
     * courante, décalées pour le profil du jardin courant (D-94).
     */
    public function reglesProposees(Request $request, Espece $espece, JardinCourant $jardinCourant): JsonResponse
    {
        $variantes = $this->recherche->variantes([$espece->id])[$espece->id] ?? [];
        /** @var array{variante?: string|null} $saisie */
        $saisie = $request->validate(
            ['variante' => $variantes === [] ? ['prohibited'] : ['required', 'string', Rule::in($variantes)]],
            [
                'variante.prohibited' => 'Cette espèce n\'a pas de variante de conduite.',
                'variante.required' => 'Choisissez la variante de conduite.',
                'variante.in' => 'Variante de conduite inconnue pour cette espèce.',
            ],
        );
        $variante = $saisie['variante'] ?? null;

        $jardin = Jardin::query()->findOrFail($jardinCourant->id());
        $decalage = Decalage::jours(ProfilClimatique::query()->findOrFail($jardin->profil_climatique_id)->decalage_jours);
        $annee = (int) $this->horloge->aujourdhui()->format('Y');

        $regles = $espece->reglesReference()
            ->with(['typeIntervention', 'sources' => fn ($s) => $s->orderBy('url')])
            ->whereNull('remplacee_le')
            ->where(fn ($w) => $w->whereNull('variante')->orWhere('variante', $variante))
            ->orderBy('cle')
            ->get();

        return response()->json($regles->map(function (RegleReference $r) use ($annee, $decalage) {
            /** @var Parametres $p */
            $p = $r->parametres;

            return [
                'cle' => $r->cle,
                // Clé étrangère non nulle : le type est toujours là.
                'type_intervention' => $r->typeIntervention?->libelle,
                'type_regle' => $r->type_regle,
                'confiance' => $r->confiance,
                'periodes' => array_map(fn (array $f) => self::periode($f, $annee, $decalage), $p['fenetres']),
                'intervalles' => array_map(function (array $i) use ($annee, $decalage) {
                    $saison = (new IntervalleSaisonnier($i['mois_debut'], $i['mois_fin'], $i['jours']))->projeter($annee, $decalage);

                    // Les clés dans l'ordre du fichier : `jsonb` ne le garde pas.
                    return [
                        'mois_debut' => $i['mois_debut'], 'mois_fin' => $i['mois_fin'], 'jours' => $i['jours'],
                        'debut' => self::jour($saison->debut), 'fin' => self::jour($saison->fin),
                    ];
                }, $p['intervalles']),
                'sources' => $r->sources->map(fn (SourceRegle $s) => [
                    'url' => $s->url,
                    'libelle' => $s->libelle,
                    'consultee_le' => $s->consultee_le->format('Y-m-d'),
                ])->all(),
            ];
        }));
    }

    /**
     * Une fenêtre lue projetée par le moteur : décalée, rognée à ses butoirs.
     *
     * @param  array{debut: string, fin: string, optimal: list<string>|null, butoirs: list<string|null>|null, libelle: string|null}  $f
     * @return array{libelle: string|null, debut: string, fin: string, optimal_debut: string|null, optimal_fin: string|null}
     */
    private static function periode(array $f, int $annee, Decalage $decalage): array
    {
        $periode = (new Fenetre(
            self::jourMois($f['debut']),
            self::jourMois($f['fin']),
            self::jourMois($f['optimal'][0] ?? null),
            self::jourMois($f['optimal'][1] ?? null),
            self::jourMois($f['butoirs'][0] ?? null),
            self::jourMois($f['butoirs'][1] ?? null),
            $f['libelle'],
        ))->projeter($annee, $decalage);

        return [
            'libelle' => $f['libelle'],
            'debut' => self::jour($periode->debut),
            'fin' => self::jour($periode->fin),
            'optimal_debut' => $periode->optimalDebut === null ? null : self::jour($periode->optimalDebut),
            'optimal_fin' => $periode->optimalFin === null ? null : self::jour($periode->optimalFin),
        ];
    }

    /** @return ($jourMois is null ? null : JourMois) */
    private static function jourMois(?string $jourMois): ?JourMois
    {
        if ($jourMois === null) {
            return null;
        }
        [$jour, $mois] = explode('/', $jourMois);

        return new JourMois((int) $jour, (int) $mois);
    }

    private static function jour(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d');
    }
}
