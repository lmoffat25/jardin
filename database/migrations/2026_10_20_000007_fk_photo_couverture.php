<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration 7 du chapitre 4 §5 : le cycle plantes ↔ photos se ferme en dernier,
// sans supprimer aucune des deux relations.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE plantes ADD CONSTRAINT fk_photo_couverture FOREIGN KEY (photo_couverture_id) REFERENCES photos(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plantes DROP CONSTRAINT IF EXISTS fk_photo_couverture');
    }
};
