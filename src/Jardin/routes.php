<?php

use Illuminate\Support\Facades\Route;
use Src\Jardin\Plantes\PlanteController;

Route::middleware('metier')->group(function () {
    Route::get('/plantes/{plante}', [PlanteController::class, 'afficher']);
});
