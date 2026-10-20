#!/usr/bin/env bash
#
# Porte sur main les réponses de Léon écrites dans le clone principal (D-30, V127).
#
#   bash scripts/plan/porter-reponses.sh <clone principal>
#
# Léon répond sous « **Réponse :** » dans docs/plan/questions-ux.md du clone
# principal, sans geste git. Ce script, appelé par enchainer.sh avant chaque
# `git pull --ff-only` :
#   1. n'accepte que des réponses nouvelles : seul questions-ux.md est modifié
#      (indexé ou non), et seulement dans des blocs « Réponse » vides sur HEAD ;
#      toute autre différence → 1, rien n'est envoyé ;
#   2. pour chaque question répondue, dans un worktree tiré d'origin/main :
#      branche `reponse/q-ux-<n>`, la réponse recopiée, `Q-UX-<n>` retiré des
#      lignes `**En attente :**` de tasks.md (la ligne disparaît quand elle est
#      vide), un commit ; l'envoi part du clone principal, pour que le crochet
#      pre-push y tourne avec son projet DDEV (D-74) ; demande de fusion
#      « Réponse à Q-UX-<n> » en fusion automatique, contrôles attendus
#      (`gh pr checks --watch`), puis la fusion elle-même ;
#   3. quand main porte chaque réponse, rend questions-ux.md propre dans le clone.
# Rien n'est jamais envoyé sur main : la fusion est celle de GitHub.
#
# Un bloc « Réponse » va de la ligne `**Réponse :**` à la ligne qui précède le
# `## ` ou le `---` suivant ; une réponse peut tenir sur plusieurs lignes.
#
# Codes : 0 rien à porter, ou réponses fusionnées · 1 autre modification, envoi,
# contrôles rouges ou fusion qui n'arrive pas (la réponse reste dans le clone).
#
# Variables : PORTER_GH (le binaire gh, un faux dans les tests), PORTER_PAUSE
# (secondes entre deux relectures, 15), PORTER_ATTENTE (attente maximale de la
# fusion après des contrôles verts, en secondes, 1800).

set -uo pipefail

GH="${PORTER_GH:-gh}"
PAUSE="${PORTER_PAUSE:-15}"
ATTENTE="${PORTER_ATTENTE:-1800}"
QU=docs/plan/questions-ux.md
TA=docs/plan/tasks.md

clone="${1:-}"
if [ -z "$clone" ] || [ ! -d "$clone" ]; then
    echo "usage : $0 <clone principal>" >&2; exit 1
fi
cd "$clone" || exit 1

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

echouer() { echo "ECHOUE porter-reponses — $1"; exit 1; }

# blocs <mode> <Q-UX-n ou ""> <fichier> [fichier du bloc neuf]
#   squelette  le fichier, chaque bloc « Réponse » réduit à un repère
#   liste      les Q-UX-n qui ont un bloc « Réponse »
#   corps      le bloc de Q-UX-n, sans ses lignes vides de fin
#   remplacer  le fichier, le bloc de Q-UX-n remplacé par le bloc neuf
blocs() {
    awk -v mode="$1" -v cible="$2" -v neuf="${4:-}" '
        function vider(   i, fin, x) {
            if (!dans) return
            fin = nb; while (fin > 0 && L[fin] ~ /^[ \t]*$/) fin--
            if (mode == "squelette") print "<<réponse " q ">>"
            else if (mode == "liste") print q
            else if (mode == "corps" && q == cible) for (i = 1; i <= fin; i++) print L[i]
            else if (mode == "remplacer") {
                if (q == cible) { while ((getline x < neuf) > 0) print x; close(neuf) }
                else for (i = 1; i <= fin; i++) print L[i]
                for (i = fin + 1; i <= nb; i++) print L[i]
            }
            dans = 0; nb = 0
        }
        /^## / || /^---/ { vider() }
        /^## / { q = ($2 ~ /^Q-UX-[0-9]+$/) ? $2 : "" }
        dans { L[++nb] = $0; next }
        q != "" && /^\*\*Réponse :\*\*/ { dans = 1; nb = 0; L[++nb] = $0; next }
        mode == "squelette" || mode == "remplacer" { print }
        END { vider() }
    ' "$3"
}
# vide <corps> : vrai si le bloc ne dit rien après « **Réponse :** ».
vide() {
    local t="${1#\*\*Réponse :\*\*}"
    [ -z "$(printf '%s' "$t" | tr -d ' \t\n')" ]
}
# retirer <Q-UX-n> <tasks.md> : ôte Q-UX-n des lignes « **En attente :** » ;
# une ligne qui n'attend plus rien disparaît, une ligne sans Q-UX-n reste telle quelle.
retirer() {
    awk -v q="$1" '
        /^\*\*En attente :\*\*/ {
            reste = $0; sub(/^\*\*En attente :\*\*[ \t]*/, "", reste)
            n = split(reste, p, ","); garde = ""; ote = 0
            for (i = 1; i <= n; i++) {
                t = p[i]; gsub(/^[ \t]+|[ \t]+$/, "", t)
                if (t == q) { ote = 1; continue }
                garde = garde (garde == "" ? "" : ", ") t
            }
            if (ote) { if (garde != "") print "**En attente :** " garde; next }
        }
        { print }
    ' "$2"
}

# 1. Ce qui est modifié : questions-ux.md seul, et seulement des réponses nouvelles.
modifs="$(git status --porcelain -uno)"
if [ -z "$modifs" ]; then
    echo "porter-reponses : copie de travail propre, rien à porter"
    exit 0
fi
autres="$(printf '%s\n' "$modifs" | cut -c4- | grep -vxF "$QU")"
if [ -n "$autres" ] || [ ! -f "$QU" ]; then
    echouer "le clone $clone est modifié ailleurs que sous « Réponse : » de $QU ; rien n'est envoyé :
$(printf '%s\n' "${autres:-$QU supprimé}" | sed 's/^/  /')"
fi
git show "HEAD:$QU" > "$tmp/avant" 2>/dev/null || echouer "$QU absent de HEAD"
blocs squelette "" "$tmp/avant" > "$tmp/squelette-avant"
blocs squelette "" "$QU" > "$tmp/squelette-apres"
if ! cmp -s "$tmp/squelette-avant" "$tmp/squelette-apres"; then
    echouer "$QU est modifié hors des blocs « Réponse : » ; rien n'est envoyé :
$(diff "$tmp/squelette-avant" "$tmp/squelette-apres" | head -n 10 | sed 's/^/  /')"
fi
reponses=()
for q in $(blocs liste "" "$QU"); do
    a="$(blocs corps "$q" "$tmp/avant")"; b="$(blocs corps "$q" "$QU")"
    [ "$a" = "$b" ] && continue
    vide "$a" || echouer "la réponse à $q est déjà écrite sur main et a été réécrite dans le clone ; rien n'est envoyé (une correction passe par une demande de fusion)"
    vide "$b" && continue
    reponses+=("$q")
done
if [ "${#reponses[@]}" -eq 0 ]; then
    echouer "$QU est modifié sans réponse nouvelle (espaces seuls ?) ; rien n'est envoyé"
fi

# 2. Une branche et une demande de fusion par réponse.
git fetch -q origin main || echouer "git fetch origin main impossible"
git worktree prune
for q in "${reponses[@]}"; do
    n="${q#Q-UX-}"; br="reponse/q-ux-$n"
    blocs corps "$q" "$QU" > "$tmp/neuf-$n"
    git show "origin/main:$QU" > "$tmp/main-$n" 2>/dev/null || echouer "$QU absent d'origin/main"
    sur_main="$(blocs corps "$q" "$tmp/main-$n")"
    if [ "$sur_main" = "$(cat "$tmp/neuf-$n")" ]; then
        echo "porter-reponses : $q est déjà sur main"
        continue
    fi
    vide "$sur_main" || echouer "$q a déjà une autre réponse sur origin/main ; rien n'est envoyé"

    if git ls-remote --exit-code --heads origin "$br" > /dev/null 2>&1; then
        echo "porter-reponses : $br est déjà sur origin (passe coupée), j'attends sa fusion"
    else
        git show-ref -q --verify "refs/heads/$br" && git branch -q -D "$br"
        arbre="$tmp/q-ux-$n"
        git worktree add -q -b "$br" "$arbre" origin/main > /dev/null 2>&1 || echouer "worktree $br impossible"
        blocs remplacer "$q" "$arbre/$QU" "$tmp/neuf-$n" > "$tmp/qu-$n" && mv "$tmp/qu-$n" "$arbre/$QU"
        if [ -f "$arbre/$TA" ]; then
            retirer "$q" "$arbre/$TA" > "$tmp/ta-$n" && mv "$tmp/ta-$n" "$arbre/$TA"
        fi
        git -C "$arbre" add -- "$QU" "$TA" 2>/dev/null || git -C "$arbre" add -- "$QU"
        git -C "$arbre" commit -q -m "Réponse à $q" -m "Réponse de Léon écrite dans le clone principal, portée par scripts/plan/porter-reponses.sh (D-30) ; $q retiré des lignes « En attente » de tasks.md." \
            -m "Agent: aucun (réponse de Léon, scripts/plan/porter-reponses.sh)
Session: sans objet" || echouer "commit de $br impossible"
        git worktree remove --force "$arbre"
        # L'envoi part du clone principal : le crochet pre-push y trouve son projet DDEV.
        git push -q origin "$br" || echouer "envoi de $br refusé (crochet pre-push ?) ; la réponse reste dans le clone"
        "$GH" pr create --base main --head "$br" --title "Réponse à $q" \
            --body "Réponse de Léon à $q, écrite dans le clone principal et portée par \`scripts/plan/porter-reponses.sh\` (D-30). $q est retiré des lignes \`**En attente :**\` de \`docs/plan/tasks.md\`." \
            > /dev/null || echouer "gh pr create a échoué pour $br"
    fi
    "$GH" pr merge "$br" --auto --squash > /dev/null || echouer "gh pr merge --auto a échoué pour $br"

    # Les contrôles : « no checks reported » juste après l'envoi n'est pas un rouge.
    essais=0
    while :; do
        sortie="$("$GH" pr checks "$br" --watch --fail-fast 2>&1)" && break
        essais=$((essais + 1))
        if printf '%s' "$sortie" | grep -qi 'no checks reported' && [ "$essais" -lt 10 ]; then sleep "$PAUSE"; continue; fi
        echouer "contrôles rouges sur $br ; la réponse reste dans le clone :
$(printf '%s\n' "$sortie" | tail -n 10 | sed 's/^/  /')"
    done
    fin=$((SECONDS + ATTENTE))
    until [ "$("$GH" pr view "$br" --json state -q .state 2>/dev/null)" = MERGED ]; do
        [ "$SECONDS" -lt "$fin" ] || echouer "$br n'est pas fusionnée après ${ATTENTE} s ; la réponse reste dans le clone"
        sleep "$PAUSE"
    done
    git fetch -q origin main || echouer "git fetch origin main impossible après la fusion de $br"
    git show "origin/main:$QU" > "$tmp/main-$n"
    [ "$(blocs corps "$q" "$tmp/main-$n")" = "$(cat "$tmp/neuf-$n")" ] \
        || echouer "$br est fusionnée mais main ne porte pas la réponse à $q ; la réponse reste dans le clone"
    git show-ref -q --verify "refs/heads/$br" && git branch -q -D "$br"
    echo "porter-reponses : $q portée sur main"
done

# 3. main porte chaque réponse : la copie de travail redevient propre.
git checkout -q HEAD -- "$QU"
echo "CONFIRME porter-reponses — ${#reponses[@]} réponse(s) sur main : ${reponses[*]}"
