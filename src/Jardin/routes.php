<?php

use Illuminate\Support\Facades\Route;
use Src\Jardin\Identification\CodeCourtController;
use Src\Jardin\Identification\EtiquettesController;
use Src\Jardin\Localisation\ProchesController;
use Src\Jardin\Localisation\TourneeController;
use Src\Jardin\Plantes\GeometrieController;
use Src\Jardin\Plantes\PlanteController;
use Src\Jardin\Zones\ZoneController;

Route::middleware('metier')->group(function () {
    Route::get('/zones', [ZoneController::class, 'lister']);
    Route::get('/zones/{zone}/etiquettes', [EtiquettesController::class, 'afficher']);
    // L'adresse d'un QR (RG-13) : un code court, jamais plus de 12 signes.
    Route::get('/p/{code}', [CodeCourtController::class, 'ouvrir'])->where('code', '[A-Za-z0-9-]{1,12}');
    Route::get('/plantes', [PlanteController::class, 'lister']);
    // Avant `/plantes/{plante}`, qui prendrait « proches » pour un identifiant.
    Route::get('/plantes/proches', [ProchesController::class, 'lister']);
    Route::get('/plantes/{plante}', [PlanteController::class, 'afficher']);
    Route::get('/tournee', [TourneeController::class, 'proposer']);
});

// L'inventaire ne s'écrit que par le propriétaire (CDC §2.1, D-49).
Route::middleware(['metier', 'role:proprietaire'])->group(function () {
    Route::post('/zones', [ZoneController::class, 'creer']);
    Route::put('/zones/{zone}', [ZoneController::class, 'renommer']);
    Route::post('/plantes', [PlanteController::class, 'creer']);
    // Avant `/plantes/{plante}`, qui prendrait « lot » pour un identifiant.
    Route::put('/plantes/lot', [PlanteController::class, 'modifierEnMasse']);
    Route::put('/plantes/{plante}', [PlanteController::class, 'modifier']);
    Route::post('/plantes/{plante}/archiver', [PlanteController::class, 'archiver']);
    Route::put('/plantes/{plante}/geometrie', [GeometrieController::class, 'enregistrer']);
});
