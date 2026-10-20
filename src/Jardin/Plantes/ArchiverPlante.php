<?php

namespace Src\Jardin\Plantes;

use Illuminate\Support\Facades\DB;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Archive une plante du jardin courant (RG-02, V88) : elle n'est jamais
 * supprimée. Son état, la date et le motif s'écrivent ; ses règles sont
 * désactivées sans `remplacee_le`, réservé aux versions remplacées (D-22). Son
 * code court, ses interventions et ses échéances matérialisées restent. Une
 * plante déjà archivée lève un conflit (`409`), sa première archive gardée.
 */
final class ArchiverPlante
{
    public function executer(Plante $plante, string $motif): Plante
    {
        return DB::transaction(function () use ($plante, $motif): Plante {
            // Relue verrouillée : deux archivages simultanés n'en écrivent qu'un.
            $plante = Plante::query()->lockForUpdate()->findOrFail($plante->id);
            if ($plante->getAttribute('etat') === 'archivee') {
                throw new ConflictHttpException('Cette plante est déjà archivée.');
            }

            $plante->update(['etat' => 'archivee', 'archivee_le' => now(), 'motif_archivage' => $motif]);
            Regle::query()->where('plante_id', $plante->id)->update(['est_active' => false]);

            return $plante;
        });
    }
}
