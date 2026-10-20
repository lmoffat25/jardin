<?php

// V78 (P6, RG-07, critère 6, écrans B6 et T5) : la fiche rend la plante, les
// échéances à venir de ses règles, ses manquées, et son historique chargé à la
// demande, 20 par page ; plante inexistante ou de B → 404, sans marqueur de B.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-06');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));

    // Un jardin « Doux » (-7 jours) : un pommier à tailler du 01/02 au 31/03 et
    // tondu au pied de mars à octobre ; 23 tontes faites en 2026 ; une autre plante.
    $this->membre = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create([
        'proprietaire_id' => $this->membre->id,
        'profil_climatique_id' => ProfilClimatique::query()->where('libelle', 'Doux')->value('id'),
    ]);
    Adhesion::create(['utilisateur_id' => $this->membre->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
    app(JardinCourant::class)->positionner($jardin->id);

    $verger = Zone::factory()->create(['nom' => 'Verger']);
    $this->pommier = Plante::factory()->create(['zone_id' => $verger->id, 'nom' => 'Pommier du fond', 'code_court' => 'FRU-001']);
    $this->taille = Regle::factory()->for($this->pommier)->create();
    Fenetre::factory()->for($this->taille)->create();
    // Une occurrence close sans être manquée : elle n'est pas une manquée de la fiche.
    EcheanceMaterialisee::factory()->for($this->taille)->create(['plante_id' => $this->pommier->id, 'due_le' => '2025-01-25', 'etat' => 'suspendue']);
    IntervalleSaisonnier::factory()->for(Regle::factory()->r1()->for($this->pommier))->create();
    foreach (range(0, 22) as $semaine) {
        Intervention::factory()->for($this->pommier)->create([
            'type_intervention_id' => RegleFactory::typeFourni('Tonte'),
            'auteur_id' => $this->membre->id,
            'faite_le' => (new DateTimeImmutable('2026-03-02'))->modify("+{$semaine} week")->format('Y-m-d'),
        ]);
    }
    $autre = Plante::factory()->create(['zone_id' => $verger->id, 'nom' => 'Poirier voisin']);
    Fenetre::factory()->for(Regle::factory()->for($autre))->create();

    app()->forgetScopedInstances();
});

it('rend la plante, ses échéances à venir et sa taille manquée', function (string $contexte, string $composant) {
    $this->travelTo(new DateTimeImmutable('2027-04-15 08:00:00', new DateTimeZone('Europe/Paris')));

    $this->withoutVite()->actingAs($this->membre)->get("/plantes/{$this->pommier->id}{$contexte}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component($composant)
            ->where('plante', [
                'id' => $this->pommier->id,
                'nom' => 'Pommier du fond',
                'code_court' => 'FRU-001',
                'zone' => ['id' => $this->pommier->zone_id, 'nom' => 'Verger'],
                'couverture' => null,
            ])
            ->count('echeances', 2)
            ->where('echeances.0.type.libelle', 'Tonte')
            ->where('echeances.0.etat', 'ouverte')
            ->where('echeances.1.type.libelle', 'Taille d\'entretien/fructification')
            ->where('echeances.1.cle', ['regle_id' => $this->taille->id, 'due_le' => '2028-01-25'])
            ->where('echeances.1.etat', 'a_venir')
            ->where('manquees', [[
                'cle' => ['regle_id' => $this->taille->id, 'due_le' => '2027-01-25'],
                'type' => ['id' => $this->taille->type_intervention_id, 'libelle' => 'Taille d\'entretien/fructification'],
                'periode' => ['debut' => '2027-01-25', 'fin' => '2027-03-24', 'optimal_debut' => null, 'optimal_fin' => null],
                'explication' => 'Taille d\'entretien/fructification · Pommier du fond : règle de la bibliothèque, fenêtre févr.–mars, avancée de 7 jours pour votre climat.',
            ]])
            ->missing('historique'));
})->with([
    'bureau' => ['', 'Bureau/FichePlante'],
    'terrain' => ['?contexte=terrain', 'Terrain/FichePlante'],
]);

it('charge l\'historique à la demande, 20 par page, le plus récent d\'abord', function () {
    $premiere = $this->actingAs($this->membre)->getJson("/plantes/{$this->pommier->id}/historique")
        ->assertOk()
        ->json();

    expect($premiere['interventions'])->toHaveCount(20)
        ->and($premiere['interventions'][0])->toMatchArray(['faite_le' => '2026-08-03', 'type' => ['id' => RegleFactory::typeFourni('Tonte'), 'libelle' => 'Tonte']])
        ->and($premiere['page'])->toBe(1)
        ->and($premiere['suivante'])->toBeTrue();

    app()->forgetScopedInstances();
    $seconde = $this->actingAs($this->membre)->getJson("/plantes/{$this->pommier->id}/historique?page=2")
        ->assertOk()
        ->json();

    expect(array_column($seconde['interventions'], 'faite_le'))->toBe(['2026-03-16', '2026-03-09', '2026-03-02'])
        ->and($seconde['suivante'])->toBeFalse();
});

it('rend 404 pour une plante inexistante', function (string $id) {
    $this->withoutVite()->actingAs($this->membre)->get("/plantes/{$id}")->assertNotFound();
    app()->forgetScopedInstances();
    $this->actingAs($this->membre)->getJson("/plantes/{$id}/historique")->assertNotFound();
})->with(['uuid inconnu' => ['3f1c2a9e-0000-4000-8000-000000000000'], 'pas un uuid' => ['FRU-001']]);

it('rend 404 pour une plante de B, sans marqueur de B', function () {
    $jardins = new DeuxJardins;
    $planteB = Plante::query()->withoutGlobalScopes()->where('nom', 'like', DeuxJardins::MARQUEUR.'%')->firstOrFail();

    foreach (["/plantes/{$planteB->id}", "/plantes/{$planteB->id}?contexte=terrain", "/plantes/{$planteB->id}/historique"] as $chemin) {
        app()->forgetScopedInstances();
        $corps = (string) $this->withoutVite()->actingAs($jardins->desDeux)
            ->withSession(['jardin_id' => $jardins->a->id])
            ->get($chemin)->assertNotFound()->getContent();
        expect($corps)->not->toContain(DeuxJardins::MARQUEUR);
    }
});
