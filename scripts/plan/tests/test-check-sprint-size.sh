#!/usr/bin/env bash
# Test de scripts/plan/check-sprint-size.sh (V22, D-29), repris de
# test-check-sprint-size.sh (Treize Pourcent) : le plan de ce dépôt passe ; un
# sprint au-delà du budget, un commentaire `taille` faux, un chemin compté cité
# dans une tâche et absent des listes rendent 1 ; rien à lire n'est jamais un
# succès.
set -uo pipefail

RACINE_DEPOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SCRIPT="$RACINE_DEPOT/scripts/plan/check-sprint-size.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fautes=0
ok() { echo "  ok : $1"; }
ko() { echo "  FAUTE : $1"; fautes=$((fautes + 1)); }

# plan <nom> <tasks.md> <lignes de la carte> → $T/<nom>/{tasks,validation-contract}.md
plan() {
  mkdir -p "$T/$1"
  printf '%s\n' "$2" > "$T/$1/tasks.md"
  printf '# Contrat\n\n**V1 — x**\n\n## Carte de couverture\n\n| Sprint | Assertions |\n|---|---|\n%s\n\nTotal : rien.\n' "$3" > "$T/$1/validation-contract.md"
}
# lancer <dossier> → $sortie, $code
lancer() { sortie="$(bash "$SCRIPT" "$1" 2>&1)"; code=$?; }
code_attendu() { if [ "$code" = "$2" ]; then ok "$1"; else ko "$1 (code $code, attendu $2)"; printf '%s\n' "$sortie" | sed 's/^/      /'; fi; }
sortie_contient() { if printf '%s\n' "$sortie" | grep -qE "$2"; then ok "$1"; else ko "$1 (pas de /$2/)"; printf '%s\n' "$sortie" | sed 's/^/      /'; fi; }

if [ ! -f "$SCRIPT" ]; then echo "ECHOUE test de la taille des sprints : script absent ($SCRIPT)"; exit 1; fi

# sprint <id> <taille A F T> <fichiers> <classes> [corps]
sprint() {
  printf '## Sprint %s — Témoin\n<!-- taille : %s -->\n\n**Objectif :** x.\n**Assertions :** voir la carte\n**Fichiers :** %s\n**Classes de test :** %s\n**Fin :** x.\n\n### %s.1 Tâches\n\n%s\n\n---\n' "$1" "$2" "$3" "$4" "$1" "${5:-- [ ] rien}"
}
UN="$(sprint 1 '1 assertion, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '- [ ] Implémenter `src/A.php`.')"

echo "1. le plan de ce dépôt passe, une ligne par sprint (V22)"
lancer "$RACINE_DEPOT/docs/plan"
code_attendu "docs/plan → 0" 0
sortie_contient "ligne du sprint 0a" "^0a : 2 assertions, 4 fichiers, 1 classes de test$"
sortie_contient "ligne du sprint 0t (listes « aucun »)" "^0t : 2 assertions, 0 fichiers, 0 classes de test$"
n_sprints="$(grep -c '^## Sprint ' "$RACINE_DEPOT/docs/plan/tasks.md")"
[ "$(printf '%s\n' "$sortie" | grep -cE '^[0-9a-z]+ : [0-9]+ assertions')" = "$n_sprints" ] \
  && ok "une ligne pour chacun des $n_sprints sprints" || ko "une ligne pour chacun des $n_sprints sprints"

echo "2. un sprint fabriqué à six assertions rend 1 (V22)"
plan six "# Plan

$UN
$(sprint 2 '6 assertions, 1 fichier, 1 classe de test' '`src/B.php`' '`tests/Routes/BTest.php`')

## Revue des coûts" '| 1 | V1 |
| 2 | V2, V3, V4, V5, V6, V7 |'
lancer "$T/six"
code_attendu "six assertions → 1" 1
sortie_contient "le sprint fautif est nommé" "^2 : .*hors budget"
printf '%s\n' "$sortie" | grep -q '^1 : .*hors budget' && ko "le sprint sain n'est pas nommé" || ok "le sprint sain n'est pas nommé"

echo "3. une dérogation motivée laisse passer, vide non"
plan derog "$(sprint 1 '6 assertions, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '<!-- derogation-taille: bascule de schéma indivisible -->
- [ ] Implémenter `src/A.php`.')" '| 1 | V1, V2, V3, V4, V5, V6 |'
lancer "$T/derog"
code_attendu "dérogation motivée → 0" 0
sortie_contient "la dérogation est dite" "^1 : .*dérogation"
plan derogvide "$(sprint 1 '6 assertions, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '<!-- derogation-taille: -->')" '| 1 | V1, V2, V3, V4, V5, V6 |'
lancer "$T/derogvide"
code_attendu "dérogation sans raison → 1" 1

echo "4. neuf fichiers ou cinq classes de test rendent 1"
plan neuf "$(sprint 1 '1 assertion, 9 fichiers, 1 classe de test' '`src/A.php`, `src/B.php`, `src/C.php`, `src/D.php`, `src/E.php`, `src/F.php`, `src/G.php`, `src/H.php`, `Makefile`' '`tests/Routes/ATest.php`')" '| 1 | V1 |'
lancer "$T/neuf"
code_attendu "neuf fichiers → 1" 1
plan cinq "$(sprint 1 '1 assertion, 1 fichier, 5 classes de test' '`src/A.php`' '`tests/Routes/ATest.php`, `tests/Routes/BTest.php`, `tests/Routes/CTest.php`, `tests/Routes/DTest.php`, `scripts/plan/tests/test-e.sh`')" '| 1 | V1 |'
lancer "$T/cinq"
code_attendu "cinq classes de test → 1" 1

echo "5. un commentaire taille qui ne dit pas le compte des listes rend 1 (V22)"
plan taille "$(sprint 1 '1 assertion, 2 fichiers, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '- [ ] Implémenter `src/A.php`.')" '| 1 | V1 |'
lancer "$T/taille"
code_attendu "taille fausse → 1" 1
sortie_contient "l'écart est dit" "^1 : .*taille.*1 assertion, 2 fichiers"
plan sanstaille "## Sprint 1 — Sans commentaire

**Fichiers :** \`src/A.php\`
**Classes de test :** aucun
" '| 1 | V1 |'
lancer "$T/sanstaille"
code_attendu "commentaire taille absent → 1" 1

echo "6. un chemin compté cité dans une tâche et absent des listes rend 1 (V22)"
plan cite "$(sprint 1 '1 assertion, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '- [ ] Implémenter `src/A.php` et `src/Oublie/B.php`.
- [x] Modifier `Makefile`, `config/x.php` : cible.')" '| 1 | V1 |'
lancer "$T/cite"
code_attendu "src/… cité, non listé → 1" 1
sortie_contient "le chemin src est nommé" "^1 : .*src/Oublie/B\.php"
sortie_contient "Makefile nommé" "^1 : .*Makefile"
sortie_contient "config nommé" "^1 : .*config/x\.php"
plan citetest "$(sprint 1 '1 assertion, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '- [ ] `tests/Routes/OublieTest.php` : un cas.')" '| 1 | V1 |'
lancer "$T/citetest"
code_attendu "classe de test citée, non listée → 1" 1
sortie_contient "la classe est nommée" "^1 : .*tests/Routes/OublieTest\.php"

echo "7. ce qui ne compte pas ne fait rien échouer (D-29)"
plan horscompte "$(sprint 1 '1 assertion, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '- [ ] Implémenter `src/A.php` (comme `src/*/routes.php`, dans `src/<Module>/`, dossier `scripts/depot/`) ; écrire `docs/plan/decisions-agents.md`, `AGENTS.md`, `database/factories/AFactory.php`, `tests/Pest.php`, `tests/Support/Aide.php`, `composer.lock`, `package-lock.json`, `resources/js/types/generes.d.ts`, `bibliotheque/fruitiers.yaml` ; lancer `make verifier`, `php artisan test`, `verifier.yml`, `pint.json`, `inertia.testing.ensure_pages_exist`.')

**Lecture :** \`src/Lu/Ailleurs.php\` hors des tâches." '| 1 | V1 |'
lancer "$T/horscompte"
code_attendu "catégories exclues, motifs, dossiers, commandes → 0" 0
plan horscomptemarque "$(sprint 1 '1 assertion, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '<!-- hors-compte: `scripts/depot/appliquer-ruleset.sh` lancé, pas modifié -->
- [ ] Implémenter `src/A.php`, puis lancer `scripts/depot/appliquer-ruleset.sh`.')" '| 1 | V1 |'
lancer "$T/horscomptemarque"
code_attendu "chemin marqué hors-compte avec sa raison → 0" 0
plan horscomptevide "$(sprint 1 '1 assertion, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`' '<!-- hors-compte: `scripts/x.sh` -->
- [ ] Implémenter `src/A.php`, puis lancer `scripts/x.sh`.')" '| 1 | V1 |'
lancer "$T/horscomptevide"
code_attendu "hors-compte sans raison → 1" 1

echo "8. les assertions viennent de la carte ; une barrée ne compte pas"
plan barree "$(sprint 1 '2 assertions, 1 fichier, 1 classe de test' '`src/A.php`' '`tests/Routes/ATest.php`')
$(sprint 1b '1 assertion, 1 fichier, 1 classe de test' '`src/B.php`' '`tests/Routes/BTest.php`')" '| 1 | V1, ~~V2~~, V3 |
| 1b | V12 |'
lancer "$T/barree"
code_attendu "V2 barrée, 1b lit sa propre ligne → 0" 0
sortie_contient "deux assertions pour 1" "^1 : 2 assertions"
sortie_contient "une assertion pour 1b" "^1b : 1 assertions"
plan horscarte "$UN" '| 2 | V1 |'
lancer "$T/horscarte"
code_attendu "sprint absent de la carte → 1" 1

echo "9. rien à lire n'est jamais un succès"
lancer "$T/absent"
code_attendu "dossier absent → 2" 2
mkdir -p "$T/sanssprint"; printf '# Plan\n\n- [ ] une case\n' > "$T/sanssprint/tasks.md"
lancer "$T/sanssprint"
code_attendu "aucune section ## Sprint → 2" 2
mkdir -p "$T/sanscarte"; printf '%s\n' "$UN" > "$T/sanscarte/tasks.md"
lancer "$T/sanscarte"
code_attendu "validation-contract.md absent → 2" 2

if [ "$fautes" -gt 0 ]; then echo "ECHOUE test de la taille des sprints : $fautes faute(s)"; exit 1; fi
echo "CONFIRME test de la taille des sprints : tous les cas passent"
