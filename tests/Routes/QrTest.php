<?php

// V114 (critère 22) : le QR produit, relu par un décodeur en test, rend
// exactement l'adresse absolue `/p/{code}` de la plante. Le décodeur
// (`khanamiryan/qrcode-detector-decoder`, un portage de ZXing) n'est pas
// l'encodeur : il lit le SVG servi, tracé module par module dans une image
// (D-108).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Jardin\Identification\EncodeurQR;
use Src\Support\Jardin\JardinCourant;
use Zxing\QrReader;

uses(RefreshDatabase::class)->group('critere-22');

/**
 * Pixels par module, essayés dans l'ordre : le portage de ZXing manque
 * certains QR à une échelle et les lit à une autre (D-111 ; l'adresse
 * `https://jardin-11b.ddev.site/p/FRU-001` ne se lit qu'à 4). Un échec de
 * lecture rend `false`, jamais un autre texte : essayer plusieurs échelles
 * n'affaiblit pas la preuve.
 */
const PIXELS_PAR_MODULE = [8, 4, 6, 10];

/**
 * Le texte d'un QR en SVG : chaque module foncé (`M x y h1 v1 h-1Z`) noirci
 * dans une image blanche à la taille de la `viewBox`, puis lu.
 */
function lireQr(string $svg): string
{
    expect(preg_match('/viewBox="0 0 (\d+) (\d+)"/', $svg, $boite))->toBe(1);
    expect(preg_match_all('/M(\d+) (\d+) h1 v1 h-1Z/', $svg, $modules, PREG_SET_ORDER))->toBeGreaterThan(100);

    foreach (PIXELS_PAR_MODULE as $pixels) {
        $image = imagecreatetruecolor((int) $boite[1] * $pixels, (int) $boite[2] * $pixels);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        $noir = (int) imagecolorallocate($image, 0, 0, 0);
        foreach ($modules as [, $x, $y]) {
            $gauche = (int) $x * $pixels;
            $haut = (int) $y * $pixels;
            imagefilledrectangle($image, $gauche, $haut, $gauche + $pixels - 1, $haut + $pixels - 1, $noir);
        }

        // GD seul : Imagick n'est pas sûr d'être en CI.
        $texte = (new QrReader($image, QrReader::SOURCE_TYPE_RESOURCE, useImagickIfAvailable: false))->text();
        if (is_string($texte)) {
            return $texte;
        }
    }

    return '';
}

it('encode exactement l\'adresse donnée', function (string $url) {
    expect(lireQr(app(EncodeurQR::class)->svg($url)))->toBe($url);
})->with([
    'courte' => ['http://localhost/p/PLA-002'],
    'longue' => ['https://un-jardin-imaginaire.exemple.fr/p/FRU-014'],
]);

it('donne à chaque étiquette le QR de l\'adresse absolue de sa plante', function () {
    $this->seed(ReferenceSeeder::class);
    $membre = Utilisateur::factory()->create();
    $jardin = Jardin::factory()->create(['proprietaire_id' => $membre->id]);
    Adhesion::create(['utilisateur_id' => $membre->id, 'jardin_id' => $jardin->id, 'role' => 'membre']);
    app(JardinCourant::class)->positionner($jardin->id);
    $verger = Zone::factory()->create(['nom' => 'Verger']);
    foreach (['FRU-001', 'FRU-014'] as $code) {
        Plante::factory()->create(['zone_id' => $verger->id, 'code_court' => $code, 'nom' => "Pommier {$code}"]);
    }
    app()->forgetScopedInstances();

    $etiquettes = null;
    $this->withoutVite()->actingAs($membre)->get("/zones/{$verger->id}/etiquettes")
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$etiquettes) {
            $etiquettes = $page->toArray()['props']['etiquettes'];
        });

    expect(array_map(fn (array $e) => lireQr($e['qr_svg']), $etiquettes))
        ->toBe([url('/p/FRU-001'), url('/p/FRU-014')])
        ->and(url('/p/FRU-001'))->toStartWith('http');
});
