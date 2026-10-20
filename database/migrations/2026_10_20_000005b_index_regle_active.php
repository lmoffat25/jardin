<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Migration 5 bis du chapitre 4 §5 : une seule règle active par plante et par
// type, les versions retirées restant en table (S15). Index partiel, pas
// contrainte UNIQUE : seule forme qui dit « unique parmi les actives ».
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX idx_regles_active_unique ON regles (plante_id, type_intervention_id) WHERE est_active');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_regles_active_unique');
    }
};
