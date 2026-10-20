<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Le référentiel des communes (chapitre 4 §3, RG-52, D-66) : référence partagée,
// importée par `jardin:importer-communes`. La clé `jardins.commune_insee`,
// absente depuis le 1a, arrive avec sa table (D-78).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->char('code_insee', 5)->primary();
            $table->string('nom', 120);
            // Sans accents ni casse, mots séparés d'une espace (D-86).
            $table->string('nom_recherche', 120);
            // Séparés d'une espace ; Paris en a 21 (D-86).
            $table->string('codes_postaux', 255);
            $table->integer('altitude_m');
            $table->string('station', 80);
            $table->decimal('ecart_c', 4, 1);
            $table->smallInteger('decalage_jours');
            $table->smallInteger('profil_propose_id');
            $table->foreign('profil_propose_id')->references('id')->on('profils_climatiques');
        });
        DB::statement('CREATE INDEX communes_recherche ON communes (nom_recherche varchar_pattern_ops)');

        Schema::table('jardins', function (Blueprint $table) {
            $table->foreign('commune_insee')->references('code_insee')->on('communes');
        });
    }

    public function down(): void
    {
        Schema::table('jardins', function (Blueprint $table) {
            $table->dropForeign(['commune_insee']);
        });
        Schema::dropIfExists('communes');
    }
};
