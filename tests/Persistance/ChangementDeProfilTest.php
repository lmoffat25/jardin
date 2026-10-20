<?php

// V75 : changer le profil d'un jardin modifie les dates calculées sans modifier
// aucune ligne de `regles` ni de `fenetres` (CDC §9 point 9, RG-26) ; une
// échéance déjà matérialisée garde son `decalage_climatique_applique_j`
// d'origine, et n'est pas écrite une seconde fois sous le nouveau profil (D-99).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class);

/** L'emplacement, la transaction d'écriture et le contenu de chaque ligne : une ligne réécrite, même à l'identique, change. */
function lignesDeLaTable(string $table): array
{
    return array_map(
        fn (object $l) => "{$l->ctid} {$l->xmin} {$l->ligne}",
        DB::select("SELECT ctid::text, xmin::text, md5(t::text) AS ligne FROM {$table} t ORDER BY ctid"),
    );
}

/** @return list<array{string, string, string, string}> état, due le, début et fin de la période */
function datesApresProfil(Jardin $jardin): array
{
    $calculees = app(CalculEcheances::class)->pourJardin($jardin->fresh() ?? $jardin);
    app(MaterialiserManquees::class)->executer($calculees);

    return $calculees->map(fn (EcheanceCalculee $e) => [
        $e->echeance->etat->value,
        $e->echeance->dueLe->format('Y-m-d'),
        $e->echeance->periode->debut->format('Y-m-d'),
        $e->echeance->periode->fin->format('Y-m-d'),
    ])->all();
}

function idProfil(string $libelle): int
{
    return ProfilClimatique::query()->where('libelle', $libelle)->value('id');
}

it('change les dates en passant de « Frais » à « Très doux », sans toucher aux règles ni aux fenêtres', function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $jardin = Jardin::factory()->create(['profil_climatique_id' => idProfil('Frais')]);
    app(JardinCourant::class)->positionner($jardin->id);
    $regle = Regle::factory()->for(Plante::factory()->state(['nom' => 'Pommier']))->create();
    Fenetre::factory()->for($regle)->create(['optimal_debut_jour' => 15, 'optimal_debut_mois' => 2, 'optimal_fin_jour' => 15, 'optimal_fin_mois' => 3]);

    // La taille 2027 n'est pas faite : manquée et écrite en « Frais ».
    $this->travelTo(new DateTimeImmutable('2027-04-15 08:00:00', new DateTimeZone('Europe/Paris')));
    expect(datesApresProfil($jardin))->toBe([
        ['manquee', '2027-02-08', '2027-02-08', '2027-04-07'],
        ['a_venir', '2028-02-08', '2028-02-08', '2028-04-07'],
    ]);
    $regles = lignesDeLaTable('regles');
    $fenetres = lignesDeLaTable('fenetres');

    $jardin->update(['profil_climatique_id' => idProfil('Très doux')]);

    expect(datesApresProfil($jardin))->toBe([
        ['a_venir', '2028-01-17', '2028-01-17', '2028-03-16'],
    ])
        ->and(lignesDeLaTable('regles'))->toBe($regles)
        ->and(lignesDeLaTable('fenetres'))->toBe($fenetres);

    // La manquée garde la date et le décalage de son profil d'origine, sa phrase aussi.
    $manquee = EcheanceMaterialisee::query()->sole();
    expect($manquee->due_le->format('Y-m-d'))->toBe('2027-02-08')
        ->and($manquee->getAttribute('decalage_climatique_applique_j'))->toBe(7)
        ->and($manquee->explication)->toContain('décalée de 7 jours pour votre climat');
});
