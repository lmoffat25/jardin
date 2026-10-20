<?php

namespace Src\Echeances\Calcul;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as Modeles;
use Illuminate\Support\Collection;
use LogicException;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Regle;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Echeance;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\Moteur;
use Src\Journal\Domaine\Intervention;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;

/**
 * Le pont (chapitre 3 §6) : charger, traduire, rattacher, et rien d'autre.
 * Aucune logique de calendrier ici : elle est dans le moteur. Le calcul d'un
 * jardin coûte un nombre fixe de requêtes, quel que soit le nombre de plantes
 * (A9, V71) ; aucun recalcul planifié (chapitre 3 §10).
 */
final class CalculEcheances
{
    public function __construct(
        private readonly Horloge $horloge,
        private readonly JardinCourant $jardinCourant,
    ) {}

    /**
     * Les échéances des règles actives des plantes actives du jardin courant :
     * les manquées non matérialisées, puis la prochaine de chaque règle.
     *
     * @return Collection<int, EcheanceCalculee>
     */
    public function pourJardin(Jardin $jardin): Collection
    {
        // Les lectures sont bornées par le jardin courant (D1) : calculer un autre jardin serait lire celui-là.
        if ($jardin->id !== $this->jardinCourant->id()) {
            throw new LogicException("Calcul du jardin {$jardin->id} depuis le jardin courant {$this->jardinCourant->id()}.");
        }

        $profil = $jardin->profilClimatique ?? throw new LogicException("Jardin {$jardin->id} sans profil climatique.");
        $decalage = Decalage::jours($profil->decalage_jours);
        $regles = $this->chargerRegles();
        $historique = Historique::depuis($this->chargerHistorique(), $this->chargerCloses());

        $echeances = Moteur::calculer(
            regles: array_values($regles->map->versDomaine()->all()),
            historique: $historique,
            decalage: $decalage,
            aujourdhui: $this->horloge->aujourdhui(),
        );

        return $this->rattacher($echeances, $regles);
    }

    /**
     * La dernière intervention par couple (plante, type), en une requête
     * `DISTINCT ON` (A9), servie par `idx_interventions_historique` (V31).
     *
     * @return Builder<Intervention>
     */
    public function requeteHistorique(): Builder
    {
        return Intervention::query()
            ->select(['plante_id', 'type_intervention_id', 'faite_le'])
            ->distinct(['plante_id', 'type_intervention_id'])
            ->orderBy('plante_id')
            ->orderBy('type_intervention_id')
            ->orderByDesc('faite_le');
    }

    /**
     * Les règles actives des plantes actives, et ce que le pont rattache :
     * une requête par table (D-44).
     *
     * @return Modeles<int, Regle>
     */
    private function chargerRegles(): Modeles
    {
        return Regle::query()
            ->where('est_active', true)
            ->whereHas('plante', fn (Builder $plante) => $plante->where('etat', 'active'))
            ->with(['fenetres', 'intervalles', 'plante.zone', 'typeIntervention'])
            ->get();
    }

    /** @return list<array{plante_id: string, type_id: int, fait_le: \DateTimeImmutable}> */
    private function chargerHistorique(): array
    {
        return array_values($this->requeteHistorique()->get()
            ->map(fn (Intervention $i) => [
                'plante_id' => $i->plante_id,
                'type_id' => $i->type_intervention_id,
                'fait_le' => $i->faite_le->toDateTimeImmutable(),
            ])
            ->all());
    }

    /**
     * Les occurrences closes (D-05) : toute échéance matérialisée l'est, avec
     * le décalage appliqué à son écriture, qui la retrouve si le profil a
     * changé depuis (D-99).
     *
     * @return list<array{regle_id: string, due_le: \DateTimeImmutable, decalage_j: int}>
     */
    private function chargerCloses(): array
    {
        return array_values(EcheanceMaterialisee::query()
            ->select(['regle_id', 'due_le', 'decalage_climatique_applique_j'])
            ->get()
            ->map(fn (EcheanceMaterialisee $e) => [
                'regle_id' => $e->regle_id,
                'due_le' => $e->due_le->toDateTimeImmutable(),
                'decalage_j' => $e->decalage_climatique_applique_j,
            ])
            ->all());
    }

    /**
     * @param  list<Echeance>  $echeances
     * @param  Modeles<int, Regle>  $regles
     * @return Collection<int, EcheanceCalculee>
     */
    private function rattacher(array $echeances, Modeles $regles): Collection
    {
        $parId = $regles->keyBy('id');

        return collect($echeances)->map(function (Echeance $echeance) use ($parId): EcheanceCalculee {
            $regle = $parId->get($echeance->regleId) ?? throw new LogicException("Échéance d'une règle non chargée : {$echeance->regleId}");

            return new EcheanceCalculee($echeance, $regle, $regle->plante, $regle->typeIntervention);
        });
    }
}
