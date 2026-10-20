<?php

// V35 : un job connaît son jardin ou ne démarre pas (A20, E3). Il porte son
// `jardinId`, repositionne le jardin courant à son démarrage et n'écrit que
// dans ce jardin ; sans `jardinId`, il échoue avant `handle()`, sans écrire.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Jardin;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Src\Support\Jardin\JobDeJardin;

uses(RefreshDatabase::class);

/** Un job témoin : il crée une zone, sans jamais dire dans quel jardin. */
final class JobTemoinDeJardin implements ShouldQueue
{
    use Dispatchable, JobDeJardin, Queueable;

    public static int $executions = 0;

    public function handle(): void
    {
        self::$executions++;
        Zone::create(['nom' => 'Zone du job']);
    }
}

/** @return array{a: Jardin, b: Jardin} deux jardins ; B est le jardin courant */
function deuxJardinsPourJob(): array
{
    test()->seed(ReferenceSeeder::class);
    [$a, $b] = Jardin::factory()->count(2)->create()->all();
    app(JardinCourant::class)->positionner($b->id);
    JobTemoinDeJardin::$executions = 0;

    return ['a' => $a, 'b' => $b];
}

/** @return array<string, int> le nombre de zones de chaque jardin, lu sans filtre */
function zonesParJardin(): array
{
    /** @var array<string, int> */
    return DB::table('zones')->selectRaw('jardin_id, count(*) as n')->groupBy('jardin_id')->pluck('n', 'jardin_id')->all();
}

it('n\'écrit que dans le jardin qu\'il porte, passé par la file', function () {
    ['a' => $a] = deuxJardinsPourJob();
    config(['queue.default' => 'database']);

    $job = new JobTemoinDeJardin;
    $job->jardinId = $a->id;
    dispatch($job);
    expect(zonesParJardin())->toBe([]);

    // Le worker (sans --once) oublie le jardin courant avant chaque job (D-80) :
    // seul le `jardinId` du payload peut le repositionner. Le worker mesure la
    // mémoire de tout le processus de la suite, pas celle du job : sa limite
    // par défaut (128 Mo) se dépasse quand la suite grandit (code 12).
    test()->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--memory' => 1024])->assertExitCode(0);

    expect(JobTemoinDeJardin::$executions)->toBe(1);
    expect(zonesParJardin())->toBe([$a->id => 1]);
    expect(DB::table('failed_jobs')->count())->toBe(0);
    expect(app(JardinCourant::class)->estPositionne())->toBeFalse();
});

it('rend au code qui l\'a lancé en synchrone son propre jardin courant', function () {
    ['a' => $a, 'b' => $b] = deuxJardinsPourJob();

    $job = new JobTemoinDeJardin;
    $job->jardinId = $a->id;
    dispatch_sync($job);

    expect(zonesParJardin())->toBe([$a->id => 1]);
    expect(app(JardinCourant::class)->id())->toBe($b->id);
});

it('échoue avant handle(), sans rien écrire, quand il ne porte pas de jardin', function () {
    deuxJardinsPourJob();

    expect(fn () => dispatch_sync(new JobTemoinDeJardin))->toThrow(LogicException::class, 'jardinId');

    expect(JobTemoinDeJardin::$executions)->toBe(0);
    expect(zonesParJardin())->toBe([]);
});

it('échoue dans la file aussi, et le job part en échec sans rien écrire', function () {
    deuxJardinsPourJob();
    config(['queue.default' => 'database']);

    dispatch(new JobTemoinDeJardin);
    test()->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1]);

    expect(JobTemoinDeJardin::$executions)->toBe(0);
    expect(zonesParJardin())->toBe([]);
    expect(DB::table('failed_jobs')->value('exception'))->toContain('LogicException');
});
