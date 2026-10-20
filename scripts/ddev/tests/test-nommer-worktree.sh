#!/usr/bin/env bash
# Test de scripts/ddev/nommer-worktree.sh : rien dans le clone principal, un nom
# propre dans un worktree, un fichier existant jamais réécrit (D-73).
set -uo pipefail
SCRIPT="$(cd "$(dirname "$0")/.." && pwd)/nommer-worktree.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fautes=0
ok() { echo "  ok : $1"; }
ko() { echo "  FAUTE : $1"; fautes=$((fautes + 1)); }

git init -q -b main "$T/principal" && mkdir -p "$T/principal/.ddev" && touch "$T/principal/.ddev/config.yaml"
git -C "$T/principal" add -A && git -C "$T/principal" -c user.name=t -c user.email=t@t commit -q -m initial
(cd "$T/principal" && bash "$SCRIPT" > /dev/null)
[ ! -f "$T/principal/.ddev/config.worktree.yaml" ] && ok "rien dans le clone principal" || ko "rien dans le clone principal"

git -C "$T/principal" worktree add -q "$T/Sprint_0G" -b sprint/0g
(cd "$T/Sprint_0G" && bash "$SCRIPT" > /dev/null)
grep -qx 'name: jardin-sprint-0g' "$T/Sprint_0G/.ddev/config.worktree.yaml" 2>/dev/null \
  && ok "nom jardin-<dossier> dans un worktree, en minuscules" || ko "nom jardin-<dossier> dans un worktree, en minuscules"

printf 'name: autre\n' > "$T/Sprint_0G/.ddev/config.worktree.yaml"
(cd "$T/Sprint_0G" && bash "$SCRIPT" > /dev/null)
grep -qx 'name: autre' "$T/Sprint_0G/.ddev/config.worktree.yaml" && ok "fichier existant laissé" || ko "fichier existant laissé"

if [ "$fautes" -gt 0 ]; then echo "ECHOUE test de nommer-worktree : $fautes faute(s)"; exit 1; fi
echo "CONFIRME test de nommer-worktree : tous les cas passent"
