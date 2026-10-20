<?php

namespace Src\Comptes\Quotas;

use Illuminate\Support\Facades\Storage;
use Src\Jardin\Domaine\FondDePlan;
use Src\Journal\Domaine\Photo;

/**
 * L'occupation du disque par le jardin courant, en Ko (CDC §8.4, §8.6) : la
 * somme de ses photos, et de ses fonds de plan image (chapitre 4 §3 bis,
 * D-60), lus sous le filtre du jardin. Un fond n'a pas de colonne de taille :
 * la sienne se lit sur le disque, il y en a peu par jardin.
 */
final class CalculerOccupation
{
    public function pourJardin(): int
    {
        $disque = Storage::disk('local');
        $fonds = 0;
        foreach (FondDePlan::query()->where('nature', 'image')->whereNotNull('fichier')->get(['fichier']) as $fond) {
            if ($fond->fichier !== null && $disque->exists($fond->fichier)) {
                $fonds += (int) ceil($disque->size($fond->fichier) / 1024);
            }
        }

        return (int) Photo::query()->sum('taille_ko') + $fonds;
    }
}
