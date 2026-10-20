<?php

// Types TypeScript générés depuis PHP (O5, D-77). `make types` fixe les deux
// valeurs : les dossiers lus, et le dossier temporaire où le générateur écrit
// avant que Prettier ne mette le fichier en forme.

$sources = env('TYPES_SOURCES');
$sortie = env('TYPES_BRUT');

return [
    'sources' => is_string($sources) ? $sources : 'app src',
    'sortie' => is_string($sortie) ? $sortie : storage_path('framework/types'),
];
