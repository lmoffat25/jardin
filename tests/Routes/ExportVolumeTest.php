<?php

// V124 (CDC §8.7) : à 500 plantes et 10 000 interventions, l'export se
// produit en flux, avec une mémoire de pointe sous 128 Mo. Lancé par
// `make balayage`, hors de la suite rapide (D-25).
//
// La borne seule ne prouve pas le flux : tout charger en mémoire tient encore
// sous 128 Mo à ce volume (mesuré : 49,5 Mo). La pointe est donc aussi
// comparée à celle d'un jardin de 1 000 interventions : en flux, elle ne
// croît presque pas avec l'historique (D-112).

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\Export;

uses(RefreshDatabase::class)->group('volume');

/** La borne de V124, en octets. */
const MEMOIRE_EXPORT_MAX = 128 * 1024 * 1024;

/** La croissance tolérée de la pointe, de 1 000 à 10 000 interventions (D-112). */
const CROISSANCE_EXPORT_MAX = 3 * 1024 * 1024;

/**
 * Un jardin de 500 plantes dans dix zones, et $parPlante interventions par
 * plante, d'une note de 200 signes. Écrit en masse, hors modèles. Rend le
 * jardin et son membre.
 *
 * @return array{0: string, 1: Utilisateur}
 */
function jardinDeDixAns(int $parPlante): array
{
    $membre = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $membre->id]);
    Adhesion::create(['utilisateur_id' => $membre->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
    $j = ['jardin_id' => $jardin->id];
    $tonte = RegleFactory::typeFourni('Tonte');

    $zones = array_map(fn (int $z) => $j + ['id' => (string) Str::uuid(), 'nom' => "Zone {$z}"], range(1, 10));
    DB::table('zones')->insert($zones);
    $plantes = array_map(fn (int $p) => $j + ['id' => (string) Str::uuid(), 'zone_id' => $zones[$p % 10]['id'], 'code_court' => sprintf('D%02d-%04d', $parPlante, $p), 'nom' => "Plante {$p}"], range(1, 500));
    DB::table('plantes')->insert($plantes);

    $note = str_repeat('Taille douce. ', 14);
    foreach (array_chunk($plantes, 25) as $paquet) {
        $lignes = [];
        foreach ($paquet as $plante) {
            foreach (range(0, $parPlante - 1) as $n) {
                $lignes[] = $j + [
                    'id' => (string) Str::uuid(), 'plante_id' => $plante['id'], 'type_intervention_id' => $tonte,
                    'auteur_id' => $membre->id, 'faite_le' => date('Y-m-d', strtotime("2016-03-01 +{$n} months")), 'note' => $note,
                ];
            }
        }
        DB::table('interventions')->insert($lignes);
    }

    return [$jardin->id, $membre];
}

/**
 * L'export d'un jardin, et la pointe de mémoire qu'il atteint.
 *
 * @return array{0: StreamedResponse|null, 1: string, 2: int}
 */
function exporterEnMesurant(string $jardin, Utilisateur $membre): array
{
    gc_collect_cycles();
    memory_reset_peak_usage();
    $reponse = Export::telecharger($membre, $jardin)->assertOk();
    $archive = $reponse->streamedContent();
    $pointe = memory_get_peak_usage();
    $base = $reponse->baseResponse;

    return [$base instanceof StreamedResponse ? $base : null, $archive, $pointe];
}

it('exporte dix ans d\'historique en flux, sous 128 Mo', function () {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
    [$petit, $membrePetit] = jardinDeDixAns(2);
    [$jardin, $membre] = jardinDeDixAns(20);

    [, , $pointePetit] = exporterEnMesurant($petit, $membrePetit);
    [$flux, $archive, $pointe] = exporterEnMesurant($jardin, $membre);

    expect($flux)->not->toBeNull()
        ->and($pointe)->toBeLessThan(MEMOIRE_EXPORT_MAX)
        ->and($pointe - $pointePetit)->toBeLessThan(CROISSANCE_EXPORT_MAX);

    $chemin = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($chemin, $archive);
    $zip = new ZipArchive;
    $zip->open($chemin);
    $interventions = Export::csv((string) $zip->getFromName('interventions.csv'));
    $zip->close();
    unlink($chemin);
    expect($interventions['lignes'])->toHaveCount(10000);
    fwrite(STDERR, sprintf("export : pointe %.1f Mo à 1 000 interventions, %.1f Mo à 10 000, archive %.1f Mo\n", $pointePetit / 1048576, $pointe / 1048576, strlen($archive) / 1048576));
});
