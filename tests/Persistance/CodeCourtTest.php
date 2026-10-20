<?php

// V81 (RG-13, S8, D-03) : chaque plante reçoit un code `PREFIXE-NNN` tiré d'un
// compteur par jardin et par préfixe ; un code n'est jamais réattribué, même
// après archivage ou suppression ; deux créations simultanées dans le même
// jardin, par deux connexions réelles dont les transactions sont validées,
// reçoivent deux codes distincts.
//
// `DatabaseMigrations` et non `RefreshDatabase` : la transaction de test
// cacherait le jardin à la seconde connexion, et garderait le verrou de la
// première jusqu'à la fin du test.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Src\Bibliotheque\Domaine\Espece;
use Src\Comptes\Domaine\Jardin;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Identification\AttribuerCodeCourt;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\TemoinBibliotheque;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier([
        'fruitiers' => [TemoinBibliotheque::fruitier()],
        'pelouses' => [TemoinBibliotheque::pelouse()],
        'petits-fruits' => [TemoinBibliotheque::framboisier()],
    ])])->assertSuccessful();
    $this->jardin = Jardin::factory()->create();
    app(JardinCourant::class)->positionner($this->jardin->id);
});

/** Une plante créée dans le jardin courant, son code attribué dans la transaction de création. */
function planteCodee(?string $cleEspece = null): Plante
{
    $espece = $cleEspece === null ? null : Espece::query()->where('cle', $cleEspece)->sole();

    return DB::transaction(fn () => Plante::factory()->create([
        'code_court' => app(AttribuerCodeCourt::class)->pour($espece),
        'espece_id' => $espece?->id,
        'nom' => 'Plante '.bin2hex(random_bytes(3)),
    ]));
}

it('numérote par préfixe de catégorie, PLA sans espèce', function () {
    $codes = array_map(fn (?string $e) => planteCodee($e)->code_court, [null, null, 'fruitier-temoin', null, 'pelouse-temoin', 'framboisier-temoin', 'fruitier-temoin']);

    expect($codes)->toBe(['PLA-001', 'PLA-002', 'FRU-001', 'PLA-003', 'GAZ-001', 'PET-001', 'FRU-002'])
        ->and(DB::table('compteurs_codes_courts')->orderBy('prefixe')->pluck('dernier', 'prefixe')->all())
        ->toBe(['FRU' => 2, 'GAZ' => 1, 'PET' => 1, 'PLA' => 3]);
});

it('tient un compteur par jardin', function () {
    planteCodee();
    planteCodee();

    $autre = Jardin::factory()->create();
    app(JardinCourant::class)->positionner($autre->id);

    expect(planteCodee()->code_court)->toBe('PLA-001');
});

it('ne réattribue jamais un code, ni après archivage ni après suppression', function () {
    planteCodee();
    $archivee = planteCodee();
    $archivee->update(['etat' => 'archivee', 'archivee_le' => now()]);
    expect(planteCodee()->code_court)->toBe('PLA-003');

    // La dernière supprimée : ni un COUNT ni un MAX ne retrouveraient le compte.
    planteCodee()->delete();
    expect(planteCodee()->code_court)->toBe('PLA-005');
});

it('passe à quatre chiffres après 999', function () {
    planteCodee();
    DB::table('compteurs_codes_courts')->update(['dernier' => 999]);

    expect(planteCodee()->code_court)->toBe('PLA-1000');
});

it('refuse d\'attribuer un code hors d\'une transaction', function () {
    app(AttribuerCodeCourt::class)->pour(null);
})->throws(LogicException::class);

it('donne deux codes distincts à deux créations simultanées, par deux connexions', function () {
    // Le compteur existe déjà : sans verrou, les deux lectures verraient le même `dernier`.
    planteCodee();

    $script = tempnam(sys_get_temp_dir(), 'concurrente');
    file_put_contents($script, <<<'PHP'
        <?php
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        app(Src\Support\Jardin\JardinCourant::class)->positionner($argv[2]);
        Illuminate\Support\Facades\DB::transaction(function () {
            fwrite(STDOUT, app(Src\Jardin\Identification\AttribuerCodeCourt::class)->pour(null)."\n");
            fflush(STDOUT);
            // Le verrou tenu une seconde, transaction ouverte.
            usleep(1_000_000);
        });
        fwrite(STDOUT, "validee\n");
        PHP);

    $processus = proc_open([PHP_BINARY, $script, base_path(), $this->jardin->id], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubes);
    expect($processus)->toBeResource();
    // Lire stderr ici attendrait la fin du processus : il n'est lu qu'en cas d'échec.
    $codeConcurrent = trim((string) fgets($tubes[1]));
    if (preg_match('/^PLA-\d{3}$/', $codeConcurrent) !== 1) {
        test()->fail('processus concurrent : '.stream_get_contents($tubes[2]));
    }

    $debut = microtime(true);
    $code = DB::transaction(fn () => app(AttribuerCodeCourt::class)->pour(null));
    $attente = microtime(true) - $debut;

    expect(trim((string) fgets($tubes[1])))->toBe('validee');
    fclose($tubes[1]);
    fclose($tubes[2]);
    expect(proc_close($processus))->toBe(0);
    unlink($script);

    expect([$codeConcurrent, $code])->toBe(['PLA-002', 'PLA-003'])
        // La seconde connexion a attendu la validation de la première.
        ->and($attente)->toBeGreaterThan(0.5)
        ->and(DB::table('compteurs_codes_courts')->value('dernier'))->toBe(3);
});
