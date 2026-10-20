<?php

namespace Src\Bibliotheque\TypesIntervention;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;

/**
 * Le catalogue des types d'intervention du jardin courant (RG-03, écrans B11
 * et T2) : les fournis et les siens, par le filtre `TypesVisibles`, et en tête
 * pour le terrain les 5 types de ses interventions les plus récentes. Un type
 * créé appartient au jardin courant ; aucun type ne se modifie.
 *
 * @phpstan-type TypeRendu array{id: int, libelle: string, famille: string, fourni: bool}
 */
final class CatalogueTypes
{
    /** Le nombre de types récents, en tête du choix au terrain (T2). */
    public const RECENTS = 5;

    public function __construct(private readonly JardinCourant $jardinCourant) {}

    /** @return array{recents: list<TypeRendu>, tous: list<TypeRendu>} */
    public function lister(): array
    {
        $tous = TypeIntervention::query()->orderBy('libelle')->orderBy('id')->get()->keyBy('id');

        // Le dernier jour fait, puis la dernière saisie, départagent deux types.
        $recents = Intervention::query()
            ->groupBy('type_intervention_id')
            ->orderByRaw('max(faite_le) desc, max(saisie_le) desc, type_intervention_id')
            ->limit(self::RECENTS)
            ->pluck('type_intervention_id')
            ->map(fn (mixed $id) => is_numeric($id) ? $tous->get((int) $id) : null)
            ->filter();

        return [
            'recents' => array_values($recents->map(self::rendu(...))->all()),
            'tous' => array_values($tous->map(self::rendu(...))->all()),
        ];
    }

    /**
     * Un type propre au jardin courant. Le libellé est unique parmi les types
     * visibles, sans tenir compte de la casse ; la contrainte `(jardin_id,
     * libelle)` arbitre deux créations simultanées.
     *
     * @throws ValidationException
     */
    public function creer(string $libelle, string $famille): TypeIntervention
    {
        $dejaPresent = fn () => ValidationException::withMessages(['libelle' => 'Ce type existe déjà.']);

        if (TypeIntervention::query()->whereRaw('lower(libelle) = lower(?)', [$libelle])->exists()) {
            throw $dejaPresent();
        }

        try {
            return TypeIntervention::query()->create([
                'jardin_id' => $this->jardinCourant->id(),
                'libelle' => $libelle,
                'famille' => $famille,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $dejaPresent();
        }
    }

    /** @return TypeRendu */
    public static function rendu(TypeIntervention $type): array
    {
        return ['id' => $type->id, 'libelle' => $type->libelle, 'famille' => $type->famille, 'fourni' => $type->jardin_id === null];
    }
}
