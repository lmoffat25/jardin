<?php

namespace Tests\Architecture\Temoin\Types;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

// Classe témoin de V8 : lue seulement quand TYPES_SOURCES la désigne.
#[TypeScript]
final class PlanteTemoin
{
    public function __construct(
        public string $id,
        public string $nom,
        public ?string $codeCourt,
        public int $arrosages,
    ) {}
}
