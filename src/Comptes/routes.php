<?php

use Illuminate\Support\Facades\Route;
use Src\Comptes\Climat\CommuneController;
use Src\Comptes\Http\ConnexionController;
use Src\Comptes\Jardins\JardinController;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'afficher'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'connecter']);
});

Route::post('/deconnexion', [ConnexionController::class, 'deconnecter'])
    ->middleware('auth')
    ->name('deconnexion');

// Hors du groupe `metier` : un compte sans jardin doit pouvoir en créer un, et
// `jardin` le renverrait ici en boucle (D-82).
Route::middleware('auth')->group(function () {
    Route::get('/jardins/nouveau', [JardinController::class, 'nouveau'])->name('jardins.nouveau');
    Route::post('/jardins', [JardinController::class, 'creer']);
    Route::post('/jardins/{jardin}/basculer', [JardinController::class, 'basculer']);
    // Référence partagée, lue par l'écran B2 (RG-52) : aucun jardin n'y entre.
    Route::get('/communes', [CommuneController::class, 'chercher']);
    Route::get('/communes/{code_insee}/profil', [CommuneController::class, 'profil']);
});

// Réservée au propriétaire (D-49) ; la policy `modifier` reste.
Route::put('/jardin', [JardinController::class, 'modifier'])->middleware(['metier', 'role:proprietaire']);
