<?php

// V113 (critère 10, écran B10) : les étiquettes d'une zone rendent toutes ses
// plantes actives, triées par code court, avec code court, nom, repère et le
// QR de leur adresse ; ni les plantes archivées, ni celles d'une autre zone.
// Une zone de B, ou inexistante → 404.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-10');

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->jardins = new DeuxJardins;

    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $this->haie = Zone::factory()->create(['nom' => 'Haie du nord']);
    // Créées dans le désordre ; une archivée, une dans une autre zone.
    foreach ([['HAI-003', 'Charme 3', 'au portail'], ['HAI-001', 'Charme 1', null], ['HAI-002', 'Noisetier', 'contre le mur']] as [$code, $nom, $repere]) {
        Plante::factory()->create(['zone_id' => $this->haie->id, 'code_court' => $code, 'nom' => $nom, 'repere' => $repere]);
    }
    Plante::factory()->create(['zone_id' => $this->haie->id, 'code_court' => 'HAI-004', 'nom' => 'Charme arraché', 'etat' => 'archivee', 'archivee_le' => '2026-05-01 10:00:00+02']);
    Plante::factory()->create(['code_court' => 'FRU-001', 'nom' => 'Pommier ailleurs']);
    app()->forgetScopedInstances();
});

it('rend les plantes actives de la zone, triées par code, avec leur QR', function () {
    $this->withoutVite()->actingAs($this->jardins->membreA)->withSession(['jardin_id' => $this->jardins->a->id])
        ->get("/zones/{$this->haie->id}/etiquettes")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Bureau/Etiquettes')
            ->where('zone', ['id' => $this->haie->id, 'nom' => 'Haie du nord'])
            ->count('etiquettes', 3)
            ->where('etiquettes.0', fn ($e) => $e['code_court'] === 'HAI-001' && $e['nom'] === 'Charme 1' && $e['repere'] === null)
            ->where('etiquettes.1', fn ($e) => $e['code_court'] === 'HAI-002' && $e['nom'] === 'Noisetier' && $e['repere'] === 'contre le mur')
            ->where('etiquettes.2', fn ($e) => $e['code_court'] === 'HAI-003' && $e['nom'] === 'Charme 3' && $e['repere'] === 'au portail')
            ->where('etiquettes.0.qr_svg', fn (string $svg) => str_starts_with($svg, '<svg ') && str_ends_with(trim($svg), '</svg>'))
            ->etc());
});

it('rend 404 pour une zone de B ou inexistante, sans marqueur de B', function (string $quelle) {
    $zone = match ($quelle) {
        'B' => (string) DB::table('zones')->where('jardin_id', $this->jardins->b->id)->value('id'),
        'inexistante' => '3f1c2a9e-0000-4000-8000-000000000000',
        'pas un uuid' => 'haie',
    };

    $corps = $this->withoutVite()->actingAs($this->jardins->desDeux)->withSession(['jardin_id' => $this->jardins->a->id])
        ->get("/zones/{$zone}/etiquettes")
        ->assertNotFound()
        ->getContent();

    expect((string) $corps)->not->toContain(DeuxJardins::MARQUEUR);
})->with(['B', 'inexistante', 'pas un uuid']);
