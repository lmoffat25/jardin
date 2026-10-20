#!/usr/bin/env bash
# Types TypeScript générés depuis PHP (V8, D-77), appelé par make types et make statique :
#   types.sh ecrire   <fichier> <dossier>…   génère et écrit <fichier>
#   types.sh comparer <fichier> <dossier>…   génère à part, échoue si <fichier> diffère
# Le générateur écrit dans un dossier temporaire (il y laisse son manifeste) ; Prettier
# met le résultat en forme selon la configuration de resources/js, où vit le versionné.
set -euo pipefail
mode=$1 fichier=$2; shift 2
d=$(mktemp -d); trap 'rm -rf "$d"' EXIT

TYPES_SOURCES="$*" TYPES_BRUT="$d" php artisan typescript:transform --quiet --no-interaction
touch "$d/generes.d.ts"
{ echo '// Produit par make types depuis les classes PHP #[TypeScript] : ne pas modifier.'
  cat "$d/generes.d.ts"; } \
  | npx prettier --stdin-filepath resources/js/types/generes.d.ts > "$d/propre"

case $mode in
  ecrire) cp "$d/propre" "$fichier" ;;
  comparer)
    cmp -s "$d/propre" "$fichier" && exit 0
    echo "types générés périmés : make types"
    diff -u "$fichier" "$d/propre" || true
    exit 1 ;;
  *) echo "mode inconnu : $mode" >&2; exit 2 ;;
esac
