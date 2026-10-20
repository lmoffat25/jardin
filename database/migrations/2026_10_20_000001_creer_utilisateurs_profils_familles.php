<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migration 1 du chapitre 4 §5 : utilisateurs, profils climatiques, familles botaniques.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('courriel', 255)->unique();
            $table->string('mot_de_passe_hache', 255);
            $table->string('nom_affiche', 120)->nullable();
            $table->timestampTz('cree_le')->useCurrent();
            $table->timestampTz('derniere_connexion_le')->nullable();
            $table->timestampTz('supprime_le')->nullable();
        });

        // Référence : l'identifiant est fourni par le jeu de référence (S2).
        Schema::create('profils_climatiques', function (Blueprint $table) {
            $table->smallInteger('id')->primary();
            $table->string('libelle', 80);
            $table->smallInteger('decalage_jours');
            $table->smallInteger('ordre_affichage');
        });

        Schema::create('familles_botaniques', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->always()->primary();
            $table->string('nom', 80)->unique();
            $table->smallInteger('delai_retour_ans')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('familles_botaniques');
        Schema::dropIfExists('profils_climatiques');
        Schema::dropIfExists('utilisateurs');
    }
};
