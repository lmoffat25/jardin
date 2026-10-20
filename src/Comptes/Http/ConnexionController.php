<?php

namespace Src\Comptes\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Src\Comptes\Domaine\Utilisateur;

/**
 * Connexion simple (CDC §6 lot 0, écran B1) : un courriel, un mot de passe.
 * Un refus ne dit jamais lequel des deux est faux ; cinq essais par minute et
 * par couple courriel–adresse, puis le refus tient jusqu'à la fin de la minute,
 * même pour un mot de passe juste.
 */
final class ConnexionController
{
    private const ESSAIS_PAR_MINUTE = 5;

    public function afficher(): Response
    {
        return Inertia::render('Bureau/Connexion');
    }

    public function connecter(Request $request): RedirectResponse
    {
        /** @var array{courriel: string, mot_de_passe: string} $saisie */
        $saisie = $request->validate([
            'courriel' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        $cle = 'connexion|'.Str::lower($saisie['courriel']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, self::ESSAIS_PAR_MINUTE)) {
            throw ValidationException::withMessages([
                'courriel' => 'Trop d\'essais. Réessayez dans une minute.',
            ]);
        }

        if (! Auth::attempt(['courriel' => $saisie['courriel'], 'password' => $saisie['mot_de_passe']])) {
            RateLimiter::hit($cle, 60);

            throw ValidationException::withMessages(['courriel' => 'Identifiants incorrects.']);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        /** @var Utilisateur $utilisateur */
        $utilisateur = Auth::user();
        $utilisateur->forceFill(['derniere_connexion_le' => now()])->save();

        return redirect()->intended('/');
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/connexion');
    }
}
