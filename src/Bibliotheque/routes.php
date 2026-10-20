<?php

use Illuminate\Support\Facades\Route;
use Src\Bibliotheque\Especes\EspeceController;

// La bibliothèque se lit, elle ne s'écrit que par `bibliotheque:charger`
// (RG-24) : aucune route en écriture ici (V65).
Route::middleware('metier')->group(function () {
    Route::get('/especes', [EspeceController::class, 'rechercher']);
    Route::get('/especes/{espece}/regles-proposees', [EspeceController::class, 'reglesProposees']);
});
