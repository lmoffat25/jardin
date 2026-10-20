<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migration 5 du chapitre 4 §5 : géométries, règles, interventions.
return new class extends Migration
{
    public function up(): void
    {
        // GeoJSON en WGS 84, jamais requêté (S6, S17).
        Schema::create('geometries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('plante_id')->unique()->constrained('plantes')->cascadeOnDelete();
            $table->string('forme', 10);
            $table->jsonb('coordonnees');
            $table->smallInteger('precision_m')->nullable();
            $table->string('source', 10);
            $table->timestampTz('relevee_le');
        });
        DB::statement("ALTER TABLE geometries ADD CONSTRAINT geometries_forme_check CHECK (forme IN ('point','ligne','polygone'))");
        DB::statement("ALTER TABLE geometries ADD CONSTRAINT geometries_source_check CHECK (source IN ('gps','manuel','plan'))");

        // Aucune colonne de zone : l'entretien porte sur une plante (RG-34, D-62).
        Schema::create('regles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('plante_id')->constrained('plantes')->cascadeOnDelete();
            $table->integer('type_intervention_id');
            $table->foreign('type_intervention_id')->references('id')->on('types_intervention');
            $table->integer('regle_reference_id')->nullable();
            $table->foreign('regle_reference_id')->references('id')->on('regles_reference');
            $table->string('type_regle', 4);
            $table->smallInteger('intervalle_jours')->nullable();
            $table->smallInteger('periodicite_annees')->nullable();
            $table->string('confiance', 12);
            $table->string('origine', 12);
            $table->boolean('est_active')->default(true);
            $table->smallInteger('version')->default(1);
            $table->timestampTz('remplacee_le')->nullable();
            $table->timestampTz('creee_le')->useCurrent();
        });
        DB::statement("ALTER TABLE regles ADD CONSTRAINT regles_type_regle_check CHECK (type_regle IN ('R1','R2'))");
        DB::statement("ALTER TABLE regles ADD CONSTRAINT regles_confiance_check CHECK (confiance IN ('verifiee','indicative'))");
        DB::statement("ALTER TABLE regles ADD CONSTRAINT regles_origine_check CHECK (origine IN ('bibliotheque','ajustee','libre'))");
        DB::statement("ALTER TABLE regles ADD CONSTRAINT regles_parametre_check CHECK ((type_regle = 'R1' AND intervalle_jours IS NOT NULL) OR (type_regle = 'R2' AND periodicite_annees IS NOT NULL))");
        // Une version retirée n'est plus active (S15).
        DB::statement('ALTER TABLE regles ADD CONSTRAINT regles_active_non_remplacee_check CHECK (est_active = false OR remplacee_le IS NULL)');

        // L'id est fourni par le client : le rejeu d'une saisie ne crée rien (S11, H5).
        Schema::create('interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('plante_id')->constrained('plantes');
            $table->integer('type_intervention_id');
            $table->foreign('type_intervention_id')->references('id')->on('types_intervention');
            $table->foreignUuid('auteur_id')->constrained('utilisateurs');
            $table->date('faite_le');
            $table->smallInteger('duree_min')->nullable();
            $table->text('note')->nullable();
            $table->uuid('lot_saisie_id')->nullable();
            $table->jsonb('position_capturee')->nullable();
            $table->decimal('quantite', 8, 2)->nullable();
            $table->string('unite', 12)->nullable();
            $table->timestampTz('saisie_le')->useCurrent();
            $table->timestampTz('synchronisee_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
        Schema::dropIfExists('regles');
        Schema::dropIfExists('geometries');
    }
};
