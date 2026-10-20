<?php

namespace Src\Echeances\TableauDeBord;

use Illuminate\Support\Collection;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Moteur\Etat;
use Src\Support\Horloge;

/**
 * Les échéances du jardin courant rangées pour le tableau de bord (B4) et la
 * liste du terrain (T1) : aujourd'hui, semaine, mois, puis le planning par
 * mois (RG-40). Par zone, et au-delà de 10 échéances d'un même (zone, type),
 * un groupe dont le détail se charge à la demande (CDC §8.7, D-24, Q-UX-8).
 *
 * Le calcul d'un écran écrit ses manquées (chapitre 3 §10) : elles ne sont
 * plus rendues ici, la fiche de la plante les montre (RG-07).
 *
 * @phpstan-type Item array{cle: array{regle_id: string, due_le: string}, plante: array{id: string, nom: string, code_court: string, zone: array{id: string, nom: string}}, type: array{id: int, libelle: string}, etat: string, periode: array{debut: string, fin: string, optimal_debut: ?string, optimal_fin: ?string}, explication: string}
 * @phpstan-type Groupe array{zone: array{id: string, nom: string}, type: array{id: int, libelle: string}, nombre: int}
 * @phpstan-type Bloc array{zone: array{id: string, nom: string}, echeances: list<Item>, groupes: list<Groupe>}
 */
final class RegrouperEcheances
{
    /** Au-delà, les échéances d'un même (zone, type) se groupent (D-24, Q-UX-8). */
    public const SEUIL_GROUPE = 10;

    /** « Cette semaine » et « ce mois » : les 7 et les 30 jours qui viennent (Q-UX-20). */
    private const JOURS_SEMAINE = 7;

    private const JOURS_MOIS = 30;

    private const ETATS_DU_JOUR = [Etat::Ouverte, Etat::Optimale, Etat::Urgente];

    public function __construct(
        private readonly CalculEcheances $calcul,
        private readonly MaterialiserManquees $materialiser,
        private readonly Horloge $horloge,
    ) {}

    /**
     * Le calcul du jardin courant, ses manquées écrites ; rend les autres, par
     * date due puis par plante.
     *
     * @return Collection<int, EcheanceCalculee>
     */
    public function calculer(Jardin $jardin): Collection
    {
        $calculees = $this->calcul->pourJardin($jardin);
        $this->materialiser->executer($calculees);

        return $calculees
            ->reject(fn (EcheanceCalculee $e) => $e->echeance->etat === Etat::Manquee)
            ->sortBy([
                fn (EcheanceCalculee $a, EcheanceCalculee $b) => $a->echeance->dueLe <=> $b->echeance->dueLe,
                fn (EcheanceCalculee $a, EcheanceCalculee $b) => strnatcasecmp($a->plante->nom, $b->plante->nom),
                fn (EcheanceCalculee $a, EcheanceCalculee $b) => strnatcasecmp($a->type->libelle, $b->type->libelle),
            ])
            ->values();
    }

    /**
     * @return array{aujourdhui: list<Bloc>, semaine: list<Bloc>, mois: list<Bloc>, planning: list<array{mois: string, zones: list<Bloc>}>}
     */
    public function tableau(Jardin $jardin): array
    {
        $aujourdhui = $this->horloge->aujourdhui();
        /** @var array<string, list<EcheanceCalculee>> $parPeriode */
        $parPeriode = [];
        foreach ($this->calculer($jardin) as $e) {
            $parPeriode[self::periode($e, $aujourdhui)][] = $e;
        }

        $planning = [];
        $mois = array_diff_key($parPeriode, array_flip(['aujourdhui', 'semaine', 'mois']));
        ksort($mois);
        foreach ($mois as $cle => $echeances) {
            $planning[] = ['mois' => (string) $cle, 'zones' => self::parZone($echeances)];
        }

        return [
            'aujourdhui' => self::parZone($parPeriode['aujourdhui'] ?? []),
            'semaine' => self::parZone($parPeriode['semaine'] ?? []),
            'mois' => self::parZone($parPeriode['mois'] ?? []),
            'planning' => $planning,
        ];
    }

    /**
     * Le détail d'un groupe : les échéances d'une zone et d'un type, dans une
     * période (`aujourdhui`, `semaine`, `mois`, ou un mois `AAAA-MM` du planning).
     *
     * @return list<Item>
     */
    public function detail(Jardin $jardin, string $zoneId, int $typeId, string $periode): array
    {
        $aujourdhui = $this->horloge->aujourdhui();

        return array_values($this->calculer($jardin)
            ->filter(fn (EcheanceCalculee $e) => $e->plante->zone_id === $zoneId
                && $e->type->id === $typeId
                && self::periode($e, $aujourdhui) === $periode)
            ->map(self::item(...))
            ->all());
    }

    /** @return Item */
    public static function item(EcheanceCalculee $e): array
    {
        $echeance = $e->echeance;

        return [
            'cle' => ['regle_id' => $echeance->regleId, 'due_le' => $echeance->dueLe->format('Y-m-d')],
            'plante' => [
                'id' => $e->plante->id,
                'nom' => $e->plante->nom,
                'code_court' => $e->plante->code_court,
                'zone' => self::zone($e),
            ],
            'type' => ['id' => $e->type->id, 'libelle' => $e->type->libelle],
            'etat' => $echeance->etat->value,
            'periode' => [
                'debut' => $echeance->periode->debut->format('Y-m-d'),
                'fin' => $echeance->periode->fin->format('Y-m-d'),
                'optimal_debut' => $echeance->periode->optimalDebut?->format('Y-m-d'),
                'optimal_fin' => $echeance->periode->optimalFin?->format('Y-m-d'),
            ],
            'explication' => $echeance->explication->enPhrase($e->type->libelle, $e->plante->nom),
        ];
    }

    /** Est-ce une période que `detail` connaît ? */
    public static function periodeConnue(string $periode): bool
    {
        return in_array($periode, ['aujourdhui', 'semaine', 'mois'], true)
            || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) === 1;
    }

    /** Due ou ouverte : aujourd'hui ; à venir : la semaine, le mois, ou son mois au planning (RG-40). */
    private static function periode(EcheanceCalculee $e, \DateTimeImmutable $aujourdhui): string
    {
        $echeance = $e->echeance;
        if (in_array($echeance->etat, self::ETATS_DU_JOUR, true)) {
            return 'aujourdhui';
        }

        $jours = (int) $aujourdhui->diff($echeance->dueLe)->format('%r%a');

        return match (true) {
            $jours <= self::JOURS_SEMAINE => 'semaine',
            $jours <= self::JOURS_MOIS => 'mois',
            default => $echeance->dueLe->format('Y-m'),
        };
    }

    /** @return array{id: string, nom: string} */
    private static function zone(EcheanceCalculee $e): array
    {
        // Chargée par le pont avec la plante (D-98) ; la clé étrangère l'impose.
        $zone = $e->plante->zone ?? throw new \LogicException("Plante {$e->plante->id} sans zone chargée.");

        return ['id' => $zone->id, 'nom' => $zone->nom];
    }

    /**
     * Les échéances par zone, zones par nom (D-100) ; au-delà du seuil, celles
     * d'un même type deviennent un groupe. L'ordre des échéances est gardé.
     *
     * @param  list<EcheanceCalculee>  $echeances
     * @return list<Bloc>
     */
    private static function parZone(array $echeances): array
    {
        /** @var array<string, list<EcheanceCalculee>> $parZone */
        $parZone = [];
        foreach ($echeances as $e) {
            $parZone[$e->plante->zone_id][] = $e;
        }

        $blocs = [];
        foreach ($parZone as $deLaZone) {
            $zone = self::zone($deLaZone[0]);
            /** @var array<int, list<EcheanceCalculee>> $parType */
            $parType = [];
            foreach ($deLaZone as $e) {
                $parType[$e->type->id][] = $e;
            }
            $groupes = [];
            foreach ($parType as $duType) {
                if (count($duType) > self::SEUIL_GROUPE) {
                    $type = $duType[0]->type;
                    $groupes[] = ['zone' => $zone, 'type' => ['id' => $type->id, 'libelle' => $type->libelle], 'nombre' => count($duType)];
                }
            }
            $seules = array_filter($deLaZone, fn (EcheanceCalculee $e) => count($parType[$e->type->id]) <= self::SEUIL_GROUPE);
            usort($groupes, fn (array $a, array $b) => strnatcasecmp($a['type']['libelle'], $b['type']['libelle']));

            $blocs[] = ['zone' => $zone, 'echeances' => array_values(array_map(self::item(...), $seules)), 'groupes' => $groupes];
        }
        usort($blocs, fn (array $a, array $b) => strnatcasecmp($a['zone']['nom'], $b['zone']['nom']));

        return $blocs;
    }
}
