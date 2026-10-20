#!/usr/bin/env bash
#
# Compte la taille de chaque sprint du plan (P5, D-29), adapté de
# check-sprint-size.sh (Treize Pourcent).
#
#   bash scripts/plan/check-sprint-size.sh docs/plan          # ou …/tasks.md
#
# Pour chaque section `## Sprint <id>` de tasks.md, une ligne
# `<id> : A assertions, F fichiers, T classes de test` :
#   assertions  les `V\d+` de sa ligne dans `## Carte de couverture` de
#               validation-contract.md (à côté de tasks.md) ; une barrée ne compte pas
#   fichiers    les chemins entre accents graves de sa ligne `**Fichiers :**`
#   classes     ceux de sa ligne `**Classes de test :**` (`aucun` = 0)
#
# Code 1, et une ligne `<id> : …` qui dit pourquoi :
#   - au-delà de 5 assertions, 8 fichiers ou 4 classes de test, sans
#     `<!-- derogation-taille: <raison> -->` dans la section ;
#   - un commentaire `<!-- taille : … -->` absent, ou qui ne dit pas ces nombres ;
#   - un chemin d'une catégorie comptée (D-29), cité dans une tâche (`- [ ]`,
#     `- [x]` et ses lignes de suite) et absent des deux listes ; un nom nu
#     (`pint.json`) n'est pas un chemin, sauf `Makefile`, `composer.json`,
#     `package.json` et `phpunit.xml`. Un chemin
#     cité sans être modifié (lancé, lu, fichier temporaire, produit par un
#     générateur) se déclare `<!-- hors-compte: `<chemin>` <raison> -->` ;
#   - un sprint absent de la carte, ou une ligne de carte sans sprint.
# Une raison vide n'est ni une dérogation ni une déclaration.
#
# Code 2 : rien à lire (pas de tasks.md, pas de validation-contract.md, aucune
# section `## Sprint`) — jamais un succès silencieux.
#
# Plafonds : MAX_ASSERTIONS=5 MAX_FICHIERS=8 MAX_CLASSES=4 (variables).

set -uo pipefail

cible="${1:-}"
if [ -z "$cible" ]; then
    echo "usage : $0 <dossier du plan | tasks.md>" >&2
    exit 2
fi
[ -d "$cible" ] && cible="$cible/tasks.md"
if [ ! -f "$cible" ]; then
    echo "check-sprint-size : pas de tasks.md en $cible, rien de lu" >&2
    exit 2
fi
contrat="$(dirname "$cible")/validation-contract.md"
if [ ! -f "$contrat" ]; then
    echo "check-sprint-size : pas de $contrat, les assertions ne se lisent pas" >&2
    exit 2
fi

MAX_ASSERTIONS="${MAX_ASSERTIONS:-5}" MAX_FICHIERS="${MAX_FICHIERS:-8}" MAX_CLASSES="${MAX_CLASSES:-4}" \
CONTRAT="$contrat" awk '
function trim(s) { sub(/^[ \t]+/, "", s); sub(/[ \t]+$/, "", s); return s }
# Les chemins entre accents graves d une ligne, dans TOK[1..n] ; rend n.
function chemins(ligne,   morceaux, n, i, k) {
    split("", TOK); k = 0
    n = split(ligne, morceaux, "`")
    for (i = 2; i <= n; i += 2) TOK[++k] = morceaux[i]
    return k
}
# Lit la carte de couverture : CARTE[id] = nombre de V distincts non barrés.
function lire_carte(chemin,   ligne, dans, cellules, id, txt, mots, m, j) {
    while ((getline ligne < chemin) > 0) {
        if (ligne ~ /^## /) { dans = (ligne ~ /^## Carte de couverture/); continue }
        if (!dans || ligne !~ /^[ \t]*\|/) continue
        split(ligne, cellules, "|")
        id = trim(cellules[2])
        if (id == "" || id == "Sprint" || id ~ /^[-:]/) continue
        txt = cellules[3]
        gsub(/~~[^~]*~~/, "", txt)
        gsub(/[^A-Za-z0-9]/, " ", txt)
        CARTE[id] = 0
        m = split(txt, mots, " ")
        for (j = 1; j <= m; j++)
            if (mots[j] ~ /^V[0-9]+$/ && !((id, mots[j]) in VU)) { VU[id, mots[j]] = 1; CARTE[id]++ }
    }
    close(chemin)
}
# Catégorie d un chemin cité : "classe", "compte" ou "" (ne compte pas, D-29).
function categorie(t,   base) {
    if (t ~ /[ *<>{}$~]/ || t ~ /…/ || t ~ /\/$/ || t == "") return ""
    if (t ~ /^tests\/.*Test\.php$/ || t ~ /^scripts\/(.*\/)?tests\/test-[^\/]*\.sh$/) return "classe"
    if (t ~ /\.md$/ || t ~ /^tests\/Support\// || t == "tests/Pest.php" || t ~ /^database\/factories\// \
        || t ~ /^bibliotheque\// || t ~ /(^|\/)(composer\.lock|package-lock\.json)$/ \
        || t == "resources/js/types/generes.d.ts" || t ~ /^scripts\/(.*\/)?tests\//) return ""
    if (t ~ /^(src|app|bootstrap|config|database\/migrations|database\/seeders|resources|scripts|\.github|\.ddev)\/./) return "compte"
    if (t == "Makefile" || t == "composer.json" || t == "package.json" || t == "phpunit.xml" || t == "tests/mutation-seuil.txt") return "compte"
    # Un nom nu (`verifier.yml`, `pint.json`) renvoie à un fichier sans dire lequel :
    # les fichiers de la racine se citent dans les listes, pas ici.
    return ""
}
function erreur(msg) { ERREURS[++nerr] = id " : " msg }
function clore(   na, nf, nc, mot, hors, i) {
    if (id == "") return
    sprints++
    vu_sprint[id] = 1
    na = (id in CARTE) ? CARTE[id] : 0
    nf = nfich; nc = ncls
    if (!(id in CARTE)) erreur("absent de la carte de couverture de validation-contract.md")
    hors = (na > MA || nf > MF || nc > MC)
    mot = ""
    if (hors && derog != "") mot = " — hors budget, dérogation : " derog
    printf "%s : %d assertions, %d fichiers, %d classes de test%s\n", id, na, nf, nc, mot
    if (hors && derog == "")
        erreur(sprintf("hors budget (%d / %d / %d au plus) : coupez-le, ou motivez <!-- derogation-taille: … -->", MA, MF, MC))
    if (derog_vide) erreur("dérogation sans raison")
    if (taille == "") erreur("pas de commentaire <!-- taille : … --> sous le titre")
    else if (taille_n != na " " nf " " nc) erreur(sprintf("commentaire taille « %s » ≠ listes (%d, %d, %d)", taille, na, nf, nc))
    for (i = 1; i <= ncites; i++) {
        t = CITES[i]
        if ((t in LISTE) || (t in HORS)) continue
        erreur("`" t "` cité dans une tâche, absent de **" (CAT[t] == "classe" ? "Classes de test" : "Fichiers") " :** (ou <!-- hors-compte: … -->)")
    }
    for (i = 1; i <= nhv; i++) erreur("hors-compte sans raison : " HORS_VIDE[i])
    id = ""
}
function ouvrir(titre,   mots) {
    split(titre, mots, " ")
    id = mots[3]
    nfich = ncls = ncites = nhv = 0; taille = taille_n = derog = ""; derog_vide = 0
    tache = 0; clos = 0
    split("", LISTE); split("", HORS); split("", CITES); split("", CAT); split("", DEJA); split("", HORS_VIDE)
}
BEGIN {
    MA = ENVIRON["MAX_ASSERTIONS"] + 0; MF = ENVIRON["MAX_FICHIERS"] + 0; MC = ENVIRON["MAX_CLASSES"] + 0
    lire_carte(ENVIRON["CONTRAT"])
}
/^## / { clore(); if ($0 ~ /^## Sprint /) ouvrir($0); next }
id == "" { next }
{
    ligne = $0
    if (ligne ~ /<!-- *taille *:/) {
        taille = ligne; sub(/.*<!-- *taille *: */, "", taille); sub(/ *-->.*/, "", taille)
        x = taille; gsub(/[^0-9]+/, " ", x); taille_n = trim(x)
        next
    }
    if (ligne ~ /<!-- *derogation-taille *:/) {
        r = ligne; sub(/.*derogation-taille *: */, "", r); sub(/ *-->.*/, "", r)
        if (trim(r) == "") derog_vide = 1; else derog = trim(r)
        next
    }
    if (ligne ~ /<!-- *hors-compte *:/) {
        r = ligne; sub(/.*hors-compte *: */, "", r); sub(/ *-->.*/, "", r)
        if (chemins(r) >= 1) {
            raison = r; sub(/^[^`]*`[^`]*`/, "", raison)
            if (trim(raison) == "") HORS_VIDE[++nhv] = TOK[1]; else HORS[TOK[1]] = 1
        }
        next
    }
    if (ligne ~ /^\*\*Fichiers :\*\*/ || ligne ~ /^\*\*Classes de test :\*\*/) {
        n = chemins(ligne)
        for (i = 1; i <= n; i++) {
            LISTE[TOK[i]] = 1
            if (ligne ~ /^\*\*Fichiers/) nfich++; else ncls++
        }
        next
    }
    # Les tâches : une case et ses lignes de suite, hors code entre clôtures.
    if (ligne ~ /^[ \t]*(```|~~~)/) { clos = !clos; tache = 0; next }
    if (clos) next
    if (ligne ~ /^[ \t]*- \[[ xX]\]/) tache = 1
    else if (ligne ~ /^[ \t]*$/ || ligne !~ /^[ \t]/ || ligne ~ /^[ \t]*([-*+]|[0-9]+\.)[ \t]/) tache = 0
    if (!tache) next
    n = chemins(ligne)
    for (i = 1; i <= n; i++) {
        t = trim(TOK[i]); sub(/:[0-9][0-9-]*$/, "", t)
        c = categorie(t)
        if (c == "" || (t in DEJA)) continue
        DEJA[t] = 1
        CAT[t] = c
        CITES[++ncites] = t
    }
}
END {
    clore()
    if (sprints == 0) {
        print "check-sprint-size : aucune section « ## Sprint », rien de lu" > "/dev/stderr"
        exit 2
    }
    for (c in CARTE) if (!(c in vu_sprint)) ERREURS[++nerr] = c " : dans la carte de couverture, aucune section ## Sprint"
    for (i = 1; i <= nerr; i++) print ERREURS[i]
    if (nerr > 0) {
        printf "\n%d erreur(s) de taille dans le plan (P5, D-29)\n", nerr
        exit 1
    }
}
' "$cible"
