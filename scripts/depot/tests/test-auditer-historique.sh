#!/usr/bin/env bash
# Test de scripts/depot/auditer-historique.sh (V1) sur des dépôts jetables.
#   bash scripts/depot/tests/test-auditer-historique.sh
set -u
# Les valeurs factices sont coupées en deux chaînes accolées : assemblées à
# l'exécution, elles n'apparaissent jamais d'un tenant dans le dépôt.
ICI=$(cd "$(dirname "$0")/.." && pwd)
AUDIT="$ICI/auditer-historique.sh"
[ -x "$AUDIT" ] || { echo "ECHOUE script absent : $AUDIT"; exit 1; }
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
echo "Bourg-Fictif" > "$T/lieux.txt"
fautes=0
attendre() {  # attendre <code attendu> <libellé> <dépôt> [liste]
  local code
  LIEUX_INTERDITS="${4-$T/lieux.txt}" "$AUDIT" "$3" > "$T/sortie" 2>&1; code=$?
  if [ "$code" = "$1" ]; then echo "ok    $2"; else echo "FAUTE $2 : code $code, attendu $1"; sed 's/^/      /' "$T/sortie"; fautes=$((fautes + 1)); fi
}
depot() { git init -q "$1" && git -C "$1" config user.email t@t && git -C "$1" config user.name t; }
engager() { printf '%s\n' "$2" > "$1/f.txt" && git -C "$1" add f.txt && git -C "$1" commit -qm "$3"; }

depot "$T/propre"; engager "$T/propre" "Un pommier, une fenêtre de taille." "propre"
depot "$T/secret"; engager "$T/secret" "rien" "propre"
engager "$T/secret" "aws_access_key_id = AKIA""IOSFODNN7EXAMPLA" "fuite"
engager "$T/secret" "rien" "retrait"
depot "$T/coord"; engager "$T/coord" "position 47.2381"", 6.0240" "coordonnées"
depot "$T/adresse"; engager "$T/adresse" "Le Clos, 12 ""rue des Lilas" "adresse"
depot "$T/lieu"; engager "$T/lieu" "Visite au bourg-fictif samedi" "lieu"

depot "$T/maquette"; mkdir "$T/z"; echo '{"text":"Le Clos, 12 ''rue des Lilas"}' > "$T/z/document.json"
(cd "$T/z" && zip -q "$T/maquette/m.figcraft" document.json) && git -C "$T/maquette" add m.figcraft \
  && git -C "$T/maquette" commit -qm "maquette"
attendre 0 "dépôt propre" "$T/propre"
attendre 1 "adresse dans une maquette .figcraft compressée" "$T/maquette"
attendre 1 "secret dans un ancien commit, retiré depuis" "$T/secret"
attendre 1 "coordonnées décimales" "$T/coord"
attendre 1 "adresse" "$T/adresse"
attendre 1 "nom de lieu de la liste, casse différente" "$T/lieu"
attendre 1 "liste des lieux absente" "$T/propre" "$T/absente.txt"
: > "$T/vide.txt"; attendre 1 "liste des lieux vide" "$T/propre" "$T/vide.txt"
LIEUX_INTERDITS="$T/lieux.txt" "$AUDIT" "$T/secret" 2>&1 | grep -q "AKIA""IOSFODNN7EXAMPLA" \
  && { echo "FAUTE la valeur du secret est affichée"; fautes=$((fautes + 1)); } || echo "ok    la valeur du secret n'est jamais affichée"
[ "$fautes" = 0 ] && echo "CONFIRME test de l'audit : tous les cas passent" || { echo "ECHOUE test de l'audit : $fautes faute(s)"; exit 1; }
