<?php

namespace Src\Comptes\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Src\Comptes\Domaine\Utilisateur;

/**
 * Crée le compte propriétaire à l'installation (CDC §6 lot 0), une seule fois :
 * s'il existe déjà un compte, rien n'est écrit. Le mot de passe est lu sur
 * l'entrée standard, jamais en argument : il finirait dans l'historique du
 * shell et dans la liste des processus.
 */
final class CreerProprietaire extends Command
{
    protected $signature = 'jardin:creer-proprietaire {courriel : Le courriel du compte}';

    protected $description = 'Crée le compte propriétaire (une seule fois) ; le mot de passe est lu sur l\'entrée standard';

    public function handle(): int
    {
        if (Utilisateur::query()->exists()) {
            $this->error('Un compte existe déjà : rien n\'est créé.');

            return self::FAILURE;
        }

        $courriel = (string) $this->argument('courriel');
        if (Validator::make(['courriel' => $courriel], ['courriel' => ['required', 'email']])->fails()) {
            $this->error('Ce courriel n\'est pas valide.');

            return self::FAILURE;
        }

        $motDePasse = $this->secret('Mot de passe');
        if (! is_string($motDePasse) || $motDePasse === '') {
            $this->error('Le mot de passe est vide : rien n\'est créé.');

            return self::FAILURE;
        }

        Utilisateur::create(['courriel' => $courriel, 'mot_de_passe_hache' => $motDePasse]);
        $this->info("Compte propriétaire créé : {$courriel}.");

        return self::SUCCESS;
    }
}
