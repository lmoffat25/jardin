#!/usr/bin/env bash
#
# Lance une session neuve par sprint, sans surveillance, jusqu'à la fin du plan
# ou un arrêt (P8, D-12). Adapté de buildfeature-run.sh (Treize Pourcent).
#
#   bash scripts/plan/enchainer.sh                  # depuis n'importe quel clone ou worktree
#   MAX_SPRINTS=1 bash scripts/plan/enchainer.sh    # une seule passe
#
# Chaque passe est `claude -p "/construire-sprint --un-sprint"` dans le clone
# principal, qui reste sur `main` : le skill construit dans un worktree, fusionne
# par la fusion automatique, puis ajoute une ligne à docs/plan/handoffs.jsonl du
# clone principal. Avant chaque passe, `git pull --ff-only` remet main à jour et
# les cases de docs/plan/tasks.md sont relues là : c'est l'état du plan.
#
# Une passe qui n'ajoute aucune ligne n'a fini aucun sprint. Terminée sur un
# paragraphe `ARRET:` (acte qui engage Léon), la boucle s'arrête, fini ou non.
# Sans `ARRET:`, elle a été coupée : elle est relancée une fois, une seule.
# Un sprint dont l'en-tête porte `**En attente :** Q-UX-n` est sauté (P3).
#
# `claude -p` tue les tâches de fond 600 s après la fin du tour principal ;
# CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS=0 le fait attendre.
#
# Journal : docs/plan/run.log du clone principal (ignoré par git). Après chaque
# passe, le terminal montre ses blocs « Décisions prises », « Points faibles »,
# « En attente » et sa ligne « Suivant : ».
#
# Codes : 0 plan fini · 1 passe en erreur ou main pas à jour · 2 rien à lancer ·
# 3 passe sans sprint fini, ou ARRET: · 4 MAX_SPRINTS atteint (3 par défaut,
# D-32) · 5 tous les sprints restants attendent une question UX.
#
# Variables : ENCHAINER_CLAUDE (le binaire, un faux dans les tests),
# ENCHAINER_RACINE (le clone principal), ENCHAINER_PREVENIR (commande facultative
# appelée sur 3 et 5, avec le motif en premier argument, O27).

set -uo pipefail

CLAUDE="${ENCHAINER_CLAUDE:-claude}"
MAX_SPRINTS="${MAX_SPRINTS:-3}"

if [ -n "${ENCHAINER_RACINE:-}" ]; then
    racine="$ENCHAINER_RACINE"
else
    commun="$(git rev-parse --path-format=absolute --git-common-dir 2>/dev/null)" || {
        echo "enchainer : hors du dépôt" >&2; exit 2; }
    racine="$(dirname "$commun")"
fi
taches="$racine/docs/plan/tasks.md"
handoffs="$racine/docs/plan/handoffs.jsonl"
journal="$racine/docs/plan/run.log"

prevenir() {
    [ -n "${ENCHAINER_PREVENIR:-}" ] || return 0
    sh -c "$ENCHAINER_PREVENIR \"\$1\"" enchainer "$1" || echo "enchainer : la prévenance a échoué (code $?)" >&2
}

# compter → « <cases vides des sprints libres> <cases vides des sprints en attente> ».
# Seules les sections `## Sprint` comptent : les portes du plan restent vides pour
# toujours. Le marqueur se lit dans l'en-tête, avant le premier `###`.
compter() {
    awk '/^## /{s = ($0 ~ /^## Sprint /); att = 0; tete = 1}
         s && /^### /{tete = 0}
         s && tete && /^\*\*En attente :\*\*/{att = 1}
         s && /^[[:space:]]*- \[ \]/{ if (att) a++; else n++ }
         END{print n+0, a+0}' "$taches"
}
en_attente() {
    awk '/^## /{s = ($0 ~ /^## Sprint /) ? $0 : ""; tete = 1}
         s != "" && /^### /{tete = 0}
         s != "" && tete && /^\*\*En attente :\*\*/{sub(/^\*\*En attente :\*\*[[:space:]]*/, ""); print s " (" $0 ")"}' "$taches"
}
lignes() { [ -f "$handoffs" ] && grep -c . "$handoffs" || echo 0; }

# bilan <passe> <première ligne de la passe dans le journal> : affichage seul,
# il ne fait jamais échouer la boucle.
bilan() {
    tail -n "+$2" "$journal" 2>/dev/null | awk -v passe="$1" '
        /^Permission allow rule/ { next }
        { clair = $0; gsub(/\*\*/, "", clair); sub(/^#+[[:space:]]*/, "", clair) }
        clair ~ /^(Décisions prises|Points faibles|En attente)[^:]*:[[:space:]]*$/ {
            dans = 1; blocs++; corps = corps "  " clair "\n"; next }
        clair ~ /^Suivant : / { dans = 0; suivant = clair; next }
        dans && /^[[:space:]]*$/ { next }
        dans && /^([-*] |[0-9]+[.)] |[[:space:]])/ { corps = corps "    " $0 "\n"; next }
        { dans = 0 }
        END {
            if (blocs == 0) printf "  passe %s : aucun bloc Décisions prises / Points faibles dans son bilan\n", passe
            else printf "%s", corps
            if (suivant != "") printf "  %s\n", suivant
        }' 2>/dev/null || echo "  passe $1 : bilan illisible, voir $journal"
}

if [ ! -f "$taches" ]; then
    echo "enchainer : pas de $taches, rien à lancer" >&2; exit 2
fi
if ! grep -q '^## Sprint ' "$taches"; then
    echo "enchainer : aucune section « ## Sprint » dans $taches, rien à lancer" >&2; exit 2
fi

passes=0
reprise=0
while :; do
    branche="$(git -C "$racine" symbolic-ref --short -q HEAD)"
    if [ "$branche" != main ]; then
        echo "ECHOUE — le clone principal $racine est sur « ${branche:-détaché} », pas sur main : l'état du plan se lit sur main"
        exit 1
    fi
    if ! git -C "$racine" pull -q --ff-only > /dev/null 2>&1; then
        echo "ECHOUE — git pull --ff-only impossible dans $racine : main n'est pas à jour, le plan n'est pas relu"
        git -C "$racine" pull --ff-only 2>&1 | tail -n 5 | sed 's/^/  /'
        exit 1
    fi
    read -r libres attente <<< "$(compter)"
    if [ "$libres" -eq 0 ] && [ "$attente" -eq 0 ]; then
        echo "CONFIRME — tous les sprints du plan sont cochés ($passes passe(s) dans ce lancement)"
        exit 0
    fi
    if [ "$libres" -eq 0 ]; then
        motif="ARRET — les sprints restants attendent tous une réponse dans docs/plan/questions-ux.md : $(en_attente | paste -sd ';' - | sed 's/;/ ; /g')"
        echo "$motif"
        prevenir "$motif"
        exit 5
    fi
    if [ "$passes" -ge "$MAX_SPRINTS" ]; then
        echo "ARRET — MAX_SPRINTS=$MAX_SPRINTS atteint, $libres case(s) libre(s) et $attente en attente"
        exit 4
    fi

    avant="$(lignes)"
    passes=$((passes + 1))
    debut=$(( $( (wc -l < "$journal") 2>/dev/null || echo 0) + 1 ))
    printf '\n===== passe %d — %s — %d case(s) libre(s), %d en attente\n' "$passes" "$(date '+%Y-%m-%d %H:%M:%S')" "$libres" "$attente" >> "$journal"
    echo "passe $passes : /construire-sprint --un-sprint ($libres case(s) libre(s)) → $journal"

    sortie="$(mktemp)"
    ( cd "$racine" && CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS="${CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS:-0}" \
        "$CLAUDE" -p "/construire-sprint --un-sprint" --permission-mode auto --output-format text ) > "$sortie" 2>&1
    code=$?
    cat "$sortie" >> "$journal"
    arret=0
    grep -q '^ARRET:' "$sortie" && arret=1
    rm -f "$sortie"
    if [ "$code" -ne 0 ]; then
        echo "ECHOUE — la passe $passes est sortie en $code ; fin de $journal :"
        tail -n 40 "$journal"
        exit 1
    fi
    if [ "$arret" -eq 1 ]; then
        [ "$(lignes)" -gt "$avant" ] && bilan "$passes" "$debut"
        motif="ARRET — la passe $passes s'est arrêtée sur un acte qui vous revient (ARRET: dans $journal)"
        echo "$motif ; fin de la passe :"
        tail -n 40 "$journal"
        prevenir "$motif"
        exit 3
    fi
    if [ "$(lignes)" -le "$avant" ]; then
        if [ "$reprise" -eq 0 ]; then
            reprise=1
            echo "REPRISE — la passe $passes n'a fini aucun sprint et n'a rien demandé (coupée) : relancée une fois"
            continue
        fi
        motif="ARRET — la passe $passes n'a fini aucun sprint (aucune ligne nouvelle dans handoffs.jsonl), deux fois de suite"
        echo "$motif ; fin de $journal :"
        tail -n 40 "$journal"
        prevenir "$motif"
        exit 3
    fi
    reprise=0
    bilan "$passes" "$debut" || true
done
