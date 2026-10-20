<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration 2 du chapitre 4 §5 : jardins, espèces.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jardins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('proprietaire_id')->constrained('utilisateurs');
            $table->string('nom', 120);
            // NOT NULL : sans profil, le moteur produit des dates fausses dès le premier jour (RG-23).
            $table->smallInteger('profil_climatique_id');
            $table->foreign('profil_climatique_id')->references('id')->on('profils_climatiques');
            // La clé vers `communes` arrive avec sa table, au 2f (D-78).
            $table->char('commune_insee', 5)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->integer('altitude_m')->nullable();
            $table->integer('surface_m2')->nullable();
            $table->integer('quota_photos_mo')->default(2048);
            $table->timestampTz('cree_le')->useCurrent();
        });

        // `cle` arrive au 4a, avec les sources de la bibliothèque (D-39).
        Schema::create('especes', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->always()->primary();
            $table->integer('famille_id')->nullable();
            $table->foreign('famille_id')->references('id')->on('familles_botaniques');
            $table->string('nom_commun', 120);
            $table->string('nom_latin', 120)->nullable();
            $table->string('categorie', 20);
            $table->string('cycle', 12)->default('perenne');
            $table->string('exposition', 60)->nullable();
            $table->string('zone_rusticite', 20)->nullable();
            $table->integer('hauteur_adulte_cm')->nullable();
            $table->string('periode_floraison', 60)->nullable();
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('especes');
        Schema::dropIfExists('jardins');
    }
};
