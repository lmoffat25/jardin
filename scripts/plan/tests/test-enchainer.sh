#!/usr/bin/env bash
# Test de scripts/plan/enchainer.sh (V18 à V21), repris des dix cas de
# test-buildfeature-run.sh (Treize Pourcent), plus ceux du jardin : sprint en
# attente sauté, tous en attente → 5 et prévenance, pull en échec → 1, plafond
# par défaut de 3 passes, ARRET: après un sprint fini.
#
# Un faux claude (ENCHAINER_CLAUDE) tient lieu de session, un clone jetable de
# clone principal (ENCHAINER_RACINE), avec son dépôt nu comme origin : chaque
# fausse passe coche le premier sprint qui n'attend rien, commite sur main, et
# ajoute une ligne à handoffs.jsonl, comme le skill construire-sprint.
set -uo pipefail

RACINE_DEPOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SCRIPT="$RACINE_DEPOT/scripts/plan/enchainer.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fautes=0
ok() { echo "  ok : $1"; }
ko() { echo "  FAUTE : $1"; fautes=$((fautes + 1)); }

# Coche le premier sprint sans « En attente » qui a une case vide.
cat > "$T/claude-construit" <<'SH'
#!/usr/bin/env bash
p=docs/plan/tasks.md
echo "fausse passe : $*"
cible="$(awk '/^## /{s = ($0 ~ /^## Sprint /) ? $0 : ""; att = 0}
  s != "" && /^\*\*En attente :\*\*/{att = 1}
  s != "" && !att && /^- \[ \]/{print s; exit}' "$p")"
awk -v c="$cible" '/^## /{on = ($0 == c)} on{sub(/^- \[ \]/, "- [x]")} {print}' "$p" > "$p.tmp" && mv "$p.tmp" "$p"
git commit -q -am "coche $cible"
echo '{"sprint":"x"}' >> docs/plan/handoffs.jsonl
SH
cat > "$T/claude-arrete" <<'SH'
#!/usr/bin/env bash
echo "ARRET: acte qui engage Léon sur le sprint 1"
SH
cat > "$T/claude-finit-et-arrete" <<'SH'
#!/usr/bin/env bash
"$(dirname "$0")/claude-construit" "$@" > /dev/null
echo "ARRET: lancez vous-même la première passe réelle"
SH
# Bilan à la manière du skill : avertissements de la CLI, prose, blocs titrés.
cat > "$T/claude-bilan" <<'SH'
#!/usr/bin/env bash
"$(dirname "$0")/claude-construit" "$@" > /dev/null
n="$(grep -c . docs/plan/handoffs.jsonl)"
cat <<EOF
Permission allow rule (.claude/settings.json): Bash(x *:*) mixes * with the trailing :* prefix syntax.
Sprint $n fini : commit abc$n, CI verte.

- **Recette :** vue, prose de la passe $n hors bloc.

**Points faibles :**
- point-faible-de-la-passe-$n : la mutation n'a vu que quatre mutants.
  Coût : vingt minutes.

**Décisions prises — à contester si fausses :**
- decision-de-la-passe-$n : la légende prend une largeur facultative.
- seconde-decision-de-la-passe-$n.

**En attente :**
- Q-UX-$n posée dans questions-ux.md.

Suivant : sprint $((n + 1)).
EOF
SH
cat > "$T/claude-plante" <<'SH'
#!/usr/bin/env bash
echo "boum" >&2; exit 7
SH
cat > "$T/claude-coupe-une-fois" <<'SH'
#!/usr/bin/env bash
echo "plafond=${CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS:-absent}"
if [ ! -f .coupe ]; then touch .coupe; echo "Background tasks still running after 600s; terminating."; exit 0; fi
exec "$(dirname "$0")/claude-construit" "$@"
SH
cat > "$T/claude-coupe-toujours" <<'SH'
#!/usr/bin/env bash
echo "Background tasks still running after 600s; terminating."
SH
cat > "$T/prevenir" <<'SH'
#!/usr/bin/env bash
echo "$1" >> "$PREVENU"
SH
chmod +x "$T"/claude-* "$T/prevenir"

# preparer <nom> <contenu de tasks.md> → clone principal $T/<nom>, origin $T/<nom>.git
preparer() {
  local r="$T/$1"
  git init -q --bare -b main "$r.git"
  git init -q -b main "$r"
  mkdir -p "$r/docs/plan"
  printf '%s\n' "$2" > "$r/docs/plan/tasks.md"
  printf 'docs/plan/handoffs.jsonl\ndocs/plan/run.log\n.coupe\n' > "$r/.gitignore"
  git -C "$r" add -A
  git -C "$r" -c user.name=t -c user.email=t@t commit -q -m initial
  git -C "$r" remote add origin "$r.git"
  git -C "$r" push -q -u origin main
  git -C "$r" config user.name t; git -C "$r" config user.email t@t
}
# lancer <nom> <faux claude> [VAR=valeur…] → $sortie, $code
lancer() {
  local r="$T/$1" f="$T/$2"; shift 2
  sortie="$(cd "$RACINE_DEPOT" && env ENCHAINER_RACINE="$r" ENCHAINER_CLAUDE="$f" "$@" bash "$SCRIPT" 2>&1)"
  code=$?
}
code_attendu() { if [ "$code" = "$2" ]; then ok "$1"; else ko "$1 (code $code, attendu $2)"; printf '%s\n' "$sortie" | sed 's/^/      /'; fi; }
sortie_contient() { if printf '%s\n' "$sortie" | grep -qE "$2"; then ok "$1"; else ko "$1 (pas de /$2/)"; printf '%s\n' "$sortie" | sed 's/^/      /'; fi; }
passes() { grep -c '===== passe' "$T/$1/docs/plan/run.log" 2>/dev/null || echo 0; }
lignes() { grep -c . "$T/$1/docs/plan/handoffs.jsonl" 2>/dev/null || echo 0; }

if [ ! -f "$SCRIPT" ]; then echo "ECHOUE test de l'enchaînement : script absent ($SCRIPT)"; exit 1; fi

DEUX='# Plan

## Portes valables pour tous les sprints

- [ ] Cette case hors sprint reste vide pour toujours

## Sprint 1 — Premier

- [ ] un
- [ ] deux

## Sprint 2 — Second

- [ ] trois
'

echo "1. va au bout du plan, une passe par sprint (V18)"
preparer boucle "$DEUX"; lancer boucle claude-construit
code_attendu "plan fini → 0" 0
sortie_contient "annonce la fin après deux passes" "CONFIRME .*\(2 passe"
[ "$(lignes boucle)" = 2 ] && ok "une ligne de handoff par sprint" || ko "une ligne de handoff par sprint"
grep -q '^- \[ \] Cette case hors sprint' "$T/boucle/docs/plan/tasks.md" && ok "case hors sprint laissée" || ko "case hors sprint laissée"

echo "2. une passe qui s'arrête sur ARRET: arrête la boucle (V19)"
preparer arret "$DEUX"; lancer arret claude-arrete
code_attendu "ARRET: sans sprint fini → 3" 3
sortie_contient "montre la fin de la passe" "acte qui engage Léon"
[ "$(passes arret)" = 1 ] && ok "pas de deuxième passe après un ARRET:" || ko "pas de deuxième passe après un ARRET:"

echo "3. une passe en erreur rend 1 (V21)"
preparer plante "$DEUX"; lancer plante claude-plante
code_attendu "passe en erreur → 1" 1

echo "4. MAX_SPRINTS atteint rend 4 (V21)"
preparer plafond "$DEUX"; lancer plafond claude-construit MAX_SPRINTS=1
code_attendu "MAX_SPRINTS=1 avec des sprints restants → 4" 4

echo "5. un plan fini ne lance rien"
preparer fini '## Sprint 1 — Fait

- [x] un
'
lancer fini claude-arrete
code_attendu "plan déjà fini → 0" 0
[ ! -f "$T/fini/docs/plan/run.log" ] && ok "aucune session pour un plan fini" || ko "aucune session pour un plan fini"

echo "6. rien à lire n'est jamais un succès (V21)"
mkdir -p "$T/vide"; lancer vide claude-construit
code_attendu "pas de tasks.md → 2" 2
preparer sanssprint '# Plan

- [ ] une case sans section Sprint
'
lancer sanssprint claude-construit
code_attendu "pas de section ## Sprint → 2" 2

echo "7. une passe coupée sans ARRET: est relancée une fois (V19)"
preparer coupe "$DEUX"; lancer coupe claude-coupe-une-fois
code_attendu "passe coupée relancée, plan fini → 0" 0
sortie_contient "annonce la reprise" "REPRISE"
grep -q 'plafond=0' "$T/coupe/docs/plan/run.log" && ok "la session attend ses tâches de fond" || ko "la session attend ses tâches de fond"

echo "8. coupée deux fois de suite : la boucle s'arrête (V19)"
preparer coupe2 "$DEUX"; lancer coupe2 claude-coupe-toujours
code_attendu "deuxième coupure → 3" 3
[ "$(passes coupe2)" = 2 ] && ok "une seule reprise" || ko "une seule reprise ($(passes coupe2) passes)"

echo "9. après chaque passe, le bilan de cette passe seule"
preparer bilan "$DEUX"; lancer bilan claude-bilan
code_attendu "bilans, plan fini → 0" 0
sortie_contient "bloc Points faibles" "^ *Points faibles :"
sortie_contient "bloc Décisions prises" "^ *Décisions prises — à contester si fausses :"
sortie_contient "bloc En attente" "^ *En attente :"
sortie_contient "éléments du bloc" "decision-de-la-passe-1 : la légende"
sortie_contient "ligne de suite d'un élément" "Coût : vingt minutes"
sortie_contient "ligne Suivant" "Suivant : sprint 2"
sortie_contient "blocs de la deuxième passe" "point-faible-de-la-passe-2"
[ "$(printf '%s\n' "$sortie" | grep -c 'decision-de-la-passe-1 :')" = 1 ] && ok "chaque bilan ne couvre que sa passe" || ko "chaque bilan ne couvre que sa passe"
printf '%s\n' "$sortie" | grep -q 'Permission allow rule' && ko "avertissements de la CLI hors du terminal" || ok "avertissements de la CLI hors du terminal"
printf '%s\n' "$sortie" | grep -q 'hors bloc' && ko "prose hors des blocs hors du terminal" || ok "prose hors des blocs hors du terminal"

echo "10. un bilan sans bloc le dit en une ligne, sans faire échouer"
preparer sansbloc "$DEUX"; lancer sansbloc claude-construit
code_attendu "bilan sans bloc → 0" 0
[ "$(printf '%s\n' "$sortie" | grep -c 'aucun bloc Décisions prises')" = 2 ] && ok "une ligne par passe" || ko "une ligne par passe"

ATTENTE='## Sprint 1 — Attend

**En attente :** Q-UX-3

- [ ] un

## Sprint 2 — Libre

- [ ] deux
'
echo "11. un sprint en attente est sauté (V20)"
preparer attente "$ATTENTE"; lancer attente claude-construit PREVENU="$T/prevenu-11" ENCHAINER_PREVENIR="$T/prevenir"
code_attendu "il ne reste que des sprints en attente → 5" 5
grep -q '^- \[x\] deux' "$T/attente/docs/plan/tasks.md" && grep -q '^- \[ \] un' "$T/attente/docs/plan/tasks.md" \
  && ok "le sprint libre est construit, celui en attente non" || ko "le sprint libre est construit, celui en attente non"

echo "12. tous en attente : 5, et la prévenance est appelée (V20)"
preparer tousattente '## Sprint 1 — Attend

**En attente :** Q-UX-3

- [ ] un
'
lancer tousattente claude-arrete PREVENU="$T/prevenu-12" ENCHAINER_PREVENIR="$T/prevenir"
code_attendu "tous en attente → 5" 5
[ ! -f "$T/tousattente/docs/plan/run.log" ] && ok "aucune session lancée" || ko "aucune session lancée"
grep -q 'Q-UX' "$T/prevenu-12" 2>/dev/null && ok "prévenance appelée avec le motif" || ko "prévenance appelée avec le motif"
lancer tousattente claude-arrete
code_attendu "sans ENCHAINER_PREVENIR, toujours 5" 5

echo "13. pull --ff-only en échec rend 1 (V19)"
preparer pull "$DEUX"; git -C "$T/pull" remote remove origin
lancer pull claude-construit
code_attendu "pull impossible → 1" 1
[ ! -f "$T/pull/docs/plan/run.log" ] && ok "aucune session lancée" || ko "aucune session lancée"

echo "14. sans MAX_SPRINTS, 3 passes au plus (D-32)"
preparer quatre '## Sprint 1 — a

- [ ] a

## Sprint 2 — b

- [ ] b

## Sprint 3 — c

- [ ] c

## Sprint 4 — d

- [ ] d
'
lancer quatre claude-construit
code_attendu "plafond par défaut → 4" 4
[ "$(passes quatre)" = 3 ] && ok "trois passes" || ko "trois passes ($(passes quatre))"

echo "15. ARRET: après un sprint fini arrête aussi (V19, 0f.3)"
preparer finarret "$DEUX"; lancer finarret claude-finit-et-arrete PREVENU="$T/prevenu-15" ENCHAINER_PREVENIR="$T/prevenir"
code_attendu "sprint fini puis ARRET: → 3" 3
[ "$(lignes finarret)" = 1 ] && ok "le sprint fini est compté" || ko "le sprint fini est compté"
grep -q 'ARRET' "$T/prevenu-15" 2>/dev/null && ok "prévenance appelée sur 3" || ko "prévenance appelée sur 3"

if [ "$fautes" -gt 0 ]; then echo "ECHOUE test de l'enchaînement : $fautes faute(s)"; exit 1; fi
echo "CONFIRME test de l'enchaînement : tous les cas passent"
