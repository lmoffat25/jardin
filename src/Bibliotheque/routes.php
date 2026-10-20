<?php

use Illuminate\Support\Facades\Route;
use Src\Bibliotheque\Especes\EspeceController;
use Src\Bibliotheque\TypesIntervention\TypeInterventionController;

// La référence de la bibliothèque se lit, elle ne s'écrit que par `bibliotheque:charger`
// (RG-24) : aucune de ces routes n'écrit (V65).
Route::middleware('metier')->group(function () {
    Route::get('/especes', [EspeceController::class, 'rechercher']);
    Route::get('/especes/{espece}/regles-proposees', [EspeceController::class, 'reglesProposees']);
});

// Les types d'intervention, eux, sont aussi propres à un jardin (RG-03) : ces
// routes lisent et écrivent le jardin courant, elles sont au registre du
// cloisonnement. Créer un type est réservé au propriétaire (D-49).
Route::middleware('metier')->group(function () {
    Route::get('/types-intervention', [TypeInterventionController::class, 'lister']);
});
Route::middleware(['metier', 'role:proprietaire'])->group(function () {
    Route::post('/types-intervention', [TypeInterventionController::class, 'creer']);
});
