<?php

use Illuminate\Support\Facades\Route;
use Src\Comptes\Http\ConnexionController;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionController::class, 'afficher'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'connecter']);
});

Route::post('/deconnexion', [ConnexionController::class, 'deconnecter'])
    ->middleware('auth')
    ->name('deconnexion');
