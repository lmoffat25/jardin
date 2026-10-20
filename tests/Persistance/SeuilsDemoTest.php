<?php

// V126 (RG-19, O7) : sur le jardin de démonstration, le tableau de bord et la
// liste des plantes tiennent les seuils de V79 (sous SEUIL_TABLEAU_MS) et de
// V89 (50 lignes par page, le total) ; la mesure est écrite dans
// `decisions-agents.md` (D-113). Lancé par `make balayage` (D-25).

use Database\Seeders\DemonstrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Utilisateur;

uses(RefreshDatabase::class)->group('volume', 'critere-11');

/** Le seuil de V79 (D-100), et celui de la liste, à 500 plantes, en millisecondes (D-113). */
const SEUIL_DEMO_TABLEAU_MS = 1000;

const SEUIL_DEMO_LISTE_MS = 500;

/** @return array{int, float, array<string, mixed>} les requêtes, la durée en ms et les props de `GET $url` */
function mesurerDemo(Utilisateur $membre, string $url, string $page): array
{
    app()->forgetScopedInstances();
    test()->flushSession();
    $requetes = 0;
    DB::listen(function () use (&$requetes) {
        $requetes++;
    });

    $debut = hrtime(true);
    $reponse = test()->withoutVite()->actingAs($membre)->get($url)->assertOk();
    $ms = (hrtime(true) - $debut) / 1e6;
    $reponse->assertInertia(fn (AssertableInertia $p) => $p->component($page));

    return [$requetes, $ms, $reponse->viewData('page')['props']];
}

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-10-15 10:00:00', new DateTimeZone('Europe/Paris')));
    expect(Artisan::call('jardin:demo'))->toBe(0);
    $this->membre = Utilisateur::query()->where('courriel', DemonstrationSeeder::COURRIEL)->firstOrFail();
});

it('rend le tableau de bord du jardin de démonstration sous le seuil de V79', function () {
    // Le premier affichage écrit les manquées ; le second est celui d'un jour ordinaire.
    mesurerDemo($this->membre, '/', 'Bureau/TableauDeBord');
    [$requetes, $ms, $props] = mesurerDemo($this->membre, '/', 'Bureau/TableauDeBord');
    fwrite(STDERR, sprintf("\nV126 tableau de bord : %d requêtes, %.0f ms (seuil %d ms)\n", $requetes, $ms, SEUIL_DEMO_TABLEAU_MS));

    $periodes = count($props['aujourdhui']) + count($props['semaine']) + count($props['mois']) + count($props['planning']);
    expect($periodes)->toBeGreaterThan(0)
        ->and($ms)->toBeLessThan(SEUIL_DEMO_TABLEAU_MS);
});

it('rend la liste des plantes du jardin de démonstration par 50, avec le total, sous le seuil', function () {
    mesurerDemo($this->membre, '/plantes', 'Bureau/Plantes');
    [$requetes, $ms, $props] = mesurerDemo($this->membre, '/plantes', 'Bureau/Plantes');
    fwrite(STDERR, sprintf("\nV126 liste des plantes : %d requêtes, %.0f ms (seuil %d ms)\n", $requetes, $ms, SEUIL_DEMO_LISTE_MS));

    expect($props['plantes'])->toHaveCount(50)
        ->and([$props['total'], $props['pages']])->toBe([500, 10])
        ->and($ms)->toBeLessThan(SEUIL_DEMO_LISTE_MS);

    [, , $derniere] = mesurerDemo($this->membre, '/plantes?page=10', 'Bureau/Plantes');
    expect($derniere['plantes'])->toHaveCount(50);
});
