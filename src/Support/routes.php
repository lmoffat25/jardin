<?php

use Illuminate\Support\Facades\Route;
use Src\Support\Http\SanteController;

Route::get('/sante', SanteController::class);
