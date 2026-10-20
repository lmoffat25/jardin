#!/usr/bin/env bash
# Test des crochets git (V10, C16, C17) sur un dépôt jetable : installés par la
# cible `crochets` du Makefile (celle que `make setup` appelle), ils tournent
# depuis un worktree ; pre-push lance statique puis test, pre-commit reformate.
#   ddev exec bash scripts/hooks/tests/test-crochets.sh     (ou DANS_CI=1)
set -u
if [ "${IS_DDEV_PROJECT:-}" != true ] && [ "${DANS_CI:-}" != 1 ]; then
  echo "ECHOUE à lancer dans DDEV (ddev exec) ou dans la CI (DANS_CI=1)"; exit 1
fi
RACINE=$(cd "$(dirname "$0")/../../.." && pwd)
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
export MARQUEUR="$T/marqueur"
fautes=0
verdict() { if [ "$1" = 0 ]; then echo "ok    $2"; else echo "FAUTE $2"; fautes=$((fautes + 1)); fi; }

# Le Makefile du dépôt jetable reprend le vrai, avec des cibles statique et test
# factices qui écrivent un marqueur ; les outils sont ceux du vrai dépôt.
D="$T/depot"; mkdir -p "$D/scripts" && cp -R "$RACINE/scripts/hooks" "$D/scripts/"
ln -s "$RACINE/vendor" "$D/vendor"; ln -s "$RACINE/node_modules" "$D/node_modules"
printf 'include %s/Makefile\nstatique: ; @echo statique >> $(MARQUEUR)\ntest: ; @echo test >> $(MARQUEUR)\n' "$RACINE" > "$D/Makefile"
git -C "$D" init -q -b main && git -C "$D" config user.email t@t && git -C "$D" config user.name t
(cd "$D" && make --no-print-directory crochets 2>/dev/null) > /dev/null
git -C "$D" add -A && git -C "$D" commit -qm "départ" > /dev/null
git init -q --bare "$T/nu.git" && git -C "$D" remote add origin "$T/nu.git"

git -C "$D" worktree add -q -b essai "$T/arbre" 2>/dev/null
[ -f "$T/arbre/.git" ]; verdict $? "le worktree a un fichier .git, pas un dossier"

printf '<?php\n\n$a  =  1;\n' > "$T/arbre/mal.php"
git -C "$T/arbre" add mal.php && git -C "$T/arbre" commit -qm "mal formaté" > /dev/null 2>&1
[ "$(git -C "$T/arbre" show HEAD:mal.php)" = "$(printf '<?php\n\n$a = 1;')" ]
verdict $? "pre-commit : le fichier PHP mal formaté est commité formaté"

git -C "$T/arbre" push -q origin essai 2>/dev/null
[ "$(cat "$MARQUEUR" 2>/dev/null | tr '\n' ' ')" = "statique test " ]
verdict $? "pre-push depuis le worktree : statique puis test"

printf 'statique: ; @exit 1\n' >> "$T/arbre/Makefile"; : > "$MARQUEUR"
echo "// suite" > "$T/arbre/suite.ts" && git -C "$T/arbre" add suite.ts && git -C "$T/arbre" commit -qm suite > /dev/null 2>&1
git -C "$T/arbre" push -q origin essai 2>/dev/null; code=$?
[ "$code" != 0 ] && ! grep -q test "$MARQUEUR"
verdict $? "pre-push : statique en échec bloque l'envoi, test ne tourne pas"

[ "$fautes" = 0 ] && echo "CONFIRME test des crochets : tous les cas passent" \
  || { echo "ECHOUE test des crochets : $fautes faute(s)"; exit 1; }
