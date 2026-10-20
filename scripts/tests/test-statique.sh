#!/usr/bin/env bash
# Test de `make statique` (V9) : une violation témoin par outil, sur une copie du
# dépôt, doit le faire échouer, et c'est bien l'outil visé qui proteste.
#   ddev exec bash scripts/tests/test-statique.sh     (ou DANS_CI=1 dans la CI)
set -u
if [ "${IS_DDEV_PROJECT:-}" != true ] && [ "${DANS_CI:-}" != 1 ]; then
  echo "ECHOUE à lancer dans DDEV (ddev exec) ou dans la CI (DANS_CI=1)"; exit 1
fi
RACINE=$(cd "$(dirname "$0")/../.." && pwd)
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
C="$T/copie"
mkdir "$C" && (cd "$RACINE" && tar --exclude=./.git --exclude=./storage/logs -cf - .) | (cd "$C" && tar -xf -)

fautes=0
temoin() {  # temoin <libellé> <motif attendu dans la sortie> <fichier> [contenu]  (sans contenu : fichier modifié par une commande avant l'appel)
  local libelle=$1 motif=$2 fichier=$3
  [ $# -ge 4 ] && { mkdir -p "$(dirname "$C/$fichier")"; printf '%s' "$4" > "$C/$fichier"; }
  (cd "$C" && make statique) > "$T/sortie" 2>&1; local code=$?
  if [ "$code" != 0 ] && grep -q -E "$motif" "$T/sortie"; then echo "ok    $libelle"
  else echo "FAUTE $libelle : code $code, motif « $motif » absent"; tail -15 "$T/sortie" | sed 's/^/      /'; fautes=$((fautes + 1)); fi
}
retirer() { rm -f "$C/$1"; }
sauver() { cp "$C/$1" "$T/$(basename "$1").orig"; }
restaurer() { cp "$T/$(basename "$1").orig" "$C/$1"; }

(cd "$C" && make statique) > "$T/sortie" 2>&1 \
  && echo "ok    la copie est propre" || { echo "ECHOUE la copie n'est pas propre au départ"; tail -15 "$T/sortie"; exit 1; }

temoin "PHPStan : paramètre non typé" "Found [0-9]+ error" src/Temoin/Faute.php \
  $'<?php\n\nnamespace Src\\Temoin;\n\nfunction identite($x)\n{\n    return $x;\n}\n'
retirer src/Temoin/Faute.php
temoin "Pint : espaces en trop" "FAIL" src/Temoin/Espaces.php \
  $'<?php\n\nnamespace Src\\Temoin;\n\nconst UN  =  1;\n'
retirer src/Temoin/Espaces.php
temoin "tsc : type faux" "error TS" resources/js/faute.ts \
  $'const x: string = 3;\nexport default x;\n'
retirer resources/js/faute.ts
temoin "ESLint : any explicite" "no-explicit-any" resources/js/faute.ts \
  $'export const identite = (x: any): any => x;\n'
retirer resources/js/faute.ts
temoin "Prettier : mise en forme" "Code style issues" resources/js/faute.ts \
  $'export const   un:number  =  1\n'
retirer resources/js/faute.ts

sauver composer.lock
php -r '$f = $argv[1]; $l = json_decode(file_get_contents($f), true);
  $l["packages"][] = ["name" => "phpmailer/phpmailer", "version" => "v5.2.0", "type" => "library"];
  file_put_contents($f, json_encode($l, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));' "$C/composer.lock"
temoin "composer audit : dépendance vulnérable fictive" "phpmailer/phpmailer" composer.lock
restaurer composer.lock

sauver package-lock.json
php -r '$f = $argv[1]; $l = json_decode(file_get_contents($f), true);
  $l["packages"][""]["dependencies"]["lodash"] = "4.17.15";
  $l["packages"]["node_modules/lodash"] = ["version" => "4.17.15",
    "resolved" => "https://registry.npmjs.org/lodash/-/lodash-4.17.15.tgz", "license" => "MIT"];
  file_put_contents($f, json_encode($l, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));' "$C/package-lock.json"
temoin "npm audit : dépendance vulnérable fictive" "lodash" package-lock.json
restaurer package-lock.json

[ "$fautes" = 0 ] && echo "CONFIRME test de make statique : chaque outil attrape sa violation" \
  || { echo "ECHOUE test de make statique : $fautes faute(s)"; exit 1; }
