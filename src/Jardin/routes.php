<?php

use Illuminate\Support\Facades\Route;
use Src\Jardin\Plantes\PlanteController;
use Src\Jardin\Zones\ZoneController;

Route::middleware('metier')->group(function () {
    Route::get('/zones', [ZoneController::class, 'lister']);
    Route::get('/plantes/{plante}', [PlanteController::class, 'afficher']);
});

// L'inventaire ne s'écrit que par le propriétaire (CDC §2.1, D-49).
Route::middleware(['metier', 'role:proprietaire'])->group(function () {
    Route::post('/zones', [ZoneController::class, 'creer']);
    Route::put('/zones/{zone}', [ZoneController::class, 'renommer']);
    Route::post('/plantes', [PlanteController::class, 'creer']);
});
