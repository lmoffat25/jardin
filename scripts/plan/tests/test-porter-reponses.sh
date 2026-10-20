#!/usr/bin/env bash
# Test de scripts/plan/porter-reponses.sh (V127, D-30) : une réponse écrite par
# Léon dans le clone principal arrive sur main par une demande de fusion, le
# marqueur « En attente » tombe, la copie de travail redevient propre ; toute
# autre modification rend 1 sans rien envoyer.
#
# Un dépôt nu local tient lieu d'origin ; un faux gh (PORTER_GH) note ses appels
# et, à `pr merge`, fusionne la branche dans main par un squash, comme GitHub.
set -uo pipefail

RACINE_DEPOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SCRIPT="$RACINE_DEPOT/scripts/plan/porter-reponses.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fautes=0
ok() { echo "  ok : $1"; }
ko() { echo "  FAUTE : $1"; fautes=$((fautes + 1)); }

cat > "$T/gh" <<'SH'
#!/usr/bin/env bash
echo "$*" >> "$GH_JOURNAL"
case "$1 $2" in
  "pr create") echo "https://example.invalid/pull/1" ;;
  "pr merge")
    [ "${GH_FUSION:-oui}" = oui ] || exit 0
    b="${3:-}"; o="$(git remote get-url origin)"; d="$(mktemp -d)"
    git clone -q "$o" "$d/c" && cd "$d/c" && git fetch -q origin "$b" \
      && git merge -q --squash FETCH_HEAD > /dev/null && git -c user.name=gh -c user.email=gh@gh commit -q -m "fusion de $b" \
      && git push -q origin main; c=$?; rm -rf "$d"; exit $c ;;
  "pr checks") [ "${GH_CHECKS:-vert}" = vert ] || { echo "verifier / tests  fail"; exit 1; } ;;
  "pr view") echo "${GH_ETAT:-MERGED}" ;;
esac
SH
chmod +x "$T/gh"

QUESTIONS='# Questions pour Léon

Préambule.

## Q-UX-2 — Une ligne fautive

**Question :** que faire ?
**Options :**
1. **Recommandé —** importer les bonnes.

**Réponse :**

## Q-UX-3 — Les noms

**Question :** quels noms ?
**Réponse :**

## Q-UX-12 — Mot de passe

**Question :** un lien ?
**Réponse :** oui, un lien par mail (Léon, 18/10/2026)
'
TACHES='# Plan

## Sprint 7d — Lot

**Objectif :** x.
**En attente :** Q-UX-2, Q-UX-3

- [ ] un

## Sprint 7e — Zone

**En attente :** Q-UX-2

- [ ] deux

## Sprint 9 — Mot de passe

**En attente :** Q-UX-12

- [ ] trois

## Sprint 9c — Autre

**En attente :** Q-UX-21

- [ ] cinq

## Sprint 2f — Climat

**En attente :** rien — Q24 tranchée

- [ ] quatre
'

# preparer <nom> → clone principal $T/<nom>, origin $T/<nom>.git
preparer() {
  local r="$T/$1"
  git init -q --bare -b main "$r.git"
  git init -q -b main "$r"
  mkdir -p "$r/docs/plan"
  printf '%s' "$QUESTIONS" > "$r/docs/plan/questions-ux.md"
  printf '%s' "$TACHES" > "$r/docs/plan/tasks.md"
  printf 'docs/plan/handoffs.jsonl\n' > "$r/.gitignore"
  git -C "$r" add -A
  git -C "$r" -c user.name=t -c user.email=t@t commit -q -m initial
  git -C "$r" remote add origin "$r.git"
  git -C "$r" push -q -u origin main
  git -C "$r" config user.name t; git -C "$r" config user.email t@t
  : > "$T/$1.gh"
}
# repondre <nom> <n> <texte> : écrit la réponse sous « **Réponse :** » de Q-UX-n, comme Léon.
repondre() {
  local f="$T/$1/docs/plan/questions-ux.md"
  awk -v n="$2" -v r="$3" '/^## Q-UX-/{q = $2} q == "Q-UX-" n && /^\*\*Réponse :\*\*$/{$0 = $0 " " r} {print}' "$f" > "$f.tmp" && mv "$f.tmp" "$f"
}
# lancer <nom> [VAR=valeur…] → $sortie, $code
lancer() {
  local r="$T/$1"; shift
  sortie="$(cd "$r" && env PORTER_GH="$T/gh" GH_JOURNAL="$T/$(basename "$r").gh" PORTER_PAUSE=0 "$@" bash "$SCRIPT" "$r" 2>&1)"
  code=$?
}
code_attendu() { if [ "$code" = "$2" ]; then ok "$1"; else ko "$1 (code $code, attendu $2)"; printf '%s\n' "$sortie" | sed 's/^/      /'; fi; }
vrai() { if eval "$2"; then ok "$1"; else ko "$1"; printf '%s\n' "$sortie" | sed 's/^/      /'; fi; }
sur_main() { git -C "$T/$1.git" show "main:docs/plan/$2"; }
rien_envoye() {
  vrai "aucun appel à gh" "[ ! -s '$T/$1.gh' ]"
  vrai "aucune branche envoyée" "[ \"\$(git -C '$T/$1.git' branch | wc -l | tr -d ' ')\" = 1 ]"
}

if [ ! -f "$SCRIPT" ]; then echo "ECHOUE test du port des réponses : script absent ($SCRIPT)"; exit 1; fi

echo "1. rien à porter : 0, sans appel à gh"
preparer rien; lancer rien
code_attendu "copie de travail propre → 0" 0
rien_envoye rien

echo "2. une réponse écrite dans le clone arrive sur main (V127)"
preparer une; repondre une 2 "importer les bonnes lignes (Léon, 20/10/2026)"
lancer une
code_attendu "réponse portée → 0" 0
vrai "une branche reponse/q-ux-2" "git -C '$T/une.git' rev-parse -q --verify refs/heads/reponse/q-ux-2 > /dev/null"
vrai "une demande de fusion « Réponse à Q-UX-2 »" "grep -q '^pr create.*Réponse à Q-UX-2' '$T/une.gh'"
vrai "en fusion automatique par squash" "grep -q '^pr merge reponse/q-ux-2 .*--auto' '$T/une.gh' && grep -q '^pr merge .*--squash' '$T/une.gh'"
vrai "attente des contrôles" "grep -q '^pr checks reponse/q-ux-2 .*--watch' '$T/une.gh'"
vrai "main porte la réponse" "sur_main une questions-ux.md | grep -q '^\*\*Réponse :\*\* importer les bonnes lignes (Léon, 20/10/2026)$'"
vrai "main : Q-UX-3 garde sa réponse vide" "sur_main une questions-ux.md | grep -c '^\*\*Réponse :\*\*$' | grep -qx 1"
vrai "main : la ligne du 7e a disparu" "! sur_main une tasks.md | grep -qx '\*\*En attente :\*\* Q-UX-2'"
vrai "main : le 7d n'attend plus que Q-UX-3" "sur_main une tasks.md | grep -qx '\*\*En attente :\*\* Q-UX-3'"
vrai "main : Q-UX-12 n'est pas pris pour Q-UX-2" "sur_main une tasks.md | grep -qx '\*\*En attente :\*\* Q-UX-12'"
vrai "main : Q-UX-21 n'est pas pris pour Q-UX-2" "sur_main une tasks.md | grep -qx '\*\*En attente :\*\* Q-UX-21'"
vrai "main : une ligne sans Q-UX reste telle quelle" "sur_main une tasks.md | grep -qx '\*\*En attente :\*\* rien — Q24 tranchée'"
vrai "main : rien d'autre ne change" "[ \"\$(sur_main une tasks.md | wc -l)\" -eq \$(( \$(printf '%s' \"\$TACHES\" | wc -l) - 1 )) ]"
vrai "la copie de travail est propre" "[ -z \"\$(git -C '$T/une' status --porcelain)\" ]"
vrai "aucun worktree ne reste" "[ \"\$(git -C '$T/une' worktree list | wc -l | tr -d ' ')\" = 1 ]"
vrai "le clone est resté sur main" "[ \"\$(git -C '$T/une' symbolic-ref --short HEAD)\" = main ]"
vrai "rien n'est envoyé directement sur main : la fusion vient de gh" "git -C '$T/une.git' log --format=%an main | head -1 | grep -qx gh"
vrai "git pull --ff-only passe" "git -C '$T/une' pull -q --ff-only"
vrai "le clone a la réponse après le pull" "grep -q 'importer les bonnes lignes' '$T/une/docs/plan/questions-ux.md'"

echo "3. deux réponses, sur plusieurs lignes : deux demandes de fusion"
preparer deux; repondre deux 2 "première ligne"
f="$T/deux/docs/plan/questions-ux.md"
awk '{print} /^\*\*Réponse :\*\* première ligne$/{print "seconde ligne, même réponse"}' "$f" > "$f.tmp" && mv "$f.tmp" "$f"
repondre deux 3 "des noms numérotés"
lancer deux
code_attendu "deux réponses → 0" 0
vrai "deux demandes de fusion" "[ \"\$(grep -c '^pr create' '$T/deux.gh')\" = 2 ]"
vrai "la seconde ligne de la réponse est portée" "sur_main deux questions-ux.md | grep -q '^seconde ligne, même réponse$'"
vrai "le 7d n'attend plus rien : sa ligne a disparu" "! sur_main deux tasks.md | grep -qE 'Q-UX-[23]([^0-9]|$)'"
vrai "la copie de travail est propre" "[ -z \"\$(git -C '$T/deux' status --porcelain)\" ]"

echo "4. une autre modification non commitée : 1, rien d'envoyé (V127)"
preparer autre; repondre autre 2 "oui"; echo "- [ ] ajout" >> "$T/autre/docs/plan/tasks.md"
lancer autre
code_attendu "tasks.md modifié → 1" 1
rien_envoye autre
vrai "la réponse de Léon est gardée" "grep -q '^\*\*Réponse :\*\* oui$' '$T/autre/docs/plan/questions-ux.md'"

echo "5. questions-ux.md modifié hors d'une réponse : 1, rien d'envoyé"
preparer horsrep; repondre horsrep 2 "oui"
sed -i.bak 's/^\*\*Question :\*\* quels noms ?$/**Question :** quels noms, vraiment ?/' "$T/horsrep/docs/plan/questions-ux.md"; rm -f "$T/horsrep/docs/plan/questions-ux.md.bak"
lancer horsrep
code_attendu "texte d'une question modifié → 1" 1
rien_envoye horsrep

echo "6. une réponse déjà sur main modifiée : 1, rien d'envoyé"
preparer deja
sed -i.bak 's/^\*\*Réponse :\*\* oui, un lien.*$/**Réponse :** non/' "$T/deja/docs/plan/questions-ux.md"; rm -f "$T/deja/docs/plan/questions-ux.md.bak"
lancer deja
code_attendu "réponse remplie sur main réécrite → 1" 1
vrai "le motif est dit" "printf '%s' \"\$sortie\" | grep -q 'déjà écrite sur main'"
rien_envoye deja

echo "7. une modification indexée compte aussi"
preparer index; repondre index 2 "oui"; git -C "$T/index" add docs/plan/questions-ux.md
lancer index
code_attendu "réponse indexée seule → 0" 0
vrai "la copie de travail est propre" "[ -z \"\$(git -C '$T/index' status --porcelain)\" ]"

echo "8. contrôles rouges : 1, la réponse reste dans le clone"
preparer rouge; repondre rouge 2 "oui"
lancer rouge GH_CHECKS=rouge GH_FUSION=non
code_attendu "CI rouge → 1" 1
vrai "la réponse de Léon est gardée" "grep -q '^\*\*Réponse :\*\* oui$' '$T/rouge/docs/plan/questions-ux.md'"
vrai "main n'a pas la réponse" "! sur_main rouge questions-ux.md | grep -q '^\*\*Réponse :\*\* oui$'"

echo "9. fusion qui n'arrive pas : 1 après l'attente, la réponse reste"
preparer lente; repondre lente 2 "oui"
lancer lente GH_FUSION=non GH_ETAT=OPEN PORTER_ATTENTE=0
code_attendu "jamais fusionnée → 1" 1
vrai "la réponse de Léon est gardée" "grep -q '^\*\*Réponse :\*\* oui$' '$T/lente/docs/plan/questions-ux.md'"

if [ "$fautes" -gt 0 ]; then echo "ECHOUE test du port des réponses : $fautes faute(s)"; exit 1; fi
echo "CONFIRME test du port des réponses : tous les cas passent"
