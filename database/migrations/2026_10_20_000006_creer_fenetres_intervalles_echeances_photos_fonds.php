<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Migration 6 du chapitre 4 §5 : fenêtres, intervalles saisonniers, échéances,
// photos, et le fond de plan de la V1 (§3 bis).
return new class extends Migration
{
    public function up(): void
    {
        // jardin_id sur les deux tables filles des règles (RG-22, D-02).
        Schema::create('fenetres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('regle_id')->constrained('regles')->cascadeOnDelete();
            $table->smallInteger('debut_jour');
            $table->smallInteger('debut_mois');
            $table->smallInteger('fin_jour');
            $table->smallInteger('fin_mois');
            $table->smallInteger('optimal_debut_jour')->nullable();
            $table->smallInteger('optimal_debut_mois')->nullable();
            $table->smallInteger('optimal_fin_jour')->nullable();
            $table->smallInteger('optimal_fin_mois')->nullable();
            // Bornes que le décalage climatique ne franchit jamais (RG-44).
            $table->smallInteger('butoir_debut_jour')->nullable();
            $table->smallInteger('butoir_debut_mois')->nullable();
            $table->smallInteger('butoir_fin_jour')->nullable();
            $table->smallInteger('butoir_fin_mois')->nullable();
            $table->string('libelle', 80)->nullable();
            $table->smallInteger('ordre')->default(1);
        });
        foreach (['debut', 'fin', 'butoir_debut', 'butoir_fin'] as $borne) {
            DB::statement("ALTER TABLE fenetres ADD CONSTRAINT fenetres_{$borne}_jour_check CHECK ({$borne}_jour BETWEEN 1 AND 31)");
            DB::statement("ALTER TABLE fenetres ADD CONSTRAINT fenetres_{$borne}_mois_check CHECK ({$borne}_mois BETWEEN 1 AND 12)");
        }
        // Un butoir se donne en entier ou pas du tout.
        DB::statement('ALTER TABLE fenetres ADD CONSTRAINT fenetres_butoir_debut_entier_check CHECK ((butoir_debut_jour IS NULL) = (butoir_debut_mois IS NULL))');
        DB::statement('ALTER TABLE fenetres ADD CONSTRAINT fenetres_butoir_fin_entier_check CHECK ((butoir_fin_jour IS NULL) = (butoir_fin_mois IS NULL))');

        Schema::create('intervalles_saisonniers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('regle_id')->constrained('regles')->cascadeOnDelete();
            $table->smallInteger('mois_debut');
            $table->smallInteger('mois_fin');
            $table->smallInteger('intervalle_jours');
        });
        DB::statement('ALTER TABLE intervalles_saisonniers ADD CONSTRAINT intervalles_saisonniers_mois_debut_check CHECK (mois_debut BETWEEN 1 AND 12)');
        DB::statement('ALTER TABLE intervalles_saisonniers ADD CONSTRAINT intervalles_saisonniers_mois_fin_check CHECK (mois_fin BETWEEN 1 AND 12)');
        DB::statement('ALTER TABLE intervalles_saisonniers ADD CONSTRAINT intervalles_saisonniers_intervalle_check CHECK (intervalle_jours >= 0)');

        // Matérialisées seulement : les échéances à venir se calculent (S10, D3).
        Schema::create('echeances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('regle_id')->constrained('regles')->cascadeOnDelete();
            $table->foreignUuid('plante_id')->constrained('plantes')->cascadeOnDelete();
            $table->date('due_le');
            $table->date('fenetre_debut')->nullable();
            $table->date('fenetre_fin')->nullable();
            $table->date('optimal_debut')->nullable();
            $table->date('optimal_fin')->nullable();
            $table->smallInteger('decalage_climatique_applique_j')->default(0);
            $table->string('etat', 12);
            $table->foreignUuid('intervention_id')->nullable()->constrained('interventions');
            // NOT NULL : pas d'échéance sans sa phrase (RG-06, S16).
            $table->string('explication', 400);
            $table->string('motif_suspension', 200)->nullable();
            $table->timestampTz('materialisee_le')->useCurrent();
            // Identité d'une occurrence, matérialisée ou non (S16).
            $table->unique(['regle_id', 'due_le']);
        });
        DB::statement("ALTER TABLE echeances ADD CONSTRAINT echeances_etat_check CHECK (etat IN ('manquee','suspendue','soldee'))");

        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->foreignUuid('plante_id')->nullable()->constrained('plantes')->cascadeOnDelete();
            $table->foreignUuid('intervention_id')->nullable()->constrained('interventions')->cascadeOnDelete();
            $table->string('empreinte', 64);
            $table->string('chemin_original', 300);
            $table->boolean('derivees_pretes')->default(false);
            $table->integer('taille_ko');
            $table->integer('largeur_px')->nullable();
            $table->integer('hauteur_px')->nullable();
            $table->date('prise_le')->nullable();
            $table->timestampTz('televersee_le')->useCurrent();
        });
        DB::statement('ALTER TABLE photos ADD CONSTRAINT photos_rattachement_check CHECK (plante_id IS NOT NULL OR intervention_id IS NOT NULL)');

        Schema::create('fonds_de_plan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jardin_id')->constrained('jardins')->cascadeOnDelete();
            $table->string('nature', 12);
            $table->string('fichier', 255)->nullable();
            // Image calée : deux ou trois couples (pixel, lon/lat).
            $table->jsonb('calage')->nullable();
            $table->boolean('actif')->default(false);
            $table->timestampTz('cree_le')->useCurrent();
        });
        DB::statement("ALTER TABLE fonds_de_plan ADD CONSTRAINT fonds_de_plan_nature_check CHECK (nature IN ('image','orthophoto'))");
        DB::statement("ALTER TABLE fonds_de_plan ADD CONSTRAINT fonds_de_plan_image_check CHECK (nature = 'orthophoto' OR (fichier IS NOT NULL AND calage IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX fonds_de_plan_un_actif ON fonds_de_plan (jardin_id) WHERE actif');

        // Les trois index de lecture du chapitre 4 §4, tous ouverts par jardin_id (S12).
        DB::statement('CREATE INDEX idx_interventions_historique ON interventions (jardin_id, plante_id, type_intervention_id, faite_le DESC)');
        DB::statement("CREATE INDEX idx_plantes_actives ON plantes (jardin_id, zone_id) WHERE etat = 'active'");
        DB::statement('CREATE INDEX idx_echeances_jardin ON echeances (jardin_id, plante_id, due_le DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_plantes_actives');
        DB::statement('DROP INDEX IF EXISTS idx_interventions_historique');
        Schema::dropIfExists('fonds_de_plan');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('echeances');
        Schema::dropIfExists('intervalles_saisonniers');
        Schema::dropIfExists('fenetres');
    }
};
