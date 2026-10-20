#!/usr/bin/env bash
# make criteres (chapitre 6 §6.3, C12, RG-46) : les critères du §10 du CDC qui
# n'ont aucun test portant le groupe `critere-NN`.
#   scripts/criteres.sh                 informatif : rend 0
#   LIVRAISON=1 scripts/criteres.sh     bloquant : rend 1 s'il en manque un
# CDC et TESTS désignent d'autres fichiers (utilisé par le test du script).
set -u
RACINE=$(cd "$(dirname "$0")/.." && pwd)
CDC=${CDC:-$RACINE/docs/cahier-des-charges-fonctionnel.md}
TESTS=${TESTS:-$RACINE/tests}

# La liste numérotée du §10, jusqu'au §11.
numeros=$(awk '/^## 10\./ { dans = 1; next } /^## / { dans = 0 } dans && /^[0-9]+\. / { sub(/\..*/, ""); print }' "$CDC")
[ -n "$numeros" ] || { echo "ECHOUE aucun critère lu au §10 de $CDC"; exit 2; }

# Les groupes Pest `->group(..., 'critere-NN', ...)` des tests.
couverts=$(grep -rhoE -- "->group\([^)]*\)" "$TESTS" 2>/dev/null | grep -oE "critere-[0-9]{2}" | sed 's/critere-0*//' | sort -un)

manquants=()
for n in $numeros; do
  echo "$couverts" | grep -qx "$n" || manquants+=("$n")
done
total=$(echo "$numeros" | wc -l | tr -d ' ')
if [ ${#manquants[@]} = 0 ]; then
  echo "criteres : les $total critères ont un test"
  exit 0
fi
echo "criteres : ${#manquants[@]} critère(s) sur $total sans test : ${manquants[*]}"
[ "${LIVRAISON:-}" = 1 ] && exit 1
exit 0
