<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Transformers\AttributedClassTransformer;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

// Les classes portant #[TypeScript] deviennent des types exportés d'un seul
// fichier, triés par nom (D-77). Le dossier de sortie est temporaire : le
// générateur y laisse son manifeste, et Prettier écrit le fichier versionné.
class TypesServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $sources = array_map(
            fn (string $dossier): string => base_path($dossier),
            preg_split('/\s+/', config()->string('typescript-transformer.sources'), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        $config
            ->transformer(AttributedClassTransformer::class, EnumTransformer::class)
            ->transformDirectories(...$sources)
            ->outputDirectory(config()->string('typescript-transformer.sortie'))
            ->writer(new FlatModuleWriter('generes.d.ts'));
    }
}
