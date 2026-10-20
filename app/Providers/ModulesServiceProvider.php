<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Support\Jardin\JardinCourant;

/**
 * Charge le fichier `routes.php` de chaque module de `src/` (A16) : un module
 * déclare ses routes sans toucher à aucun autre fichier. Aucun fichier n'est
 * une erreur.
 *
 * Enregistre aussi `JardinCourant` en instance de portée (A11) : le conteneur
 * l'oublie au début de chaque requête et de chaque job, aucun jardin ne
 * survit de l'un à l'autre.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(JardinCourant::class);
    }

    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->charger($this->app->basePath('src'));
    }

    public function charger(string $racine): void
    {
        foreach (self::fichiersDeRoutes($racine) as $fichier) {
            Route::middleware('web')->group($fichier);
        }
    }

    /** @return list<string> */
    public static function fichiersDeRoutes(string $racine): array
    {
        $fichiers = glob($racine.'/*/routes.php') ?: [];
        sort($fichiers);

        return $fichiers;
    }
}
