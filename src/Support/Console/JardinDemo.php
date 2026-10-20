<?php

namespace Src\Support\Console;

use Database\Seeders\DemonstrationSeeder;
use Illuminate\Console\Command;

/**
 * `make jardin-demo` (chapitre 6 §3, V125) : le jardin fictif de 500 plantes,
 * remplacé s'il existe. Pour le développement et la recette seulement : en
 * production, rien n'est écrit (chapitre 4 §5).
 */
final class JardinDemo extends Command
{
    protected $signature = 'jardin:demo';

    protected $description = 'Crée (ou recrée) le jardin fictif de démonstration : 500 plantes et deux ans d\'historique ; jamais en production';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Le jardin de démonstration ne se crée jamais en production : rien n\'est écrit.');

            return self::FAILURE;
        }

        $debut = hrtime(true);
        $seeder = $this->laravel->make(DemonstrationSeeder::class);
        $seeder->setContainer($this->laravel)->setCommand($this)->__invoke();
        $this->info(sprintf('Jardin « %s » créé pour %s, en %.0f s.', DemonstrationSeeder::NOM, DemonstrationSeeder::COURRIEL, (hrtime(true) - $debut) / 1e9));

        return self::SUCCESS;
    }
}
