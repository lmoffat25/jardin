<?php

use Illuminate\Support\Facades\Route;
use Src\Journal\Domaine\Photo;
use Src\Journal\Historique\HistoriqueController;
use Src\Journal\Photos\PhotoController;
use Src\Journal\Photos\ServirPhoto;
use Src\Journal\Saisie\InterventionController;

Route::middleware('metier')->group(function () {
    Route::get('/plantes/{plante}/historique', HistoriqueController::class);
    // Ouverte au membre : le terrain saisit (D-49).
    Route::post('/interventions', [InterventionController::class, 'enregistrer']);
    // Ouverte au membre : la photo accompagne la saisie (D-49, H10).
    Route::post('/photos', [PhotoController::class, 'attacher']);
    Route::get('/photos/{photo}/{taille}', ServirPhoto::class)->whereIn('taille', Photo::TAILLES);
});
