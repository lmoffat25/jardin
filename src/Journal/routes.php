<?php

use Illuminate\Support\Facades\Route;
use Src\Journal\Historique\HistoriqueController;
use Src\Journal\Saisie\InterventionController;

Route::middleware('metier')->group(function () {
    Route::get('/plantes/{plante}/historique', HistoriqueController::class);
    // Ouverte au membre : le terrain saisit (D-49).
    Route::post('/interventions', [InterventionController::class, 'enregistrer']);
});
