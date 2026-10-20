<?php

// V4 : un fichier src/<Module>/routes.php est chargé sans toucher à aucun autre
// fichier ; son absence ne casse rien (A16).

use App\Providers\ModulesServiceProvider;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->racine = sys_get_temp_dir().'/modules-'.bin2hex(random_bytes(4));
    File::makeDirectory($this->racine);
});

afterEach(function () {
    File::deleteDirectory($this->racine);
});

function chargerModules(string $racine): void
{
    (new ModulesServiceProvider(app()))->charger($racine);
    app('router')->getRoutes()->refreshNameLookups();
}

it('charge la route d\'un module témoin', function () {
    File::makeDirectory($this->racine.'/Temoin');
    File::put($this->racine.'/Temoin/routes.php',
        "<?php\nIlluminate\\Support\\Facades\\Route::get('/temoin', fn () => 'temoin');\n");

    chargerModules($this->racine);

    $this->get('/temoin')->assertOk()->assertSee('temoin');
});

it('démarre sans aucun fichier de routes', function () {
    File::makeDirectory($this->racine.'/SansRoutes');

    chargerModules($this->racine);

    expect(ModulesServiceProvider::fichiersDeRoutes($this->racine))->toBe([]);
    $this->get('/sante')->assertOk();
});

it('lit les modules dans l\'ordre alphabétique', function () {
    foreach (['Zeta', 'Alpha', 'Mu'] as $module) {
        File::makeDirectory($this->racine.'/'.$module);
        File::put($this->racine.'/'.$module.'/routes.php', "<?php\n");
    }

    $modules = array_map(fn ($f) => basename(dirname($f)), ModulesServiceProvider::fichiersDeRoutes($this->racine));

    expect($modules)->toBe(['Alpha', 'Mu', 'Zeta']);
});
