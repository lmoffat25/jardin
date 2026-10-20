<?php

use Illuminate\Support\Facades\Route;
use Src\Echeances\Reglages\RegleController;
use Src\Echeances\Solde\FaitController;
use Src\Echeances\TableauDeBord\DetailGroupeController;
use Src\Echeances\TableauDeBord\TableauDeBordController;

// La page d'arrivée après la connexion (D-47) : le tableau de bord du jardin courant.
Route::middleware('metier')->group(function () {
    Route::get('/', [TableauDeBordController::class, 'bureau']);
    Route::get('/terrain', [TableauDeBordController::class, 'terrain']);
    Route::get('/echeances/groupe', DetailGroupeController::class);
    // Ouverte au membre : « fait » se dit au terrain (D-49, D-57).
    Route::post('/echeances/fait', FaitController::class);
});

// Les règles ne se corrigent et ne se sautent que par le propriétaire (CDC §2.1, D-49, D-58).
Route::middleware(['metier', 'role:proprietaire'])->group(function () {
    Route::put('/regles/{regle}', [RegleController::class, 'corriger']);
    Route::post('/echeances/suspendre', [RegleController::class, 'suspendre']);
});
