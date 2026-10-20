<?php

namespace Src\Journal\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Jardin;
use Src\Jardin\Domaine\FondDePlan;
use Src\Journal\Domaine\Photo;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;

/**
 * Les fichiers orphelins d'un jardin (V121, chapitre 3 §12, D-60) : sous
 * `photos/{jardin}/`, ceux qu'aucune photo (original ou dérivée) ni aucun fond
 * de plan ne référence, modifiés il y a plus de 7 jours. Le jardin est un
 * argument obligatoire, qui positionne `JardinCourant` (A11) : les références
 * se lisent sous le filtre, et les fichiers d'un autre jardin ne sont jamais
 * regardés. Sans `--supprimer`, la commande liste et ne supprime rien.
 */
final class NettoyerOrphelines extends Command
{
    private const JOURS = 7;

    protected $signature = 'photos:nettoyer
        {jardin : L\'identifiant du jardin}
        {--supprimer : Supprime les fichiers listés}';

    protected $description = 'Liste (ou supprime, avec --supprimer) les fichiers de photos d\'un jardin référencés nulle part depuis plus de 7 jours';

    public function handle(JardinCourant $jardinCourant, Horloge $horloge): int
    {
        $jardin = (string) $this->argument('jardin');
        if (! Str::isUuid($jardin) || ! Jardin::query()->whereKey($jardin)->exists()) {
            $this->error('Jardin inconnu : rien n\'est supprimé.');

            return self::FAILURE;
        }
        $jardinCourant->positionner($jardin);

        $references = [];
        foreach (Photo::query()->get(['id', 'empreinte', 'chemin_original']) as $photo) {
            $references[$photo->chemin_original] = true;
            foreach (array_diff(Photo::TAILLES, ['original']) as $taille) {
                $references[$photo->cheminDerivee($taille)] = true;
            }
        }
        foreach (FondDePlan::query()->whereNotNull('fichier')->get(['fichier']) as $fond) {
            $references[(string) $fond->fichier] = true;
        }

        $disque = Storage::disk('local');
        $limite = $horloge->maintenant()->getTimestamp() - self::JOURS * 86400;
        $orphelins = [];
        foreach ($disque->allFiles("photos/{$jardin}") as $fichier) {
            if (is_string($fichier) && ! isset($references[$fichier]) && $disque->lastModified($fichier) < $limite) {
                $orphelins[] = $fichier;
            }
        }
        sort($orphelins);

        $supprimer = (bool) $this->option('supprimer');
        foreach ($orphelins as $fichier) {
            $this->line($fichier);
            if ($supprimer) {
                $disque->delete($fichier);
            }
        }

        $n = count($orphelins);
        $libelle = $n.' '.($n > 1 ? 'fichiers orphelins' : 'fichier orphelin').' de plus de '.self::JOURS.' jours';
        $this->info($supprimer
            ? $libelle.' '.($n > 1 ? 'supprimés' : 'supprimé').'.'
            : $libelle.' ; rien n\'est supprimé sans --supprimer.');

        return self::SUCCESS;
    }
}
