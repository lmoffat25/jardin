<?php

use Illuminate\Support\Facades\Route;
use Src\Journal\Historique\HistoriqueController;

Route::middleware('metier')->group(function () {
    Route::get('/plantes/{plante}/historique', HistoriqueController::class);
});
