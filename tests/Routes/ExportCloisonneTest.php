<?php

// V123 (RG-21, RG-28, critère 12) : l'export de A ne contient aucun
// identifiant, nom ni fichier de B, même quand le même utilisateur possède
// les deux jardins. B remplit chacune des tables de l'export, ses noms
// portent `MARQUEUR-B`, et son fond de plan a son image sur le disque.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DeuxJardins;
use Tests\Support\Export;

uses(RefreshDatabase::class)->group('critere-12');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
    $this->jardins = new DeuxJardins;
    $this->idsA = [$this->jardins->a->id, ...Export::peupler($this->jardins->a->id, 'A', $this->jardins->proprietaireA->id)];
    $this->idsB = [...$this->jardins->identifiantsB, ...Export::peupler($this->jardins->b->id, DeuxJardins::MARQUEUR, $this->jardins->proprietaireB->id)];
    // Le type propre à B : son identifiant est un entier, cherché par son libellé.
    $this->typeB = (int) DB::table('types_intervention')->where('jardin_id', $this->jardins->b->id)->value('id');
});

/**
 * Tout le texte de l'archive, noms de fichiers compris.
 *
 * @param  array<string, string>  $fichiers
 */
function toutLeTexte(array $fichiers): string
{
    return implode("\n", [...array_keys($fichiers), ...array_values($fichiers)]);
}

it('ne met rien de B dans l\'export de A, pour qui possède A et adhère à B', function () {
    $fichiers = Export::fichiers(Export::telecharger($this->jardins->desDeux, $this->jardins->a->id)->assertOk());
    $texte = toutLeTexte($fichiers);

    expect($texte)->not->toContain(DeuxJardins::MARQUEUR)
        ->and($texte)->not->toContain($this->jardins->b->id);
    foreach ($this->idsB as $id) {
        expect($texte)->not->toContain($id);
    }
    $typesA = array_column(Export::csv($fichiers['types_intervention.csv'])['lignes'], 'id');
    expect($typesA)->not->toContain((string) $this->typeB);
    // Le fond de A, et lui seul.
    expect(array_values(array_filter(array_keys($fichiers), fn ($n) => str_starts_with($n, 'plans/'))))->toHaveCount(1);
});

it('rend B depuis B, et rien de A', function () {
    $texte = toutLeTexte(Export::fichiers(Export::telecharger($this->jardins->desDeux, $this->jardins->b->id)->assertOk()));

    expect($texte)->toContain(DeuxJardins::MARQUEUR.' Pommier')
        ->and($texte)->toContain(DeuxJardins::MARQUEUR.' image du plan');
    foreach ($this->idsA as $id) {
        expect($texte)->not->toContain($id);
    }
});

it('ne joint pas le nom d\'une ligne de B, même quand une ligne de A la désigne', function () {
    // Les clés étrangères ne disent pas le jardin : une écriture fautive peut
    // faire désigner à A une zone ou une plante de B. Les noms joints de
    // l'export restent alors vides.
    $a = $this->jardins->a->id;
    $b = $this->jardins->b->id;
    $zoneB = (string) DB::table('zones')->where('jardin_id', $b)->orderBy('nom')->value('id');
    $planteB = (string) DB::table('plantes')->where('jardin_id', $b)->orderBy('nom')->value('id');
    DB::table('zones')->where('jardin_id', $a)->whereNull('zone_parente_id')->update(['zone_parente_id' => $zoneB]);
    DB::table('plantes')->where('jardin_id', $a)->update(['zone_id' => $zoneB]);
    foreach (['geometries', 'regles', 'interventions', 'echeances', 'photos'] as $table) {
        DB::table($table)->where('jardin_id', $a)->update(['plante_id' => $planteB]);
    }
    // Les fenêtres et intervalles de A gardent leurs règles, de A, qui
    // désignent maintenant une plante de B ; les échéances, une règle de B
    // qui n'a pas d'échéance (sinon l'unicité (regle_id, due_le) refuse, selon
    // l'ordre tiré des uuid).
    $regleB = (string) DB::table('regles')->where('jardin_id', $b)
        ->whereNotIn('id', DB::table('echeances')->where('jardin_id', $b)->select('regle_id'))
        ->orderBy('id')->value('id');
    DB::table('echeances')->where('jardin_id', $a)->update(['regle_id' => $regleB]);

    $texte = toutLeTexte(Export::fichiers(Export::telecharger($this->jardins->desDeux, $a)->assertOk()));

    expect($texte)->not->toContain(DeuxJardins::MARQUEUR);
});
