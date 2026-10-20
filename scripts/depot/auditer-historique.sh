#!/usr/bin/env bash
# Audit de tout l'historique avant publication (V1, C25, D-33).
#   scripts/depot/auditer-historique.sh <dépôt>
# Rend 0 si rien n'est trouvé, 1 sinon. N'affiche jamais une valeur trouvée :
# seulement commit, fichier et règle. La liste des lieux interdits est lue hors
# du dépôt ($LIEUX_INTERDITS, par défaut ~/.config/jardin/lieux-interdits.txt).
set -u
DEPOT=${1:?usage : auditer-historique.sh <dépôt>}
LIEUX=${LIEUX_INTERDITS:-$HOME/.config/jardin/lieux-interdits.txt}
if ! grep -q '[^[:space:]]' "$LIEUX" 2>/dev/null; then
  echo "ECHOUE liste des lieux interdits absente ou vide : $LIEUX (une ligne par nom, écrite par Léon)"
  exit 1
fi
command -v gitleaks > /dev/null || { echo "ECHOUE gitleaks absent (brew install gitleaks)"; exit 1; }
COMMITS=$(git -C "$DEPOT" rev-list --all --count) || { echo "ECHOUE $DEPOT n'est pas un dépôt git"; exit 1; }
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT

# 1. Secrets : gitleaks sur tous les commits, valeurs masquées.
CONFIG=()
[ -f "$DEPOT/.gitleaks.toml" ] && CONFIG=(--config "$DEPOT/.gitleaks.toml")
gitleaks git "$DEPOT" --log-opts="--all" ${CONFIG[@]+"${CONFIG[@]}"} --redact --no-banner --exit-code 0 \
  --report-format csv --report-path "$T/gitleaks.csv" > /dev/null 2>&1 \
  || { echo "ECHOUE gitleaks n'a pas pu lire $DEPOT"; exit 1; }
# Colonnes du rapport CSV de gitleaks : RuleID,Commit,File,… ; on n'en garde que trois.
awk -F, 'NR > 1 { print "secret     " substr($2, 1, 10) "  " $3 "  " $1 }' "$T/gitleaks.csv" \
  > "$T/resultats"

# 2. Coordonnées, adresses et lieux réels, dans les lignes ajoutées de tous les commits.
git -C "$DEPOT" log -p --all --no-color --format='commit %H' \
  | awk -v lieux="$LIEUX" '
    BEGIN { while ((getline l < lieux) > 0) { gsub(/^[ \t]+|[ \t\r]+$/, "", l); if (l != "") L[tolower(l)] = 1 } }
    /^commit [0-9a-f]+$/ { c = substr($2, 1, 10); next }
    /^\+\+\+ / { f = substr($0, 7); next }
    /^\+/ {
      s = substr($0, 2); b = tolower(s)
      if (s ~ /-?[0-9]{1,2}\.[0-9]{4,}[ ,;]+-?[0-9]{1,3}\.[0-9]{4,}/) print "coordonnée " c "  " f
      if (b ~ /[0-9]+,? (rue|chemin|route|allée|impasse|avenue|boulevard|place|lieu-dit) /) print "adresse    " c "  " f
      for (l in L) if (index(b, l)) { print "lieu       " c "  " f "  (ligne " NR " du journal)"; break }
    }' | sort -u >> "$T/resultats"

# 3. Les maquettes .figcraft sont des archives zip : ni gitleaks ni la recherche ci-dessus
#    n'y lisent. Chaque version de l'historique est décompressée et lue de même.
git -C "$DEPOT" log --all --format='%H' --name-only --diff-filter=AM -- '*.figcraft' \
  | awk '/^[0-9a-f]{40}$/ { c = $0; next } NF { print c, $0 }' \
  | while read -r c f; do
      d="$T/fig/${c:0:10}/$(echo "$f" | tr / _)"; mkdir -p "$d"
      git -C "$DEPOT" show "$c:$f" > "$d.zip" && unzip -q -o "$d.zip" -d "$d" 2>/dev/null \
        || echo "illisible  ${c:0:10}  $f" >> "$T/resultats"
      rm -f "$d.zip"
    done
if [ -d "$T/fig" ]; then
  gitleaks dir "$T/fig" --redact --no-banner --exit-code 0 --report-format csv \
    --report-path "$T/fig.csv" > /dev/null 2>&1
  awk -F, 'NR > 1 { print "secret     " $3 "  " $1 }' "$T/fig.csv" | sed "s#$T/fig/##" >> "$T/resultats"
  # Seules les chaînes JSON portent du texte saisi ; les nombres (positions, matrices,
  # couleurs) ressembleraient à des coordonnées sans en être.
  find "$T/fig" -type f ! -name '*.png' | while read -r g; do
    grep -o -E '"([^"\\]|\\.)*"' "$g" | awk -v lieux="$LIEUX" -v f="${g#$T/fig/}" '
      BEGIN { while ((getline l < lieux) > 0) { gsub(/^[ \t]+|[ \t\r]+$/, "", l); if (l != "") L[tolower(l)] = 1 } }
      { b = tolower($0)
        if ($0 ~ /-?[0-9]{1,2}\.[0-9]{4,}[ ,;]+-?[0-9]{1,3}\.[0-9]{4,}/) { print "coordonnée " f; exit }
        if (b ~ /[0-9]+,? (rue|chemin|route|allée|impasse|avenue|boulevard|place|lieu-dit) /) { print "adresse    " f; exit }
        for (l in L) if (index(b, l)) { print "lieu       " f; exit } }'
  done | sort -u >> "$T/resultats"
fi

N=$(wc -l < "$T/resultats" | tr -d ' ')
if [ "$N" = 0 ]; then
  echo "CONFIRME audit : $COMMITS commits lus, 0 résultat (gitleaks $(gitleaks version), lieux : $(grep -c '[^[:space:]]' "$LIEUX") noms)"
  exit 0
fi
echo "ECHOUE audit : $COMMITS commits lus, $N résultat(s) — rien n'est publié"
cat "$T/resultats"
exit 1
