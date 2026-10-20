<?php

namespace Src\Journal\Saisie;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;

/**
 * Enregistre une saisie d'intervention, une ligne par plante (V93, V96).
 * Idempotente : l'identifiant vient du client, et un renvoi ne crée rien ni
 * ne modifie rien ; il rend l'état stocké (RG-50, H5, S11, D-15). Jamais de
 * refus métier (H9) : plante archivée, échéance soldée, date passée ou
 * future sont acceptées. Seul ce que le jardin courant ne connaît pas est
 * refusé, et un identifiant pris dans un autre jardin ne s'en distingue pas
 * (D-08).
 */
final class EnregistrerIntervention
{
    public const TYPE_INCONNU = 'Type d\'intervention inconnu.';

    public function __construct(
        private readonly JardinCourant $jardinCourant,
        private readonly Horloge $horloge,
    ) {}

    /**
     * @return EloquentCollection<int, Intervention> les interventions stockées, dans l'ordre des lignes
     *
     * @throws ValidationException
     */
    public function executer(SaisieIntervention $saisie, Utilisateur $auteur): EloquentCollection
    {
        return DB::transaction(function () use ($saisie, $auteur): EloquentCollection {
            $jardinId = $this->jardinCourant->id();

            // 1. Plantes et type lus sous filtre : absents du jardin courant, introuvables.
            $presentes = Plante::query()->whereKey($saisie->plantesIds())->pluck('id');
            $sansPlante = $saisie->rangsSansPlante($presentes);
            if ($sansPlante !== []) {
                throw Introuvable::lignes($sansPlante);
            }
            // Le filtre `TypesVisibles` (D-55) : un type de B est inconnu.
            if (! TypeIntervention::query()->whereKey($saisie->typeId)->exists()) {
                throw ValidationException::withMessages(['type_intervention_id' => self::TYPE_INCONNU]);
            }

            // 2. Insérer sans écraser : un renvoi ne fait rien (ON CONFLICT (id) DO NOTHING).
            Intervention::query()->insertOrIgnore($saisie->lignesAInserer($jardinId, $auteur->id, $this->horloge->maintenant()));

            // 3. Relire sous filtre. Un identifiant qui manque existe dans un
            //    autre jardin : la réponse d'une plante introuvable, et la
            //    transaction est annulée.
            $stockees = Intervention::query()->whereKey($saisie->ids())->get();
            $absentes = $saisie->lignesAbsentesDe($stockees);
            if ($absentes !== []) {
                throw Introuvable::lignes($absentes);
            }

            // 4. L'état stocké, pas la demande, dans l'ordre des lignes.
            $rangs = array_flip($saisie->ids());

            return $stockees->sortBy(fn (Intervention $i) => $rangs[$i->id])->values();
        });
    }
}
