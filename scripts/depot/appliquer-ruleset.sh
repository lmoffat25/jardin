#!/usr/bin/env bash
# Applique .github/ruleset-main.json au dépôt et relit ce que GitHub a gardé (C2).
#   scripts/depot/appliquer-ruleset.sh <propriétaire/dépôt>
# Création par POST la première fois, puis PUT : jamais PATCH, qui fusionnerait
# avec l'existant au lieu de le remplacer. Rend 1 si la relecture diffère.
set -u
DEPOT=${1:?usage : appliquer-ruleset.sh <propriétaire/dépôt>}
CHARGE="$(cd "$(dirname "$0")/../.." && pwd)/.github/ruleset-main.json"
NOM=$(python3 -I -c 'import json,sys; print(json.load(open(sys.argv[1]))["name"])' "$CHARGE")
ID=$(gh api "repos/$DEPOT/rulesets" --jq ".[] | select(.name == \"$NOM\") | .id") \
  || { echo "ECHOUE lecture des rulesets de $DEPOT"; exit 1; }
if [ -z "$ID" ]; then
  ID=$(gh api --method POST "repos/$DEPOT/rulesets" --input "$CHARGE" --jq .id) \
    || { echo "ECHOUE création du ruleset"; exit 1; }
else
  gh api --method PUT "repos/$DEPOT/rulesets/$ID" --input "$CHARGE" > /dev/null \
    || { echo "ECHOUE remplacement du ruleset $ID"; exit 1; }
fi
T=$(mktemp); trap 'rm -f "$T"' EXIT
gh api "repos/$DEPOT/rulesets/$ID" > "$T" || { echo "ECHOUE relecture du ruleset $ID"; exit 1; }
# GitHub ajoute ses propres champs (id, dates, valeurs par défaut) : chaque champ de la
# charge doit se retrouver à l'identique dans la relecture, le reste est ignoré.
python3 -I - "$CHARGE" "$T" <<'PY' || exit 1
import json, sys
charge, relu = (json.load(open(f)) for f in sys.argv[1:3])
def inclus(a, b, chemin="ruleset"):
    if isinstance(a, dict):
        return all(isinstance(b, dict) and k in b and inclus(v, b[k], f"{chemin}.{k}")
                   for k, v in a.items())
    if isinstance(a, list) and a and isinstance(a[0], dict) and "type" in a[0]:
        par_type = {r.get("type"): r for r in b or []}
        return all(inclus(r, par_type.get(r["type"]), f"{chemin}[{r['type']}]") for r in a)
    if a != b:
        print(f"ECHOUE relecture : {chemin} vaut {b!r}, attendu {a!r}")
    return a == b
sys.exit(0 if inclus(charge, relu) else 1)
PY
echo "CONFIRME ruleset « $NOM » (id $ID) appliqué à $DEPOT et relu à l'identique"
