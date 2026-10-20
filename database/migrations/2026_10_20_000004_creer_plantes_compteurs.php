<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migration 4 du chapitre 4 §5 : plantes, compteurs des codes courts (D-03).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('zone_id')->constrained('zones');
            $table->string('code_court', 12);
            $table->string('nom', 120);
            $table->string('repere', 200)->nullable();
            $table->integer('espece_id')->nullable();
            $table->foreign('espece_id')->references('id')->on('especes');
            $table->string('variete', 120)->nullable();
            $table->string('variante', 60)->nullable();
            $table->date('plantee_le')->nullable();
            $table->smallInteger('age_estime_ans')->nullable();
            $table->string('cycle', 12)->default('perenne');
            $table->date('fin_le')->nullable();
            $table->uuid('plante_origine_id')->nullable();
            // La clé vers `photos` est posée par la migration 7 (cycle plantes ↔ photos).
            $table->uuid('photo_couverture_id')->nullable();
            $table->string('sante', 12)->nullable();
            $table->string('etat', 12)->default('active');
            $table->timestampTz('archivee_le')->nullable();
            $table->string('motif_archivage', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('cree_le')->useCurrent();

            // Le code court est unique par jardin, pas globalement (RG-13, S8).
            $table->unique(['jardin_id', 'code_court']);
            $table->unique(['zone_id', 'nom']);
        });
        Schema::table('plantes', function (Blueprint $table) {
            $table->foreign('plante_origine_id')->references('id')->on('plantes');
        });
        DB::statement("ALTER TABLE plantes ADD CONSTRAINT plantes_cycle_check CHECK (cycle IN ('perenne','annuelle'))");
        DB::statement("ALTER TABLE plantes ADD CONSTRAINT plantes_sante_check CHECK (sante IS NULL OR sante IN ('bonne','surveiller','degradee'))");
        DB::statement("ALTER TABLE plantes ADD CONSTRAINT plantes_etat_check CHECK (etat IN ('active','archivee'))");

        // Un compteur par jardin et par préfixe, jamais COUNT(*) : un code n'est pas réattribué (S8).
        Schema::create('compteurs_codes_courts', function (Blueprint $table) {
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->string('prefixe', 4);
            $table->integer('dernier');
            $table->primary(['jardin_id', 'prefixe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteurs_codes_courts');
        Schema::dropIfExists('plantes');
    }
};
