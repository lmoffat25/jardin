<?php

namespace Src\Echeances\Solde;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Regle;
use Src\Journal\Domaine\Intervention;
use Src\Journal\Saisie\EnregistrerIntervention;
use Src\Journal\Saisie\InterventionController;
use Src\Journal\Saisie\SaisieIntervention;
use Src\Support\Horloge;

/**
 * `POST /echeances/fait` (V98, écran T1, D-57), ouvert au membre (D-49) :
 * « fait » sur une échéance désignée par `(regle_id, due_le)` (H11) crée
 * l'intervention du type de la règle sur sa plante, datée d'aujourd'hui, et
 * solde exactement ce couple. Rend ce que rend `/interventions` ; un renvoi
 * reçoit l'état stocké.
 *
 * @phpstan-import-type Position from SaisieIntervention
 */
final class FaitController
{
    public const PAS_A_FAIRE = 'Cette échéance n\'est pas à faire.';

    public const REGLE_INACTIVE = 'Cette règle n\'est plus active.';

    public function __construct(
        private readonly EnregistrerIntervention $enregistrer,
        private readonly SolderEcheance $solder,
        private readonly Horloge $horloge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{id: string, regle_id: string, due_le: string, position?: Position|null} $valide */
        $valide = $request->validate([
            'id' => ['required', 'uuid'],
            'regle_id' => ['required', 'uuid'],
            'due_le' => ['required', 'date_format:Y-m-d'],
            'position' => ['nullable', 'array:lng,lat,precision_m'],
            'position.lng' => ['required_with:position', 'numeric', 'between:-180,180'],
            'position.lat' => ['required_with:position', 'numeric', 'between:-90,90'],
            'position.precision_m' => ['nullable', 'numeric', 'min:0'],
        ], [
            'due_le.date_format' => 'Date invalide : AAAA-MM-JJ.',
        ]);

        $auteur = $request->user();
        if (! $auteur instanceof Utilisateur) {
            throw new LogicException('POST /echeances/fait sans utilisateur connecté.');
        }
        $id = strtolower($valide['id']);
        $dueLe = new \DateTimeImmutable($valide['due_le']);

        $stockees = DB::transaction(function () use ($valide, $id, $dueLe, $auteur) {
            // Par le scope du jardin courant : une règle de B est introuvable (D1).
            $regle = Regle::query()->with(['fenetres', 'intervalles'])->findOrFail(strtolower($valide['regle_id']));
            $saisie = SaisieIntervention::depuis([
                'type_intervention_id' => $regle->type_intervention_id,
                'faite_le' => $this->horloge->aujourdhui()->format('Y-m-d'),
                'position' => $valide['position'] ?? null,
                'lignes' => [['id' => $id, 'plante_id' => $regle->plante_id]],
            ]);

            // Un renvoi : le couple est déjà soldé, l'état stocké suffit (D-15).
            if (Intervention::query()->whereKey($id)->exists()) {
                return $this->enregistrer->executer($saisie, $auteur, solder: false);
            }
            if (! $regle->est_active) {
                throw ValidationException::withMessages(['regle_id' => self::REGLE_INACTIVE]);
            }
            if ($this->solder->echeanceDuCouple($regle, $dueLe, $this->horloge->aujourdhui()) === null) {
                throw ValidationException::withMessages(['due_le' => self::PAS_A_FAIRE]);
            }

            $stockees = $this->enregistrer->executer($saisie, $auteur, solder: false);
            $this->solder->couple($stockees->firstOrFail(), $regle->id, $dueLe);

            return $stockees;
        });

        return response()->json(['interventions' => $stockees->map(InterventionController::rendu(...))->all()]);
    }
}
