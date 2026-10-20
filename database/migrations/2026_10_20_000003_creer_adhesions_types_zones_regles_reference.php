<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migration 3 du chapitre 4 §5 : adhésions, types d'intervention, zones, règles de référence.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adhesions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestampTz('invite_le')->nullable();
            $table->timestampTz('accepte_le')->nullable();
            $table->unique(['utilisateur_id', 'jardin_id']);
        });
        // Énumération en varchar + CHECK, pas en ENUM (S5).
        DB::statement("ALTER TABLE adhesions ADD CONSTRAINT adhesions_role_check CHECK (role IN ('proprietaire','membre'))");

        Schema::create('types_intervention', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->always()->primary();
            // NULL = type fourni, partagé par tous les jardins.
            $table->foreignUuid('jardin_id')->nullable()->constrained('jardins')->cascadeOnDelete();
            $table->string('libelle', 80);
            $table->string('famille', 20);
            $table->boolean('demande_quantite')->default(false);
            $table->boolean('demande_produit')->default(false);
            $table->unique(['jardin_id', 'libelle']);
        });
        // Un libellé unique parmi les types fournis : la contrainte UNIQUE ci-dessus
        // ne compare pas deux jardin_id nuls (D-38).
        DB::statement('CREATE UNIQUE INDEX types_intervention_fournis_libelle ON types_intervention (libelle) WHERE jardin_id IS NULL');

        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->uuid('zone_parente_id')->nullable();
            $table->string('nom', 120);
            $table->timestampTz('archivee_le')->nullable();
            $table->timestampTz('cree_le')->useCurrent();
            $table->unique(['jardin_id', 'nom']);
        });
        Schema::table('zones', function (Blueprint $table) {
            $table->foreign('zone_parente_id')->references('id')->on('zones');
        });

        // Sans `cle` ni sources : elles arrivent au 4a (D-39).
        Schema::create('regles_reference', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->always()->primary();
            $table->integer('espece_id');
            $table->foreign('espece_id')->references('id')->on('especes');
            $table->string('variante', 60)->nullable();
            $table->integer('type_intervention_id');
            $table->foreign('type_intervention_id')->references('id')->on('types_intervention');
            $table->string('type_regle', 4);
            $table->jsonb('parametres');
            // NOT NULL sans valeur par défaut : le curateur tranche (S9, RG-31).
            $table->string('confiance', 12);
            $table->smallInteger('version')->default(1);
            $table->timestampTz('remplacee_le')->nullable();
        });
        DB::statement("ALTER TABLE regles_reference ADD CONSTRAINT regles_reference_type_regle_check CHECK (type_regle IN ('R1','R2'))");
        DB::statement("ALTER TABLE regles_reference ADD CONSTRAINT regles_reference_confiance_check CHECK (confiance IN ('verifiee','indicative'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('regles_reference');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('types_intervention');
        Schema::dropIfExists('adhesions');
    }
};
