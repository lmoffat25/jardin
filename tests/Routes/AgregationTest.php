<?php

// V77 (CDC §8.7, critère 11, écran B4) : une zone qui compte 14 tailles dues
// rend un groupe (zone, type, nombre = 14) dont le détail ne part pas avec la
// page ; il se charge à la demande. Le seuil est 10 (D-24, Q-UX-8) : jusqu'à
// 10, chaque plante garde sa ligne.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-11');

/**
 * Un jardin du profil de référence : $n rosiers à tailler du 01/02 au 31/03
 * dans la roseraie, et un pommier au verger. Rend son membre et la roseraie.
 *
 * @return array{Utilisateur, Zone}
 */
function roseraie(int $n): array
{
    $utilisateur = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $utilisateur->id]);
    Adhesion::create(['utilisateur_id' => $utilisateur->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
    app(JardinCourant::class)->positionner($jardin->id);

    $zone = Zone::factory()->create(['nom' => 'Roseraie']);
    foreach (range(1, $n) as $i) {
        $rosier = Plante::factory()->create(['zone_id' => $zone->id, 'nom' => sprintf('Rosier %02d', $i)]);
        Fenetre::factory()->for(Regle::factory()->for($rosier))->create();
    }
    $pommier = Plante::factory()->create(['zone_id' => Zone::factory()->create(['nom' => 'Verger'])->id, 'nom' => 'Pommier seul']);
    Fenetre::factory()->for(Regle::factory()->for($pommier))->create();

    app()->forgetScopedInstances();

    return [$utilisateur, $zone];
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2027-02-10 08:00:00', new DateTimeZone('Europe/Paris')));
});

it('groupe 14 tailles d\'une zone sans en envoyer le détail', function () {
    [$membre, $roseraie] = roseraie(14);

    $reponse = $this->withoutVite()->actingAs($membre)->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Bureau/TableauDeBord')
            ->count('aujourdhui', 2)
            ->where('aujourdhui.0.zone.nom', 'Roseraie')
            ->count('aujourdhui.0.echeances', 0)
            ->where('aujourdhui.0.groupes', [[
                'zone' => ['id' => $roseraie->id, 'nom' => 'Roseraie'],
                'type' => ['id' => Regle::query()->withoutGlobalScopes()->value('type_intervention_id'), 'libelle' => 'Taille d\'entretien/fructification'],
                'nombre' => 14,
            ]])
            ->where('aujourdhui.1.zone.nom', 'Verger')
            ->count('aujourdhui.1.echeances', 1)
            ->count('aujourdhui.1.groupes', 0));

    expect((string) $reponse->getContent())->not->toContain('Rosier');

    $type = Regle::query()->withoutGlobalScopes()->value('type_intervention_id');
    app()->forgetScopedInstances();
    $detail = $this->actingAs($membre)
        ->getJson('/echeances/groupe?'.http_build_query(['zone' => $roseraie->id, 'type' => $type, 'periode' => 'aujourdhui']))
        ->assertOk()
        ->json();

    expect($detail)->toHaveCount(14)
        ->and(array_column(array_column($detail, 'plante'), 'nom'))->toBe(array_map(fn ($i) => sprintf('Rosier %02d', $i), range(1, 14)))
        ->and(array_keys($detail[0]))->toBe(['cle', 'plante', 'type', 'etat', 'periode', 'explication']);
});

it('garde une ligne par plante jusqu\'à 10', function () {
    [$membre] = roseraie(10);

    $this->withoutVite()->actingAs($membre)->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->count('aujourdhui.0.echeances', 10)
            ->count('aujourdhui.0.groupes', 0));
});

it('groupe aussi au planning, par mois', function () {
    [$membre, $roseraie] = roseraie(11);
    $this->travelTo(new DateTimeImmutable('2026-10-01 08:00:00', new DateTimeZone('Europe/Paris')));

    $this->withoutVite()->actingAs($membre)->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('planning.0.mois', '2027-02')
            ->where('planning.0.zones.0.groupes.0.nombre', 11)
            ->count('planning.0.zones.0.echeances', 0));

    $type = Regle::query()->withoutGlobalScopes()->value('type_intervention_id');
    app()->forgetScopedInstances();
    $this->actingAs($membre)
        ->getJson('/echeances/groupe?'.http_build_query(['zone' => $roseraie->id, 'type' => $type, 'periode' => '2027-02']))
        ->assertOk()
        ->assertJsonCount(11);
});
