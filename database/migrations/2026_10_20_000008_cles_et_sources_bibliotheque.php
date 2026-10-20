<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Clés et sources de la bibliothèque (chapitre 4 §3.3, S9, D-39) : les clés
// stables des fichiers, les sources de chaque règle, et le contrôle au commit
// qu'une règle `verifiee` en cite au moins deux (B2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('especes', function (Blueprint $table) {
            $table->string('cle', 80)->unique();
        });

        Schema::table('regles_reference', function (Blueprint $table) {
            $table->string('cle', 120);
            $table->unique(['cle', 'version']);
        });
        // Une seule version en vigueur par clé ; les versions remplacées restent (RG-24).
        DB::statement('CREATE UNIQUE INDEX regles_reference_en_vigueur ON regles_reference (cle) WHERE remplacee_le IS NULL');

        Schema::create('sources_regle', function (Blueprint $table) {
            $table->integer('id')->generatedAs()->always()->primary();
            $table->integer('regle_reference_id');
            $table->foreign('regle_reference_id')->references('id')->on('regles_reference')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('libelle', 200)->nullable();
            $table->date('consultee_le');
            $table->unique(['regle_reference_id', 'url']);
        });

        // Un CHECK ne voit pas une autre table : le contrôle est un déclencheur
        // de contrainte, différé au commit pour laisser écrire la règle avant
        // ses sources (D-39). SQLSTATE 23514, une violation d'intégrité.
        // OR REPLACE : `migrate:fresh` supprime les tables, pas les fonctions.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION regles_reference_deux_sources() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                regle integer;
            BEGIN
                IF TG_TABLE_NAME = 'regles_reference' THEN
                    regle := NEW.id;
                ELSE
                    regle := OLD.regle_reference_id;
                END IF;
                IF EXISTS (SELECT 1 FROM regles_reference WHERE id = regle AND confiance = 'verifiee')
                   AND (SELECT count(*) FROM sources_regle WHERE regle_reference_id = regle) < 2 THEN
                    RAISE EXCEPTION 'règle de référence % vérifiée avec moins de deux sources (B2, S9)', regle
                        USING ERRCODE = '23514';
                END IF;
                RETURN NULL;
            END;
            $$
            SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER regles_reference_deux_sources
                AFTER INSERT OR UPDATE OF confiance ON regles_reference
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION regles_reference_deux_sources()
            SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER sources_regle_deux_sources
                AFTER DELETE OR UPDATE OF regle_reference_id ON sources_regle
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION regles_reference_deux_sources()
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sources_regle');
        DB::statement('DROP TRIGGER IF EXISTS regles_reference_deux_sources ON regles_reference');
        DB::statement('DROP FUNCTION IF EXISTS regles_reference_deux_sources()');
        DB::statement('DROP INDEX IF EXISTS regles_reference_en_vigueur');
        Schema::table('regles_reference', function (Blueprint $table) {
            $table->dropUnique(['cle', 'version']);
            $table->dropColumn('cle');
        });
        Schema::table('especes', function (Blueprint $table) {
            $table->dropColumn('cle');
        });
    }
};
