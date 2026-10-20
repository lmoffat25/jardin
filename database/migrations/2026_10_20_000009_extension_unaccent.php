<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// La recherche d'espèce ignore les accents (V65, écran B7) : `unaccent` est
// une extension de confiance de PostgreSQL, livrée avec l'image de DDEV et
// celle de la CI (D-94).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
    }
};
