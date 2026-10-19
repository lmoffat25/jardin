# Plan d'exécution — partie A (socle)

Version 0.1 — 19/10/2026. Applique le CDC v0.13, les chapitres techniques 1 à 6
et la méthode du chapitre 7 (`README.md`). Contrat : `validation-contract.md`.

## Sommaire

- [Portes valables pour tous les sprints](#portes-valables-pour-tous-les-sprints)
- [Conventions de ce plan](#conventions-de-ce-plan)
- Chantier 0 : [0a](#sprint-0a--audit-de-lhistorique-et-publication-du-dépôt) ·
  [0b](#sprint-0b--squelette-ddev-makefile-ci-minimale) ·
  [0c](#sprint-0c--inertia-react-et-typescript-strict) ·
  [0d](#sprint-0d--outillage-strict-crochets-frontières) ·
  [0e](#sprint-0e--ci-complète-fusion-automatique-consignes-des-agents) ·
  [0f](#sprint-0f--enchaînement-des-sprints)
- Schéma : [1a](#sprint-1a--migrations-du-socle-et-jeu-de-référence) ·
  [1b](#sprint-1b--migrations-du-moteur-du-journal-et-du-plan)
- Cloisonnement et comptes : [2a](#sprint-2a--jardin-courant-et-filtre-par-jardin) ·
  [2b](#sprint-2b--connexion-et-props-partagées) ·
  [2c](#sprint-2c--créer-un-jardin-choisir-son-profil-basculer) ·
  [2d](#sprint-2d--le-test-qui-énumère-les-routes)
- Moteur : [3a](#sprint-3a--projection-décalage-butoirs) ·
  [3b](#sprint-3b--règle-r2-états-manquées-pluriannuel-explication) ·
  [3c](#sprint-3c--fenêtres-multiples-et-occurrences-closes) ·
  [3d](#sprint-3d--règle-r1-saisonnière-et-report-hors-saison) ·
  [3e](#sprint-3e--balayages-calendaires-et-mutation)
- Bibliothèque : [4a](#sprint-4a--chargement-de-la-bibliothèque) ·
  [4b](#sprint-4b--espèces-sourcées-et-tests-du-protocole) ·
  [4c](#sprint-4c--espèces-à-sourcer)
- Calcul et lectures : [5](#sprint-5--le-pont-calcul-des-échéances-en-base) ·
  [6](#sprint-6--tableau-de-bord-et-fiche-de-plante)
- Inventaire : [7a](#sprint-7a--zones-création-de-plante-code-court) ·
  [7b](#sprint-7b--création-en-lot-et-archivage) ·
  [7c](#sprint-7c--liste-modification-en-masse-types-dintervention-zones-archivées)
- Journal : [8a](#sprint-8a--enregistrer-une-intervention-idempotente) ·
  [8b](#sprint-8b--fait-depuis-une-échéance-rétroactif-position)
- Règles : [9](#sprint-9--correction-de-règle-versionnée-suspension-règles-libres)
- Positions : [10a](#sprint-10a--géométries-priorité-des-sources-plantes-proches) ·
  [10b](#sprint-10b--code-court-étiquettes-qr)
- Photos : [11a](#sprint-11a--photos-et-dérivées-en-file) ·
  [11b](#sprint-11b--photo-de-couverture-fond-de-plan-nettoyage)
- [Sprint 12 — export](#sprint-12--export-intégral) ·
  [Sprint 13 — démonstration](#sprint-13--jardin-de-démonstration-et-seuils)
- [Revue des coûts](#revue-des-coûts)

---

## Portes valables pour tous les sprints

Chaque sprint, en plus de sa propre porte :

1. **Toute route passe le test de cloisonnement C15** (`tests/Routes/CloisonnementTest.php`,
   V41). À partir du sprint 2d, une route nouvelle s'ajoute au registre du test
   dans la même demande de fusion ; une route absente du registre fait échouer
   la suite. Avant 2d, les routes créées (2b, 2c) sont inscrites par le sprint 2d.
2. **`make verifier` est vert en local, puis la CI de la demande de fusion est
   verte.** La dernière ligne de `make verifier` est collée dans la demande de
   fusion (C5). La fusion est automatique sur CI verte (P4) ; aucun envoi direct
   sur `main`, jamais de production (règle des agents n° 4).
3. **La documentation change dans la même demande de fusion que le
   comportement** (règle n° 7) : chapitre technique concerné si un choix s'y
   écarte, `AGENTS.md` si une commande change, et la case cochée ici.
4. **Toute décision technique prise en route** va dans `decisions-agents.md`
   (date, sprint, décision, ce qui est écarté et pourquoi), et dans la section
   « Décisions prises » de la demande de fusion.
5. Un attendu de test modifié se signale (RG-49) ; une question d'expérience
   utilisateur va dans `questions-ux.md` et met le sprint de côté (P3) ; un acte
   qui engage Léon arrête l'enchaînement par un paragraphe `ARRET:` (P2).

> Ce fichier est le plan d'exécution ; en cas d'écart avec les chapitres techniques, il l'emporte.

## Conventions de ce plan

- **Code** sous `src/<Module>/`, espace de noms `Src\` (chapitre 3 ; décision
  D-01). **Tests** sous `tests/Moteur`, `tests/Persistance`, `tests/Routes`,
  `tests/Architecture` (chapitre 6 §6) ; les tests des scripts shell sous
  `scripts/**/tests/`.
- **Compte de taille** : « fichiers » = fichiers de code créés ou modifiés, hors
  fichiers produits par un générateur (squelette Laravel, `composer.lock`), hors
  documentation ; « classes de test » = fichiers de test. Le compte est à la main
  pour la partie A, et `check-sprint-size.sh` le recompte à partir du sprint 0f
  (O28, décision D-13).
- **Routes** : les formulaires du bureau répondent en Inertia (redirection et
  erreurs de session, `422` en JSON) ; les actions de terrain (interventions,
  photos, « fait », plantes proches) répondent en JSON (décision D-21). Les
  contrôleurs sont minces : ils valident, appellent une action, rendent.
- **Écrans** : un contrôleur qui sert un écran de la partie B rend le composant
  Inertia nommé ici (`Bureau/TableauDeBord`, `Terrain/AFaire`…) ; le fichier de
  page est écrit par la partie B (décision D-20).
- **Sprints 0a à 0f** : lancés à la main, un agent neuf chacun ; le script
  d'enchaînement n'existe qu'à la fin de 0f.
- **Agronomie** : aucune valeur n'est écrite ici. Les jeux témoins des tests du
  moteur reprennent les ordres de grandeur de l'annexe A du CDC et ne vont
  jamais dans `bibliotheque/` ; les valeurs de la bibliothèque viennent du
  protocole (B1–B4).

---

## Sprint 0a — Audit de l'historique et publication du dépôt
<!-- taille : 2 assertions, 4 fichiers, 1 classe de test -->

**Objectif :** publier `~/jardin` en dépôt public protégé, après avoir prouvé que son historique ne contient ni secret ni donnée réelle.
**Constructeur :** principal (acte qui engage Léon, API GitHub, aucun code applicatif)
**Risque :** complet
**Lecture :** chapitre 6 §2 (C1, C2, C25), §11 point 1 ; README §3 ; CDC §8.4 (RG-28).
**Contrat :** `scripts/depot/auditer-historique.sh` rend `0` et un rapport (commits lus, résultats) ou `1` ; `.github/ruleset-main.json` est la charge complète du ruleset ; `scripts/depot/appliquer-ruleset.sh <proprietaire/depot>` fait un `PUT` (jamais `PATCH`, C2) et relit.
**Tests d'abord :** `scripts/depot/tests/test-auditer-historique.sh` sur un dépôt jetable qui contient un faux secret dans un ancien commit ; premier rouge attendu : « script absent ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** le test du script / l'audit réel de `~/jardin`, une fois.
**Porte :** l'audit vert, puis l'arrêt ; `make verifier` n'existe pas encore.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V1, V2
**Fin :** le dépôt public existe, son historique est celui audité, le ruleset et la détection de secrets sont actifs et relus.
**Coût :** attente de l'accord de Léon, acte qui l'engage → **accepter** : c'est l'arrêt prévu (README §6).

### 0a.1 Audit

- [ ] Créer le dossier `scripts/depot/` et le dossier `scripts/depot/tests/`.
- [ ] Implémenter `scripts/depot/auditer-historique.sh` : lance `gitleaks git --log-opts="--all"` (installé par Homebrew, version écrite dans `decisions-agents.md`) sur le dépôt passé en argument, puis un `git log -p --all` filtré sur des motifs de coordonnées décimales (`-?\d{1,2}\.\d{4,}`), d'adresses (`\d+,? (rue|chemin|route|allée)`) et la liste de noms de lieux de `scripts/depot/lieux-interdits.txt` (vide au départ, Léon peut l'alimenter, jamais versionnée avec des valeurs : le fichier est dans `.gitignore`). Sortie : nombre de commits lus, nombre de résultats ; code `1` au premier résultat. Ne jamais afficher la valeur d'un secret trouvé : seulement fichier, commit, règle.
- [ ] Implémenter `scripts/depot/tests/test-auditer-historique.sh` : crée un dépôt jetable sous `mktemp -d`, un commit propre puis un commit avec une fausse clé AWS de la documentation de gitleaks, puis un commit qui la retire ; attend `1` ; sur un dépôt propre, attend `0`.
- [ ] Créer `.gitleaks.toml` (règles par défaut, aucune exception au départ).
- [ ] Lancer l'audit sur `~/jardin` ; écrire commande, version, nombre de commits lus et résultat dans `decisions-agents.md`. Résultat non vide → `ARRET:` avec la liste (fichier, commit, règle), rien n'est publié.

### 0a.2 Préparer la publication

- [ ] Créer `.github/ruleset-main.json` : cible `main`, interdit la suppression et la réécriture (`non_fast_forward`), exige une demande de fusion ; **sans** contrôle requis (ajoutés au 0e, quand `verifier.yml` existe).
- [ ] Implémenter `scripts/depot/appliquer-ruleset.sh` : `gh api --method PUT repos/$1/rulesets/<id>` (ou `POST` à la première création, puis `PUT`), puis `gh api repos/$1/rulesets/<id>` et compare la relecture à la charge ; code `1` si elles diffèrent.

### 0a.3 ARRÊT — acte qui engage Léon (README §3)

- [ ] **ARRÊT.** L'agent termine sa passe par un paragraphe `ARRET:` qui donne à Léon : le résultat de l'audit, le nom proposé du dépôt, et les commandes qu'il lancera **après l'accord explicite de Léon dans la session** : `gh repo create <nom> --public --source ~/jardin --remote origin`, activation de la détection de secrets et du blocage à l'envoi (`gh api --method PATCH repos/<nom> -f security_and_analysis[secret_scanning][status]=enabled -f security_and_analysis[secret_scanning_push_protection][status]=enabled`), `git push origin main`, puis `appliquer-ruleset.sh`. **Rien n'est créé ni envoyé avant cet accord ; il n'existe aucun contournement.**

### 0a.4 Après l'accord

- [ ] Exécuter les commandes de 0a.3, dans cet ordre (protections avant le premier envoi).
- [ ] Relire par `gh api repos/<nom>` : `private: false`, détection et blocage `enabled` ; relire le ruleset ; comparer `git rev-list --all` local et distant. Écrire la relecture dans `decisions-agents.md`. Un écart → `ARRET:`.

---

## Sprint 0b — Squelette, DDEV, Makefile, CI minimale
<!-- taille : 4 assertions, 7 fichiers, 3 classes de test -->

**Objectif :** un clone neuf démarre en une commande, rend `/sante`, et la CI de la demande de fusion passe sur un test trivial.
**Constructeur :** back (PHP, conteneurs, YAML)
**Risque :** complet
**Lecture :** chapitre 6 §3 (DDEV), §4 (Makefile), §8 (jobs) ; chapitre 3 §2 (modules, A16).
**Contrat :** `GET /sante` → `200 {"etat":"ok"}`, sans session ; `make setup|moteur|statique|test|balayage|e2e|mutation|criteres|verifier` existent ; `verifier.yml` sur `pull_request`, un job `tests` avec un service PostgreSQL de même majeure que DDEV.
**Tests d'abord :** `tests/Routes/SanteTest.php` ; premier rouge attendu : « route absente (404) ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** `ddev exec php artisan test --filter=Sante` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V3, V4, V5, V6
**Fin :** `make setup` sur un clone neuf, puis `/sante` en HTTPS local, puis CI verte sur la première demande de fusion.
**Coût :** construction des conteneurs (boucle lente) et DDEV sur OrbStack jamais lancé pour ce projet → **essai d'abord** (0b.1).

### 0b.1 Essai d'abord

- [ ] Démarrer OrbStack, lancer `ddev start` sur un projet vide sous le bloc-notes avec `php 8.4`, `postgres 17`, `nodejs 22` ; relever les versions réellement servies et les écrire dans `decisions-agents.md` (chapitre 6 §3 : « à vérifier au chantier 0 »). Si une version n'existe pas, prendre la plus proche et l'écrire.

### 0b.2 Squelette

- [ ] Créer l'ossature Laravel 12 dans un dossier temporaire (`composer create-project laravel/laravel`), puis la déplacer dans `~/jardin` à côté de `docs/` et `design/` sans écraser `.gitignore` (fusionner les deux).
- [ ] Modifier `composer.json` : autoload `"Src\\": "src/"` ; `pestphp/pest` et `pestphp/pest-plugin-laravel` en développement.
- [ ] Créer `.ddev/config.yaml` (type laravel, versions de 0b.1, `webserver_type: nginx-fpm`, `database: postgres:17`).
- [ ] Implémenter `app/Providers/ModulesServiceProvider.php` : parcourt `src/*/routes.php` (glob trié) et charge chaque fichier dans le groupe `web` ; aucun fichier n'est une erreur (A16). L'enregistrer dans `bootstrap/providers.php`.
- [ ] Implémenter `src/Support/Http/SanteController.php` (`__invoke` → `response()->json(['etat' => 'ok'])`, après un `select 1` par Eloquent sur la connexion) et `src/Support/routes.php` (`GET /sante`, sans authentification).

### 0b.3 Makefile et CI

- [ ] Créer `Makefile` : toutes les cibles du chapitre 6 §4, les vides font `@echo "(vide)"` ; `verifier` enchaîne `statique test balayage e2e mutation criteres` par dépendances séquentielles (`$(MAKE)` successifs, arrêt au premier code non nul). Chaque cible marche dans DDEV (`ddev exec`) et dans la CI (variable `DANS_CI=1`, commandes directes).
- [ ] Créer `.github/workflows/verifier.yml` : `on: pull_request`, job `tests` avec service `postgres:17`, étapes `actions/checkout`, `shivammathur/setup-php` (8.4), `make setup`, `make test` ; aucune autre commande de travail (C6).

### 0b.4 Tests

- [ ] `tests/Routes/SanteTest.php` : `200`, corps exact, sans session.
- [ ] `tests/Architecture/ChargementRoutesTest.php` : crée un module témoin `src/Temoin/routes.php` dans un dossier temporaire chargé par le fournisseur, vérifie la route ; sans fichier, l'application démarre (V4).
- [ ] `tests/Architecture/MakefileTest.php` : `make -n verifier` liste les six cibles dans l'ordre ; une cible forcée en échec (`ECHEC=statique`) arrête l'enchaînement avec un code non nul (V5).
- [ ] Ouvrir la première demande de fusion ; relever le run vert (V6).

---

## Sprint 0c — Inertia, React et TypeScript strict
<!-- taille : 2 assertions, 8 fichiers, 2 classes de test -->

**Objectif :** un seul bundle Inertia en TypeScript strict, et des types TypeScript générés depuis PHP, tenus à jour par `make statique`.
**Constructeur :** front (Vite, TypeScript, Inertia)
**Risque :** complet
**Lecture :** chapitre 3 §3 (encart V1) et §4 (props partagées) ; chapitre 6 §5 ; O5.
**Contrat :** un seul point d'entrée `resources/js/app.tsx` ; `inertia.testing.ensure_pages_exist = false` (décision D-20) ; `make types` produit `resources/js/types/generes.d.ts`.
**Tests d'abord :** `tests/Routes/InertiaTest.php` ; premier rouge attendu : « composant Inertia absent de la réponse ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** `tsc --noEmit` et le test / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V7, V8
**Fin :** une route de test rend un composant Inertia nommé ; une classe PHP modifiée sans régénération fait échouer `make statique`.

### 0c.1 Bundle

- [ ] Installer `inertiajs/inertia-laravel`, `@inertiajs/react`, React 19, TypeScript. Créer `resources/views/app.blade.php`, `resources/js/app.tsx` (résolution `./pages/**/*.tsx`), `vite.config.ts` (un seul `input`), `tsconfig.json` (`strict: true`, `noUncheckedIndexedAccess: true`), et modifier `package.json` (scripts `build`, `typecheck`).
- [ ] Implémenter `src/Support/Http/HandleInertiaRequests.php` (props partagées vides pour l'instant, remplies au 2b) et l'enregistrer dans `bootstrap/app.php`.
- [ ] Créer `config/inertia.php` avec `testing.ensure_pages_exist => false` et un commentaire qui renvoie à D-20.

### 0c.2 Types générés

- [ ] Choisir le générateur (O5) — par défaut `spatie/laravel-typescript-transformer`, attribut `#[TypeScript]` sur les classes de données — et écrire le choix dans `decisions-agents.md`. Cible `make types` ; `make statique` régénère dans un fichier temporaire et `diff` avec le versionné.
- [ ] `tests/Routes/InertiaTest.php` : une route de test enregistrée par le test rend `Temoin/Page` avec une prop.
- [ ] `tests/Architecture/TypesGeneresTest.php` : une classe témoin annotée produit le type attendu ; un fichier versionné périmé fait échouer la comparaison (V8).

---

## Sprint 0d — Outillage strict, crochets, frontières
<!-- taille : 4 assertions, 8 fichiers, 4 classes de test -->

**Objectif :** l'analyse statique, les crochets git, les tests de frontières et `make criteres` attrapent ce qu'ils doivent attraper.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 6 §5 (outils), §6.1 (C7), §7 (crochets, C16, C17), §6.3 (`criteres`) ; chapitre 3 §9 (frontières) ; CDC §10 (numéros 1 à 24).
**Contrat :** `make statique` = PHPStan/Larastan + Pint `--test` + `tsc --noEmit` + ESLint + Prettier `--check` + `composer audit` + `npm audit --audit-level=high` + régénération des types ; `make criteres [LIVRAISON=1]`.
**Tests d'abord :** `tests/Architecture/FrontieresTest.php` ; premier rouge attendu : « fichier témoin interdit non détecté ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** chaque outil seul / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V9, V10, V11, V12
**Fin :** une violation introduite dans chaque outil est rouge ; un envoi depuis un worktree lance les crochets ; `make criteres` liste les 24 critères sans test.

### 0d.1 Analyse statique

- [ ] Créer `phpstan.neon` (Larastan, niveau `max` ; si `max` n'est pas tenable sur le squelette, le niveau le plus haut qui l'est, écrit avec sa raison — O18), `pint.json` (préréglage `laravel`), `eslint.config.js` (typescript-eslint `strictTypeChecked`, react-hooks), Prettier dans `package.json`.
- [ ] Modifier `Makefile` : cible `statique` comme au contrat.
- [ ] `scripts/tests/test-statique.sh` : pour chaque outil, copie le dépôt dans un dossier temporaire, y introduit une violation témoin (variable non typée, espace en trop, `any`, dépendance vulnérable fictive dans un `composer.lock` de test), attend un échec de `make statique` (V9).

### 0d.2 Crochets

- [ ] Créer `scripts/hooks/pre-commit` (Pint et Prettier sur les fichiers indexés, puis `git add` de ces fichiers) et `scripts/hooks/pre-push` (`make statique && make test`).
- [ ] Modifier `Makefile` : `setup` copie les crochets dans `$(git rev-parse --git-common-dir)/hooks` (C17).
- [ ] `scripts/hooks/tests/test-crochets.sh` : dépôt jetable, `make setup`, `git worktree add`, envoi vers un dépôt nu local depuis le worktree ; le crochet a tourné (marqueur écrit par une cible `test` factice) (V10).

### 0d.3 Frontières et critères

- [ ] `tests/Architecture/FrontieresTest.php` (Pest `arch()`) : `Src\Echeances\Moteur` n'utilise ni `Illuminate`, ni `Carbon`, ni les fonctions `now`, `time`, `date` ; `Src` n'utilise pas `DB::table`, `DB::select`, `DB::statement`, `DB::insert`, `DB::update`, `DB::delete` (V11).
- [ ] Implémenter `scripts/criteres.sh` : extrait les numéros 1 à 24 du tableau du CDC §10 (`docs/cahier-des-charges-fonctionnel.md`), cherche `->group('critere-NN')` dans `tests/`, liste les manquants ; code `0`, ou `1` si `LIVRAISON=1` et un manquant. Cible `make criteres`.
- [ ] `tests/Architecture/CriteresTest.php` : lance le script sur un CDC et un dossier de tests témoins ; vérifie la liste et les deux codes (V12).

---

## Sprint 0e — CI complète, fusion automatique, consignes des agents
<!-- taille : 5 assertions, 6 fichiers, 3 classes de test -->

**Objectif :** la CI a tous ses jobs, la mutation tourne, la fusion est automatique sur CI verte, et un agent trouve sa consigne dans le dépôt.
**Constructeur :** principal (ruleset et réglages du dépôt par l'API GitHub)
**Risque :** complet
**Lecture :** chapitre 6 §8 (jobs, C18–C20), §9 (C24 révisée), §10 (AGENTS, règles, modèle de demande de fusion) ; CDC §8.8.
**Contrat :** jobs `changements`, `statique`, `tests`, `e2e`, `mutation` ; `e2e` et `mutation` réussissent en moins d'une minute sans changement qui les concerne ; ruleset avec contrôles requis ; `allow_auto_merge: true`.
**Tests d'abord :** `tests/Architecture/WorkflowTest.php` (lit le YAML) ; premier rouge attendu : « job mutation absent ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** le test du YAML / un seul envoi pour la CI réelle.
**Porte :** `make verifier`, puis la CI de la demande de fusion, **fusionnée automatiquement** (c'est la preuve de V16).
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V13, V14, V15, V16, V17
**Fin :** la demande de fusion de ce sprint est la première fusionnée sans geste humain.
**Coût :** chaque essai de CI réelle est un envoi et un run de plusieurs minutes, fusion automatique et mutation jamais observées → **sprint d'outil** pour la mutation (0e.1, classe témoin) et **accepter** pour la CI : itérer sur `WorkflowTest`, n'envoyer qu'une fois.

### 0e.1 Mutation, essai d'abord

- [ ] Installer `pestphp/pest-plugin-mutate` et un pilote de couverture (`pcov` dans DDEV par `webimage_extra_packages`, `coverage: pcov` dans la CI). Créer la classe témoin `src/Support/Temoin/Parite.php` (une méthode `estPaire(int): bool`) et son test ; `make mutation` sur `src/Support/Temoin` rapporte une mutation tuée et, avec un test volontairement faible, une survivante (V13). Écrire le temps mesuré dans `decisions-agents.md`. La classe est retirée au 3e.

### 0e.2 Workflow

- [ ] Modifier `.github/workflows/verifier.yml` : job `changements` (`dorny/paths-filter`, sorties `moteur`, `front`) ; `statique` (`make statique`, `make criteres`) ; `tests` (`make test`, `make balayage`) ; `e2e` et `mutation` qui démarrent toujours et sortent en succès quand leur filtre est faux ; `timeout-minutes` sur chaque job ; `concurrency: { group: verifier-${{ github.head_ref }}, cancel-in-progress: true }` ; aucun `schedule`.
- [ ] `tests/Architecture/WorkflowTest.php` : parse le YAML (`symfony/yaml`), vérifie jobs, `timeout-minutes`, `concurrency`, absence de `schedule`, et que chaque étape `run:` commence par `make ` (V6, V14, V15).

### 0e.3 Fusion automatique

- [ ] Modifier `.github/ruleset-main.json` : `required_status_checks` (les cinq jobs), `strict_required_status_checks_policy: true` ; l'appliquer par `scripts/depot/appliquer-ruleset.sh` (PUT, relecture). Activer `allow_auto_merge` par `gh api --method PATCH repos/<nom> -F allow_auto_merge=true` et relire.
- [ ] Ouvrir la demande de fusion du sprint avec `gh pr merge --auto --squash` ; relever qu'elle est fusionnée sans geste (V16).

### 0e.4 Consignes

- [ ] Créer `AGENTS.md` (produit en trois lignes, carte des documents, commandes `make`, les neuf règles du chapitre 6 §10, l'arrêt du README §3), `.claude/rules/git.md`, `.claude/rules/tests.md`, `.claude/rules/documentation.md`, `.github/pull_request_template.md` (six titres du chapitre 6 §10), `.github/dependabot.yml` (composer, npm, github-actions ; hebdomadaire ; groupés).
- [ ] `tests/Architecture/ConsignesTest.php` : les six titres du modèle, la périodicité et les trois écosystèmes de dependabot, la présence des fichiers de règles (V17).

---

## Sprint 0f — Enchaînement des sprints
<!-- taille : 5 assertions, 5 fichiers, 2 classes de test -->

**Objectif :** un script lance un agent neuf par sprint jusqu'à la fin du plan ou un arrêt, en adaptant `buildfeature-run.sh` de Treize Pourcent.
**Constructeur :** principal (script de pilotage des sessions, skill projet)
**Risque :** complet
**Lecture :** README §3, §6 ; source `~/Documents/work/treizepourcent/treizepourcentplateforme/scripts/plan/buildfeature-run.sh`, `test-buildfeature-run.sh`, `check-sprint-size.sh`, et le skill `buildfeature` (étape 8, ligne de handoff) ; décision D-12.
**Contrat :** `bash scripts/plan/enchainer.sh` ; codes `0` plan fini · `1` passe en erreur · `2` rien à lancer · `3` passe sans sprint fini ou `ARRET:` · `4` `MAX_SPRINTS` atteint · `5` tous les sprints restants attendent une question UX ; variables `ENCHAINER_CLAUDE` (faux claude des tests), `ENCHAINER_RACINE`, `ENCHAINER_PREVENIR` (commande appelée sur `3` et `5`, facultative, O27).
**Tests d'abord :** `scripts/plan/tests/test-enchainer.sh` (repris des dix cas de `test-buildfeature-run.sh`, plus deux) ; premier rouge attendu : « script absent ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** le test du script / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** une passe réelle sur un plan témoin d'un sprint trivial (« ajouter une ligne au README »), lancée une fois, avec un claude réel.
**Question UX possible :** aucune (O27 n'est pas une question d'écran : sans réponse, `ENCHAINER_PREVENIR` reste vide)
**Assertions :** V18, V19, V20, V21, V22
**Fin :** la passe réelle sur le plan témoin va jusqu'à la fusion automatique, coche la case dans `main`, ajoute sa ligne de handoff, et la boucle rend `0`.

### 0f.1 Script

- [ ] Implémenter `scripts/plan/enchainer.sh` en adaptant `buildfeature-run.sh` : chaque passe est `claude -p "/construire-sprint --un-sprint" --permission-mode auto` dans le clone principal, avec `CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS=0` ; avant chaque passe `git -C <clone> pull --ff-only` (échec → code `1`) ; état = cases non cochées des sections `## Sprint` de `docs/plan/tasks.md` lu sur `main`, en **excluant** les sprints dont l'en-tête porte `**En attente :**` ; lignes de `docs/plan/handoffs.jsonl` ; `^ARRET:` dans la sortie → code `3` ; passe sans ligne nouvelle et sans `ARRET:` → une relance, puis code `3` ; résumé de passe lu dans `run.log` (blocs « Décisions prises », « Points faibles », « En attente », ligne « Suivant : »).
- [ ] Modifier `.gitignore` : `docs/plan/handoffs.jsonl`, `docs/plan/run.log`.
- [ ] Créer `.claude/skills/construire-sprint/SKILL.md` adapté de `buildfeature` : lire `AGENTS.md`, choisir le premier sprint non coché et non en attente, créer la branche et le worktree, tests d'abord, construire, `make verifier`, demande de fusion en brouillon puis prête avec fusion automatique, attendre la fusion (`gh pr checks --watch`), corriger tant que la CI est rouge, puis écrire la ligne de handoff dans le `handoffs.jsonl` du clone principal (`git rev-parse --git-common-dir`). Une question UX → `questions-ux.md` + `**En attente :** Q-UX-n` dans l'en-tête du sprint, dans la même demande de fusion, puis fin de passe sans handoff mais avec la ligne « Suivant : ».

### 0f.2 Taille des sprints

- [ ] Implémenter `scripts/plan/check-sprint-size.sh` en adaptant celui de Treize Pourcent : motif `V\d+`, section `## Carte de couverture` de `validation-contract.md`, lecture des commentaires `<!-- taille : … -->`, dérogation `<!-- derogation-taille: raison -->` ; code `1` au-delà de 5 / 8 / 4 (O28, D-13). Cible `make plan`.

### 0f.3 Tests

- [ ] `scripts/plan/tests/test-enchainer.sh` : les dix cas repris (avec faux claude), plus : un sprint `En attente` est sauté ; tous en attente → `5` et `ENCHAINER_PREVENIR` appelé ; `pull --ff-only` en échec → `1` (V18–V21).
- [ ] `scripts/plan/tests/test-check-sprint-size.sh` : ce plan passe ; un sprint fabriqué à six assertions rend `1` (V22).

---

## Sprint 1a — Migrations du socle et jeu de référence
<!-- taille : 4 assertions, 7 fichiers, 3 classes de test -->

**Objectif :** les tables des comptes, des jardins, du référentiel et de l'inventaire existent avec leurs contraintes, et le jeu de référence se charge deux fois sans rien changer.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 4 §2 (S1–S6), §3.1 à §3.3, §5 (ordre, jeux de démarrage), S13–S14 ; CDC §5.4 (profils), §3.7 (types fournis) ; décisions D-02, D-03, D-04.
**Contrat :** migrations 1 à 4 du chapitre 4 §5, une par étape ; `php artisan db:seed --class=ReferenceSeeder`.
**Tests d'abord :** `tests/Persistance/MigrationsTest.php` ; premier rouge attendu : « table utilisateurs inexistante ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** `php artisan test tests/Persistance` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V23, V24, V25, V26
**Fin :** `migrate:fresh --seed` passe, et les contraintes refusent ce qu'elles doivent refuser.

### 1a.1 Migrations

- [ ] Supprimer les migrations `users`, `password_reset_tokens` du squelette et remplacer la table par `utilisateurs` (chapitre 4) ; garder `sessions`, `cache`, `jobs` (tables techniques).
- [ ] Créer `database/migrations/2026_10_20_000001_creer_utilisateurs_profils_familles.php` : `utilisateurs`, `profils_climatiques` (id smallint fourni), `familles_botaniques`, au SQL du chapitre 4.
- [ ] Créer `…_000002_creer_jardins_especes.php` : `jardins` (`profil_climatique_id NOT NULL`, `quota_photos_mo` 2048), `especes`.
- [ ] Créer `…_000003_creer_adhesions_types_zones_regles_reference.php` : `adhesions` (CHECK rôle, UNIQUE), `types_intervention` (`jardin_id` nullable), `zones` **plus `archivee_le timestamptz NULL`** (D-04), `regles_reference` (CHECK `verifiee_exige_une_source`, `confiance NOT NULL` sans défaut).
- [ ] Créer `…_000004_creer_plantes_compteurs.php` : `plantes` au SQL du chapitre 4 (UNIQUE `(jardin_id, code_court)`, UNIQUE `(zone_id, nom)`) **et** `compteurs_codes_courts (jardin_id uuid NOT NULL REFERENCES jardins ON DELETE CASCADE, prefixe varchar(4) NOT NULL, dernier integer NOT NULL, PRIMARY KEY (jardin_id, prefixe))` (D-03). La FK `photo_couverture_id` attend la migration 7.

### 1a.2 Référence

- [ ] Créer `src/Comptes/Domaine/Utilisateur.php` (Authenticatable, table `utilisateurs`, uuid) et modifier `config/auth.php` (fournisseur `Src\Comptes\Domaine\Utilisateur`).
- [ ] Implémenter `database/seeders/ReferenceSeeder.php` : 5 profils (libellés, `decalage_jours` −15, −7, 0, +7, +21, ordre) et les types fournis du CDC §3.7 (`jardin_id` NULL), par `upsert` sur la clé naturelle (id du profil ; libellé du type), en Eloquent.

### 1a.3 Tests

- [ ] `tests/Persistance/MigrationsTest.php` : `migrate:fresh` deux fois ; les tables existent (V23).
- [ ] `tests/Persistance/ReferenceSeederTest.php` : décalages exacts ; deux passages → mêmes lignes, mêmes `id` (V23).
- [ ] `tests/Persistance/ContraintesSocleTest.php` : jardin sans profil refusé ; rôle inconnu et adhésion double refusés ; codes courts et noms (V24, V25) ; règle de référence `verifiee` sans source et sans confiance refusées (V26). Chaque cas attend une `QueryException` dont le `SQLSTATE` est `23xxx`.

---

## Sprint 1b — Migrations du moteur, du journal et du plan
<!-- taille : 5 assertions, 4 fichiers, 4 classes de test -->

**Objectif :** toutes les tables métier existent, chacune porte son jardin, et les contraintes qui protègent le moteur tiennent.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 4 §3.4 à §3.7, §3 bis (`fonds_de_plan`), §4 (index), S10–S18 ; modèle de données D10 ; décision D-02.
**Contrat :** migrations 5, 5 bis, 6 et 7 du chapitre 4 §5 ; `fonds_de_plan` en migration 6.
**Tests d'abord :** `tests/Persistance/CloisonnementSchemaTest.php` ; premier rouge attendu : « table regles inexistante ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** `php artisan test tests/Persistance` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V27, V28, V29, V30, V31
**Fin :** le schéma entier du chapitre 4 est en place, avec `jardin_id` sur `fenetres` et `intervalles_saisonniers`.

### 1b.1 Migrations

- [ ] Créer `…_000005_creer_geometries_regles_interventions.php` : `geometries` (`plante_id UNIQUE`, `coordonnees jsonb`, CHECK forme et source), `regles` (CHECK `est_active = false OR remplacee_le IS NULL`), `interventions` (`id uuid` sans défaut : fourni par le client ; `faite_le date`).
- [ ] Créer `…_000005b_index_regle_active.php` : index unique partiel `(plante_id, type_intervention_id) WHERE est_active`.
- [ ] Créer `…_000006_creer_fenetres_intervalles_echeances_photos_fonds.php` : `fenetres` et `intervalles_saisonniers` **avec `jardin_id uuid NOT NULL REFERENCES jardins ON DELETE CASCADE`** (D-02) ; `echeances` (CHECK état `manquee|suspendue|soldee`, `explication varchar(400) NOT NULL`, UNIQUE `(regle_id, due_le)`) ; `photos` (CHECK plante ou intervention) ; `fonds_de_plan` (CHECK nature, index unique partiel `(jardin_id) WHERE actif`) ; les trois index du §4.
- [ ] Créer `…_000007_fk_photo_couverture.php` : `fk_photo_couverture … ON DELETE SET NULL`.

### 1b.2 Tests

- [ ] `tests/Persistance/CloisonnementSchemaTest.php` : lit `information_schema.columns` par Eloquent (`Schema::getColumns`) ; liste fermée d'exceptions écrite dans le test ; une table hors liste sans `jardin_id NOT NULL` échoue (V27).
- [ ] `tests/Persistance/ContraintesMoteurTest.php` : V28 (deux actives refusées ; active + inactive acceptées ; active avec `remplacee_le` refusée) et V29 (état calculé, explication nulle, doublon `(regle_id, due_le)` refusés).
- [ ] `tests/Persistance/ContraintesFormeTest.php` : butoir à moitié, photo orpheline, fond `image` sans fichier, deux fonds actifs (V30).
- [ ] `tests/Persistance/IndexTest.php` : les trois index commencent par `jardin_id` (`pg_indexes`) ; `EXPLAIN` de la requête A9 sur 2 000 interventions de test (après `ANALYZE`) cite `idx_interventions_historique` (V31).

---

## Sprint 2a — Jardin courant et filtre par jardin
<!-- taille : 4 assertions, 8 fichiers, 4 classes de test -->

**Objectif :** toute lecture et toute écriture d'un modèle métier est filtrée par le jardin courant sans qu'on l'écrive, et rien ne tourne sans jardin courant.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 3 §5 (A11, `JardinScope`, jobs, commandes) ; modèle de données D1 ; CDC RG-21, RG-22.
**Contrat :** `final class JardinCourant { public function positionner(string $jardinId): void; public function id(): string; public function estPositionne(): bool; }` (singleton du conteneur, remis à zéro entre deux requêtes et deux jobs) ; trait `Src\Support\Jardin\AppartientAuJardin` (ajoute `JardinScope`, renseigne `jardin_id` au `creating`, relation `jardin()`) ; exception `JardinCourantAbsent`.
**Tests d'abord :** `tests/Persistance/JardinScopeTest.php` ; premier rouge attendu : « classe JardinCourant absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les quatre classes de test / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V32, V33, V34, V35
**Fin :** deux jardins en base, aucune lecture depuis A ne rend une ligne de B, et une lecture sans jardin courant lève `JardinCourantAbsent`.

### 2a.1 Socle

- [ ] Implémenter `src/Support/Jardin/JardinCourant.php` : `id()` lève `JardinCourantAbsent` s'il n'est pas positionné ; jamais `null` (A11). Enregistré en `scoped` dans `ModulesServiceProvider` (remis à zéro par requête et par job).
- [ ] Implémenter `src/Support/Jardin/JardinScope.php` : `apply()` ajoute `where <table>.jardin_id = JardinCourant::id()` ; aucune lecture ne passe sans jardin positionné.
- [ ] Implémenter `src/Support/Jardin/AppartientAuJardin.php` : `bootAppartientAuJardin()` ajoute le scope et, au `creating`, `jardin_id ??= JardinCourant::id()` ; un `jardin_id` fourni et différent du courant lève `\LogicException` (aucune écriture croisée).
- [ ] Implémenter `src/Support/Jardin/JobDeJardin.php` : trait de job avec propriété `public string $jardinId` et middleware de job qui appelle `positionner()` avant `handle()` ; un job sans `jardinId` échoue avant `handle()` (`\LogicException`), sans écrire.

### 2a.2 Premiers modèles

- [ ] Implémenter `src/Comptes/Domaine/Jardin.php` et `src/Comptes/Domaine/Adhesion.php` (référentiel du compte : `jardins` n'utilise pas le trait ; `Adhesion` non plus, elle est lue par utilisateur), `src/Jardin/Domaine/Zone.php` et `src/Jardin/Domaine/Plante.php` (trait `AppartientAuJardin`, uuid, casts). Ajouter les factories `database/factories/` correspondantes (fichiers de test, hors compte).

### 2a.3 Tests

- [ ] `tests/Persistance/JardinCourantTest.php` : `id()` sans positionnement lève ; après, rend l'id (V32).
- [ ] `tests/Persistance/JardinScopeTest.php` : listes, `find`, relation `Zone::plantes` depuis A ne rendent rien de B ; création sans `jardin_id` reçoit A ; création avec `jardin_id` de B lève (V33).
- [ ] `tests/Architecture/ModelesCloisonnesTest.php` : toute classe `Src\*\Domaine\*` qui étend `Model` utilise `AppartientAuJardin`, sauf la liste fermée (`Jardin`, `Adhesion`, `Utilisateur`, `ProfilClimatique`, `FamilleBotanique`, `Espece`, `RegleReference`, `TypeIntervention` — ce dernier filtré à part, voir 7c) (V34).
- [ ] `tests/Persistance/JobDeJardinTest.php` : un job témoin portant A n'écrit que dans A ; sans `jardinId`, il échoue sans rien écrire (V35).

---

## Sprint 2b — Connexion et props partagées
<!-- taille : 3 assertions, 6 fichiers, 3 classes de test -->

**Objectif :** on se connecte, le jardin courant est positionné à chaque requête, et il arrive sur toutes les pages.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC §6 lot 0 (compte), RG-43 ; chapitre 3 §4 (props partagées), §5 ; `conception-ecrans.md` §4 (B1).
**Contrat :** `GET /connexion` → Inertia `Bureau/Connexion` (B1) ; `POST /connexion {courriel, mot_de_passe}` → `302 /` ou `302 back` avec erreur sur `courriel` (« Identifiants incorrects. ») ; `POST /deconnexion` ; `php artisan jardin:creer-proprietaire {courriel}` (mot de passe lu sur l'entrée standard).
**Tests d'abord :** `tests/Routes/ConnexionTest.php` ; premier rouge attendu : « route /connexion absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet (l'écran B1 est en partie B)
**Question UX possible :** aucune
**Assertions :** V36, V37, V38
**Fin :** une session ouverte voit le jardin courant dans ses props ; sans session, toute route métier redirige vers `/connexion`.

### 2b.1 Connexion

- [ ] Implémenter `src/Comptes/Http/ConnexionController.php` (`afficher`, `connecter`, `deconnecter`) : `Auth::attempt`, régénération de session, limitation à 5 essais par minute (`RateLimiter`), mise à jour de `derniere_connexion_le` ; erreur unique, sans dire quel champ est faux.
- [ ] Créer `src/Comptes/routes.php` : les trois routes ; un groupe `auth` + `jardin` (middleware de 2b.2) pour toutes les routes métier des modules suivants, défini par `ModulesServiceProvider`.
- [ ] Implémenter `src/Comptes/Console/CreerProprietaire.php` : refuse (code `1`, rien écrit) s'il existe déjà un utilisateur ; lit le mot de passe par `secret()` ; jamais en argument.

### 2b.2 Jardin courant de la requête

- [ ] Implémenter `src/Support/Http/PositionnerJardinCourant.php` (middleware `jardin`) : prend `session('jardin_id')` si l'utilisateur a une adhésion à ce jardin, sinon le premier jardin de ses adhésions (ordre de création) ; aucun jardin → redirection vers la création de jardin (`/jardins/nouveau`, B2) ; positionne `JardinCourant`.
- [ ] Modifier `src/Support/Http/HandleInertiaRequests.php` : props `utilisateur {id, courriel}`, `jardinCourant {id, nom, profil {id, libelle, decalage_jours}}`, `jardins [{id, nom}]` (adhésions).
- [ ] Modifier `bootstrap/app.php` : alias `jardin`, redirection des invités vers `/connexion`.

### 2b.3 Tests

- [ ] `tests/Routes/ConnexionTest.php` : nominal, mot de passe faux (message identique à courriel inconnu), route métier sans session → `/connexion`, `derniere_connexion_le` relue (V36).
- [ ] `tests/Persistance/CreerProprietaireTest.php` : création, refus si un compte existe, mot de passe jamais dans la sortie (V37).
- [ ] `tests/Routes/PropsPartageesTest.php` : une page de test authentifiée porte les trois props ; un `session('jardin_id')` d'un jardin sans adhésion est ignoré (V38).

---

## Sprint 2c — Créer un jardin, choisir son profil, basculer
<!-- taille : 2 assertions, 7 fichiers, 2 classes de test -->

**Objectif :** un utilisateur crée un second jardin avec son profil climatique, bascule de l'un à l'autre, et rien ne passe de l'un à l'autre.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-20, RG-22, RG-23, RG-26, §5.4 ; chapitre 3 §2 (module Comptes) ; `conception-ecrans.md` (B2).
**Contrat :** `GET /jardins/nouveau` → `Bureau/Jardin` (B2) ; `POST /jardins {nom, profil_climatique_id}` → `302 /` ; `PUT /jardin {nom, profil_climatique_id, commune?}` (jardin courant, propriétaire seul) ; `POST /jardins/{jardin}/basculer` → `302 /` ou `403`.
**Tests d'abord :** `tests/Routes/JardinTest.php` ; premier rouge attendu : « route /jardins absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les deux classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V39, V40
**Fin :** deux jardins pour un même utilisateur, chacun voit ses seules zones.

### 2c.1 Actions

- [ ] Implémenter `src/Comptes/Jardins/CreerJardin.php` : `executer(Utilisateur $u, string $nom, int $profilId): Jardin` ; transaction ; crée le jardin et l'adhésion `proprietaire` ; positionne la session et `JardinCourant`.
- [ ] Implémenter `src/Comptes/Jardins/ChoisirProfilClimatique.php` : met à jour `jardins.profil_climatique_id` du jardin courant, rien d'autre (V75 le vérifiera côté échéances).
- [ ] Implémenter `src/Comptes/Jardins/BasculerDeJardin.php` : vérifie l'adhésion, écrit `session('jardin_id')` ; sinon `AuthorizationException` (`403`), session inchangée.
- [ ] Implémenter `src/Comptes/Domaine/ProfilClimatique.php` (référentiel, lecture seule), `src/Comptes/Policies/JardinPolicy.php` (`modifier` : rôle `proprietaire`), `src/Comptes/Jardins/JardinController.php` (`nouveau`, `creer`, `modifier`, `basculer`), et modifier `src/Comptes/routes.php`. Validation : `nom` requis, 1 à 120 caractères ; `profil_climatique_id` requis et existant.

### 2c.2 Tests

- [ ] `tests/Routes/JardinTest.php` : les quatre cas de V39 (nominal, refus sans nom / sans profil / profil inconnu, autre jardin, relecture de `jardins` et `adhesions`) ; un membre qui modifie → `403`.
- [ ] `tests/Routes/BasculerTest.php` : vers un jardin avec adhésion, puis sans (`403`, jardin courant inchangé relu par les props) (V40).

---

## Sprint 2d — Le test qui énumère les routes
<!-- taille : 1 assertion, 6 fichiers, 1 classe de test -->

**Objectif :** un seul test parcourt toutes les routes de l'application et prouve qu'aucune ne lit, ne montre ni ne modifie un autre jardin.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 6 §6.4 (C15) ; chapitre 2 TS-6 (test n° 4) ; CDC critère 12.
**Contrat :** `tests/Support/RegistreDesRoutes.php` : pour chaque nom de route, une fonction qui fabrique les paramètres et la charge à partir d'un jardin donné ; liste `EXCEPTIONS` (santé, connexion, déconnexion, bibliothèque) ; `tests/Support/DeuxJardins.php` : deux jardins complets (zones, plantes avec marqueur `MARQUEUR-B-…` dans les noms de B), un utilisateur par jardin et un utilisateur qui a les deux.
**Tests d'abord :** `tests/Routes/CloisonnementTest.php` ; premier rouge attendu : « route jardins.basculer absente du registre ».
**Preuve :** rouge (une route volontairement fautive, écrite dans le test puis retirée, doit faire échouer le test : c'est le rouge exigé)
**Itérer sur / lancer une fois :** ce test / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion ; à partir d'ici, porte n° 1 de tous les sprints.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V41
**Fin :** le test parcourt toutes les routes existantes et échoue sur une route ajoutée sans inscription.

### 2d.1 Fixtures

- [ ] Implémenter `tests/Support/DeuxJardins.php` et les factories `UtilisateurFactory`, `JardinFactory` (avec profil), `ZoneFactory`, `PlanteFactory` (code court par l'action de 7a quand elle existera ; d'ici là un compteur de test). Toutes les coordonnées sont inventées (C4).
- [ ] Implémenter `tests/Support/RegistreDesRoutes.php` : entrées pour les routes de 2b et 2c ; chaque entrée déclare `lit|liste|ecrit`, et pour `ecrit` la charge valide.

### 2d.2 Le test

- [ ] Implémenter `tests/Routes/CloisonnementTest.php` : `Route::getRoutes()` ; toute route hors registre et hors `EXCEPTIONS` → échec nommant la route ; pour chaque entrée, connecté sur A : `lit` avec les identifiants de B → `404` ou `403`, corps sans `MARQUEUR-B` ni identifiant de B ; `liste` → corps sans `MARQUEUR-B` ni identifiant de B ; `ecrit` avec identifiants de B → empreinte de B (`md5` des lignes de chaque table de B, lues par Eloquent sans scope) identique avant et après. Groupe `critere-12`.

---

## Sprint 3a — Projection, décalage, butoirs
<!-- taille : 4 assertions, 4 fichiers, 3 classes de test -->

**Objectif :** une fenêtre jour/mois se projette sur la bonne année, se décale selon le profil et se rogne à ses butoirs, sans aucune dépendance au framework.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 3 §7 (R2, A7, A25, pseudo-code) ; CDC §4.2, §5.4, RG-25, RG-44, RG-45 ; chapitre 2 TS-4, TS-6 n° 1 et 2 ; décision D-09.
**Contrat :** `Fenetre` (jours et mois, optimale, butoirs facultatifs, libellé, ordre) ; `Decalage::jours(int): self`, `appliquer(\DateTimeImmutable): \DateTimeImmutable` ; `Periode {debut, fin, optimalDebut, optimalFin}` avec `contient()`, `estVide()` ; `Calendrier::date(int $annee, int $mois, int $jour)` et `Calendrier::prochaineFenetre(Fenetre, Decalage, \DateTimeImmutable): Periode` (D-23).
**Tests d'abord :** `tests/Moteur/ProjectionTest.php` ; premier rouge attendu : « classe Fenetre absente ».
**Preuve :** mutation (`make mutation` sur `src/Echeances/Moteur/`, lancé ici à titre de relevé, le seuil est posé au 3e)
**Itérer sur / lancer une fois :** `make moteur` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V42, V43, V44, V45
**Fin :** les projections de V42 à V45 sont exactes, en moins d'une seconde.

### 3a.1 Classes

- [ ] Créer le dossier `src/Echeances/Moteur/` et le dossier `tests/Moteur/`.
- [ ] Implémenter `src/Echeances/Moteur/Calendrier.php` : `date()` ramène le 29/02 au 28/02 des années ordinaires (D-09) ; aucune autre date impossible n'est acceptée (`\InvalidArgumentException`) ; tout en `\DateTimeImmutable` à minuit, fuseau `Europe/Paris` fixé à la construction (le moteur ne lit jamais l'horloge).
- [ ] Implémenter `src/Echeances/Moteur/Decalage.php` (`jours()`, `appliquer()`, `valeur()`), `src/Echeances/Moteur/Periode.php`, `src/Echeances/Moteur/Fenetre.php` (`traverseAnnee()`, `projeter()`).

**Cible — projection et rognage aux butoirs** (`Fenetre::projeter`) :

```php
public function projeter(int $anneeDeDebut, Decalage $decalage): Periode
{
    $anneeDeFin = $anneeDeDebut + ($this->traverseAnnee() ? 1 : 0);
    $debut = Calendrier::date($anneeDeDebut, $this->debutMois, $this->debutJour);
    $fin   = Calendrier::date($anneeDeFin,   $this->finMois,   $this->finJour);

    // 1. Décaler les deux bornes et l'optimale (RG-25, RG-26).
    $debut = $decalage->appliquer($debut);
    $fin   = $decalage->appliquer($fin);
    [$optDebut, $optFin] = $this->optimaleProjetee($anneeDeDebut, $decalage);

    // 2. Rogner ENSUITE : un butoir est une date fixe, jamais décalée (RG-44, A25).
    //    Butoir de début sur l'année de début non décalée, butoir de fin sur l'année de fin.
    if ($this->butoirDebut !== null) {
        $debut = max($debut, Calendrier::date($anneeDeDebut, $this->butoirDebut->mois, $this->butoirDebut->jour));
    }
    if ($this->butoirFin !== null) {
        $fin = min($fin, Calendrier::date($anneeDeFin, $this->butoirFin->mois, $this->butoirFin->jour));
    }

    // 3. L'optimale est contenue dans la fenêtre rognée (D-09).
    return Periode::rognee($debut, $fin, $optDebut, $optFin);
}
```

**Cible — projection sur trois années** (`Calendrier::prochaineFenetre`, chapitre 3 §7) :

```php
public static function prochaineFenetre(Fenetre $f, Decalage $d, \DateTimeImmutable $aujourdhui): Periode
{
    $annee = (int) $aujourdhui->format('Y');
    // L'ordre compte : année précédente d'abord, pour la fenêtre à cheval en cours (15/01 → déc. précédent).
    foreach ([$annee - 1, $annee, $annee + 1] as $anneeDeDebut) {
        $p = $f->projeter($anneeDeDebut, $d);
        if (! $p->estVide() && $aujourdhui <= $p->fin) {
            return $p;               // première candidate dont la fin n'est pas passée
        }
    }
    throw new \LogicException('Fenêtre vide trois années de suite');   // refusée en amont (B-T13, V104)
}
```

### 3a.2 Tests

- [ ] `tests/Moteur/ProjectionTest.php` : V42 (15/01/2027 et 15/06/2027) ; V45 (29/02 en 2028 et en 2027, fenêtre `01/02 → 29/02`).
- [ ] `tests/Moteur/DecalageTest.php` : V43, les cinq profils sur une même fenêtre, écart Nord–Midi = 22 jours sur les bornes et l'optimale. Groupe `critere-13`.
- [ ] `tests/Moteur/ButoirsTest.php` : V44, avec et sans butoir, Montagne et Midi ; optimale rognée. Groupe `critere-4`.

---

## Sprint 3b — Règle R2 : états, manquées, pluriannuel, explication
<!-- taille : 5 assertions, 6 fichiers, 4 classes de test -->

**Objectif :** `Moteur::calculer` rend, pour une règle R2 à une fenêtre, l'échéance à venir, son état du jour, ses manquées et son explication, avec une identité stable.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 3 §7 (signature, cascade, A10), CDC §4.2 (R2), §4.4, RG-06, RG-07, RG-31, RG-40, H11 ; chapitre 2 TS-6 ; décisions D-05, D-07, D-18.
**Contrat :**

```php
final class Moteur {
    /** @return Echeance[] */
    public static function calculer(array $regles, Historique $historique, Decalage $decalage, \DateTimeImmutable $aujourdhui): array;
}
```

`Regle {id, planteId, typeId, typeRegle: 'R1'|'R2', periodiciteAnnees, fenetres: Fenetre[], intervalles: IntervalleSaisonnier[], confiance, effetLe}` ; `Historique::derniere(planteId, typeId): ?\DateTimeImmutable`, `Historique::estClose(regleId, \DateTimeImmutable $dueLe): bool` ; `Echeance {regleId, dueLe, periode, etat: Etat, explication: Explication}` ; `Etat` enum `Manquee|AVenir|Urgente|Optimale|Ouverte` (D-18 : X = 15 jours) ; `Explication {typeRegle, periode|intervalle, decalageJours, confiance}` et `enPhrase(string $libelleType, string $nomPlante): string` (≤ 400 caractères).
**Tests d'abord :** `tests/Moteur/EtatsTest.php` ; premier rouge attendu : « classe Moteur absente ».
**Preuve :** mutation
**Itérer sur / lancer une fois :** `make moteur` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V46, V47, V48, V49, V50
**Fin :** les cas TS-6 n° 1, 2, 7 passent, chaque jour d'une saison a un seul état, et deux calculs rendent les mêmes identités.

### 3b.1 Classes

- [ ] Implémenter `src/Echeances/Moteur/Regle.php`, `Historique.php` (objet interrogeable, A10, construit à partir de tableaux simples), `Echeance.php`, `Etat.php`, `Explication.php`.
- [ ] Implémenter `src/Echeances/Moteur/Moteur.php` : pour chaque règle R2 active, `prochaineFenetre` ; si la dernière intervention tombe dans la période, la suivante ; périodicité `n > 1` : à partir de la fenêtre de la dernière intervention, sauter `n − 1` fenêtres, sans jamais les rendre manquées (V48) ; manquées : toute occurrence projetée dont la fin est passée, postérieure à `effetLe`, sans intervention dedans et non close dans l'historique (V47, D-05) ; `dueLe` = début de la période projetée, décalée et rognée (D-07) ; état par la cascade ordonnée du chapitre 3 §7. Les règles R1 lèvent `\LogicException('R1 au sprint 3d')` jusqu'au 3d.

**Cible — cascade d'état** (l'ordre est normatif) :

```php
private static function etat(Periode $p, \DateTimeImmutable $j, bool $faiteDansLaPeriode): Etat
{
    if ($j > $p->fin && ! $faiteDansLaPeriode)         return Etat::Manquee;   // 1
    if ($j < $p->debut)                                  return Etat::AVenir;    // 2
    if ($j >= $p->fin->modify('-' . self::X . ' days'))  return Etat::Urgente;   // 3 (X = 15, D-18)
    if ($p->dansOptimale($j))                            return Etat::Optimale;  // 4
    return Etat::Ouverte;                                                         // 5
}
```

### 3b.2 Tests

- [ ] Créer `tests/Moteur/Temoins/JeuTemoin.php` : fabriques de règles témoins (pommier `01/12 → 31/03`, optimale `01/02 → 15/03` ; cerisier `01/08 → 30/09`, optimale jusqu'au 31/08 ; haie `01/08 → 31/03` avec butoirs) — **jeux témoins, jamais copiés dans `bibliotheque/`**.
- [ ] `tests/Moteur/EtatsTest.php` : V46, chaque jour du 01/09/2026 au 30/04/2027, un seul état et l'ordre de la cascade.
- [ ] `tests/Moteur/ManqueeTest.php` : V47 (01/04/2027 ; règle créée en cours de fenêtre → aucune manquée). Groupe `critere-6`.
- [ ] `tests/Moteur/PluriannuelTest.php` : V48.
- [ ] `tests/Moteur/IdentiteEtExplicationTest.php` : V49 (deux calculs, mêmes couples) ; V50 (phrase avec décalage non nul, sans mention pour la plaine, caractère indicatif, ≤ 400 caractères). Groupes `critere-3`, `critere-13`.

---

## Sprint 3c — Fenêtres multiples et occurrences closes
<!-- taille : 4 assertions, 4 fichiers, 3 classes de test -->

**Objectif :** une règle à plusieurs fenêtres rend une seule échéance à venir, toutes ses manquées, et ne repropose jamais une occurrence close.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 3 §7 (A8) ; CDC RG-36, §4.4, H9 ; décision D-05.
**Contrat :** inchangé (`Moteur::calculer`) ; nouvelle classe `src/Echeances/Moteur/ChoixDeFenetre.php` (`prochaine(Regle, Historique, Decalage, \DateTimeImmutable): array{0: Fenetre, 1: Periode}`).
**Tests d'abord :** `tests/Moteur/FenetresMultiplesTest.php` ; premier rouge attendu : « deux échéances à venir rendues au lieu d'une ».
**Preuve :** mutation
**Itérer sur / lancer une fois :** `make moteur` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V51, V52, V53, V54
**Fin :** les cas V51 à V54 passent, et les tests du 3b restent verts.

### 3c.1 Choix de fenêtre

- [ ] Implémenter `src/Echeances/Moteur/ChoixDeFenetre.php` ; modifier `Moteur.php` (appel), `Regle.php` (fenêtres triées par `ordre`), `Historique.php` (`estClose`).

**Cible — choix parmi des fenêtres multiples** (A8) :

```php
public static function prochaine(Regle $r, Historique $h, Decalage $d, \DateTimeImmutable $aujourdhui): array
{
    $derniere = $h->derniere($r->planteId, $r->typeId);
    $candidates = [];
    foreach ($r->fenetres as $f) {                       // 1. chaque fenêtre est projetée
        $p = Calendrier::prochaineFenetre($f, $d, $aujourdhui);
        // 2. une fenêtre déjà soldée dans sa propre période, ou close (suspendue, manquée), passe à son occurrence suivante
        while (($derniere !== null && $p->contient($derniere)) || $h->estClose($r->id, $p->debut)) {
            $p = Calendrier::prochaineFenetre($f, $d, $p->fin->modify('+1 day'));
        }
        $candidates[] = [$f, $p];
    }
    // 3. la plus proche ; à égalité, l'ordre déclaré de la fenêtre
    usort($candidates, fn ($a, $b) => [$a[1]->debut, $a[0]->ordre] <=> [$b[1]->debut, $b[0]->ordre]);
    return $candidates[0];                               // 4. une seule échéance au total
}
```

### 3c.2 Tests

- [ ] `tests/Moteur/FenetresMultiplesTest.php` : V51, V52 (fenêtres témoins novembre et février).
- [ ] `tests/Moteur/OccurrencesClosesTest.php` : V53 (suspendue, puis manquée matérialisée).
- [ ] `tests/Moteur/HorsFenetreTest.php` : V54 (taille de juin).

---

## Sprint 3d — Règle R1 saisonnière et report hors saison
<!-- taille : 4 assertions, 4 fichiers, 3 classes de test -->

**Objectif :** une tonte suit l'intervalle de sa saison, ne tombe jamais hors saison, et le décalage déplace la saison sans toucher l'intervalle.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 3 §7 (R1, A24, pseudo-code) ; CDC §4.2 (R1), RG-25, critères 5 et 15 ; chapitre 2 TS-6 n° 5 et 9 ; décision D-10.
**Contrat :** `IntervalleSaisonnier {moisDebut, moisFin, intervalleJours}` ; `Regle::intervalleDe(\DateTimeImmutable, Decalage): int` (bornes de saison = 1er du mois de début, dernier jour du mois de fin, décalées sans butoir ; valeur jamais décalée) ; `Regle::ouvertureSuivante(\DateTimeImmutable, Decalage): \DateTimeImmutable`.
**Tests d'abord :** `tests/Moteur/TonteTest.php` ; premier rouge attendu : « LogicException R1 au sprint 3d ».
**Preuve :** mutation
**Itérer sur / lancer une fois :** `make moteur` / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V55, V56, V57, V58
**Fin :** les cas TS-6 n° 5 et 9 passent ; aucune tonte n'est due en janvier.

### 3d.1 R1

- [ ] Implémenter `src/Echeances/Moteur/IntervalleSaisonnier.php` ; modifier `Regle.php` (`intervalleDe`, `ouvertureSuivante`, garde : une règle R1 sans aucune période d'intervalle > 0 lève `\InvalidArgumentException` à la construction), `Calendrier.php` (dernier jour du mois), `Moteur.php` (branche R1 ; `dueLe` = date due ; état : `AVenir` avant, `Ouverte` le jour même et après — la cascade R2 ne s'applique pas).

**Cible — report hors saison** (`Moteur::prochaineR1`, chapitre 3 §7, étapes 1 à 4, avec D-10) :

```php
private static function prochaineR1(Regle $r, ?\DateTimeImmutable $derniere, Decalage $d, \DateTimeImmutable $aujourdhui): \DateTimeImmutable
{
    if ($derniere === null) {
        $due = $aujourdhui;                                        // 1. jamais faite
    } else {
        $i = $r->intervalleDe($derniere, $d);                      // 2. intervalle de la saison de la dernière
        $due = $i === 0 ? $derniere : $derniere->modify("+{$i} days");
    }
    for ($tours = 0; $r->intervalleDe($due, $d) === 0; $tours++) { // 3. hors saison : jamais (critère 15, A24),
        if ($tours > 12) { throw new \LogicException('aucune saison active'); }   //    appliqué aussi au cas 1 (D-10)
        $due = $r->ouvertureSuivante($due, $d);                    //    premier jour de la période active suivante
    }
    if ($due < $aujourdhui) {                                      // 4. en retard
        $due = $r->intervalleDe($aujourdhui, $d) > 0 ? $aujourdhui : $r->ouvertureSuivante($aujourdhui, $d);
    }
    return $due;
}
```

### 3d.2 Tests

- [ ] `tests/Moteur/TonteTest.php` : V55 (jeu témoin 7 / 10 / 0). Groupe `critere-5`.
- [ ] `tests/Moteur/HorsSaisonTest.php` : V56 (15/11, 15/12, aucune tonte en janvier) ; V58 (en retard, saison ouverte puis fermée). Groupe `critere-15`.
- [ ] `tests/Moteur/DecalageSaisonTest.php` : V57 (Midi : saison ouverte quinze jours plus tôt, intervalle 7 inchangé).

---

## Sprint 3e — Balayages calendaires et mutation
<!-- taille : 3 assertions, 5 fichiers, 3 classes de test -->

**Objectif :** des invariants vérifiés chaque jour de deux années pour cinq profils, et un seuil de mutation qui ne fait que monter.
**Constructeur :** back
**Risque :** léger (tests seuls, réglage d'outil)
**Lecture :** chapitre 6 §6.2 (C9, C10, C11), O16 ; chapitre 2 TS-8 ; CDC critère 4.
**Contrat :** groupe Pest `balayage` exclu de `make test`, lancé par `make balayage` ; `tests/mutation-seuil.txt` (un entier, le pourcentage) ; `make mutation` échoue sous le seuil.
**Tests d'abord :** `tests/Moteur/Balayage/BalayageAnnuelTest.php` ; premier rouge attendu : aucun (le moteur existe) — le rouge exigé est une mutation introduite à la main dans `Fenetre::projeter` (inversion du `max`) qui doit faire échouer le balayage.
**Preuve :** mutation
**Itérer sur / lancer une fois :** `make mutation` par classe (`--class=`) / `make mutation` entier et `make balayage`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V59, V60, V61
**Fin :** les balayages passent sur 2027 et 2028 ; le seuil versionné est la valeur mesurée ; `make moteur` reste sous la seconde.
**Coût :** boucle lente (mutation sur tout le moteur) et instrument nouveau (seuil versionné) → **accepter**, en itérant par classe ; l'instrument a été essayé au 0e (V13).

### 3e.1 Balayages

- [ ] `tests/Moteur/Balayage/BalayageAnnuelTest.php` : les cinq invariants de V59, chaque jour de 2027 et 2028, cinq profils, règles du jeu témoin. Groupe `balayage`.
- [ ] `tests/Moteur/NidificationTest.php` : V60, cinq profils, 01/04 → 31/07. Groupe `critere-4` (dans `make test`, il est court).
- [ ] Modifier `tests/Pest.php` et `phpunit.xml` (groupe `balayage` exclu par défaut), `Makefile` (`balayage`, `moteur` = `tests/Moteur` sans `balayage`, avec mesure du temps : échec au-delà d'une seconde).

### 3e.2 Mutation

- [ ] Lancer `make mutation` sur `src/Echeances/Moteur/` ; tuer les survivantes par des tests (pas par des exclusions) ; écrire la valeur obtenue dans `tests/mutation-seuil.txt` et dans `decisions-agents.md` (O16).
- [ ] `tests/Architecture/SeuilMutationTest.php` : le seuil versionné est ≥ à celui de `main` (lu par `git show origin/main:tests/mutation-seuil.txt`, absent = 0) (V61).
- [ ] Retirer `src/Support/Temoin/` et son test (créés au 0e).

---

## Sprint 4a — Chargement de la bibliothèque
<!-- taille : 4 assertions, 8 fichiers, 3 classes de test -->

**Objectif :** les fichiers de `bibliotheque/` se chargent en base de façon idempotente et versionnée, leur structure est testée, et une espèce rend ses règles proposées calées pour le jardin.
**Constructeur :** back
**Risque :** complet
**Lecture :** protocole de bibliothèque §1 (B1–B8), §3 (B-T4 à B-T11) ; chapitre 4 §3.3, §5 ; chapitre 6 §6.7 ; CDC RG-24, RG-39 ; décision D-19.
**Contrat :** format YAML (D-19), un fichier par catégorie, schéma :

```yaml
# bibliotheque/<categorie>.yaml — les valeurs viennent du protocole, jamais d'ici
especes:
  - cle: <identifiant stable>
    nom_commun: …
    nom_latin: …
    famille: …
    categorie: …
    regles:
      - cle: <stable>
        type_intervention: <libellé fourni>
        type_regle: R2            # ou R1
        confiance: verifiee        # ou indicative
        sources: [<url>, <url>]
        consultees_le: AAAA-MM-JJ
        fenetres: [{debut: JJ/MM, fin: JJ/MM, optimal: [JJ/MM, JJ/MM], butoirs: [JJ/MM, JJ/MM], libelle: …}]
        intervalles: [{mois_debut: M, mois_fin: M, jours: N}]
        periodicite_annees: N
```

`php artisan bibliotheque:charger` ; `GET /especes?q=` → JSON ; `GET /especes/{espece}/regles-proposees` → JSON (B7).
**Tests d'abord :** `tests/Persistance/ChargementBibliothequeTest.php` sur un dossier de fichiers témoins ; premier rouge attendu : « commande bibliotheque:charger absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V62, V63, V64, V65
**Fin :** un fichier témoin se charge, se recharge sans rien changer, et une modification crée une version suivante sans toucher aucun jardin.

### 4a.1 Chargement

- [ ] Créer le dossier `bibliotheque/` (vide de valeurs : un `LISEZMOI.md` qui renvoie au protocole) et le dossier `src/Bibliotheque/Chargement/`.
- [ ] Implémenter `src/Bibliotheque/Chargement/LireBibliotheque.php` : lit et valide (structure, dates `JJ/MM` réelles, confiance, au moins une source si `verifiee`) ; une erreur → exception qui nomme fichier et chemin de la clé ; rien n'est écrit.
- [ ] Implémenter `src/Bibliotheque/Chargement/ChargerBibliotheque.php` (commande) : transaction ; `upsert` des familles et espèces sur leur clé ; pour chaque règle, compare `parametres` à la version en vigueur : identique → rien ; différent → nouvelle ligne `version + 1`, l'ancienne reçoit `remplacee_le` ; ne touche jamais `regles`, `fenetres`, `echeances` (RG-24).
- [ ] Implémenter `src/Bibliotheque/Domaine/Espece.php`, `FamilleBotanique.php`, `RegleReference.php` (référentiel, sans trait de jardin, lecture seule hors chargement).

### 4a.2 Lecture

- [ ] Implémenter `src/Bibliotheque/Especes/RechercheEspece.php` (nom commun ou latin, insensible à la casse et aux accents par `unaccent` déclarée en migration — ou `ILIKE` si l'extension n'est pas disponible dans les deux environnements, à écrire), `src/Bibliotheque/Especes/EspeceController.php` (`rechercher`, `reglesProposees` : règles en vigueur avec les périodes projetées par `Fenetre::projeter` pour le profil du jardin courant et l'année courante, et la confiance), `src/Bibliotheque/routes.php`. Ajouter les deux routes à `EXCEPTIONS` du test de cloisonnement, avec la raison.

### 4a.3 Tests

- [ ] `tests/Persistance/ChargementBibliothequeTest.php` : V62 (deux chargements, aucune ligne modifiée ; fichier mal formé refusé en entier) ; V63 (version 2, `remplacee_le`, empreintes de `regles` / `fenetres` / `echeances` d'un jardin témoin inchangées).
- [ ] `tests/Persistance/StructureBibliothequeTest.php` : B-T4 à B-T9, B-T11 et B2 sur les fichiers de `bibliotheque/` ; chacun a un cas témoin fautif qui échoue (V64).
- [ ] `tests/Routes/ReglesProposeesTest.php` : V65 (deux jardins de profils différents, périodes décalées ; aucune route en écriture sous `/especes`).

---

## Sprint 4b — Espèces sourcées et tests du protocole
<!-- taille : 4 assertions, 7 fichiers, 4 classes de test -->

**Objectif :** la bibliothèque contient les espèces déjà sourcées du CDC §5.1, chaque règle sourcée selon le protocole, et les tests B-T1 à B-T3 et B-T12 à B-T14 passent sur elle.
**Constructeur :** back (avec recherche documentaire)
**Risque :** complet
**Lecture :** protocole de bibliothèque en entier ; CDC §5.1 (liste de départ), annexe A ; critère 4.
**Contrat :** fichiers `bibliotheque/fruitiers.yaml`, `haies.yaml`, `ornement.yaml`, `pelouse.yaml`, `traitements.yaml` ; `make sources`.
**Tests d'abord :** `tests/Persistance/CoherenceBibliothequeTest.php` sur des fichiers témoins fautifs ; premier rouge attendu : « aucun fruitier à noyau trouvé ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les tests de la bibliothèque / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V66, V68, V69, V70
**Fin :** pommier, poirier, prunier, cerisier, haie persistante, haie caduque, rosier, arbuste à floraison estivale, pelouse et traitements fruitiers sont chargés, chaque valeur venant d'une source consultée dans la session (B4).
**Coût :** recherche documentaire lente et jugement de qualité, comportement jamais observé (la projection des cinq profils sur des valeurs réelles) → **couper** : les cinq espèces à sourcer passent au 4c ; ici, seules celles que le CDC dit déjà sourcées.

### 4b.1 Contenu

- [ ] Pour chaque espèce, consulter les sources (B1–B4) pendant la session, écrire `sources` et `consultees_le` ; une valeur sans source vérifiable n'est pas écrite (la règle reste absente, et c'est écrit dans `decisions-agents.md`). **Aucune valeur de mémoire, aucune valeur reprise d'un test.** Toute haie porte les butoirs `01/08` et `31/03` (B7, RG-44).
- [ ] Créer les cinq fichiers YAML ; lancer `bibliotheque:charger` sur une base vide.

### 4b.2 Sources

- [ ] Implémenter `src/Bibliotheque/Console/VerifierSources.php` : `HEAD` puis `GET` sur chaque `source_url`, délai 10 s, liste des URL en échec ; cible `make sources`. Modifier `tests/Architecture/WorkflowTest.php` : aucune étape de la CI n'appelle `make sources` (V66).

### 4b.3 Tests

- [ ] `tests/Persistance/CoherenceBibliothequeTest.php` : B-T1, B-T2, B-T3 (V68).
- [ ] `tests/Persistance/ClimatsBibliothequeTest.php` : B-T12, B-T13, B-T14, par `Fenetre::projeter` (V69).
- [ ] `tests/Persistance/NidificationBoutEnBoutTest.php` : V70 — chargée, copiée sur une plante de cinq jardins témoins, calcul complet chaque jour du 01/04 au 31/07 (nécessite le pont du sprint 5 : **ce test est écrit ici en `todo()` et activé au sprint 5**, case à cocher là-bas). Groupe `critere-4`.

---

## Sprint 4c — Espèces à sourcer
<!-- taille : 1 assertion, 3 fichiers, 1 classe de test -->

**Objectif :** pêcher, vigne, framboisier, groseillier et arbuste à floraison printanière entrent dans la bibliothèque avec leurs sources, ou en sortent avec la raison.
**Constructeur :** back (recherche documentaire)
**Risque :** léger (données et tests seuls)
**Lecture :** protocole de bibliothèque ; CDC §5.1 (espèces à sourcer), annexe A.
**Contrat :** ajouts dans `fruitiers.yaml` et `ornement.yaml` ; tableau de complétude B-T10.
**Tests d'abord :** `tests/Persistance/CompletudeBibliothequeTest.php` ; premier rouge attendu : « pêcher sans règle ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les tests de la bibliothèque / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune — la liste de départ est fixée par le CDC ; une espèce retirée faute de source est une décision écrite, pas une question
**Assertions :** V67
**Fin :** chaque espèce de la liste de départ a une règle sourcée, ou figure dans `decisions-agents.md` avec la raison de son retrait.
**Coût :** recherche documentaire, résultat incertain (l'espèce peut sortir) → **accepter**, le sprint est petit et n'en bloque aucun autre.

- [ ] Sourcer chaque espèce selon B1–B4 ; ajouter les règles ; arbuste à floraison printanière en R2 (CDC §5.1).
- [ ] `tests/Persistance/CompletudeBibliothequeTest.php` : la liste de départ, lue dans le test, a pour chaque espèce soit une règle chargée, soit une entrée `retiree:` dans `bibliotheque/retraits.yaml` avec sa raison (B-T10).

---

## Sprint 5 — Le pont : calcul des échéances en base
<!-- taille : 5 assertions, 8 fichiers, 4 classes de test -->

**Objectif :** les échéances d'un jardin se calculent depuis la base en un nombre fixe de requêtes, et les manquées se matérialisent au calcul.
**Constructeur :** back
**Risque :** complet
**Lecture :** chapitre 3 §6 (pont `CalculEcheances`), §7 (A9), §12 (aucun recalcul planifié) ; modèle de données D3 ; CDC §9 points 5 bis et 9, RG-07, RG-19, RG-26.
**Contrat :** reprise du chapitre 3 :

```php
final class CalculEcheances {
  public function pourJardin(Jardin $jardin): Collection;   // de EcheanceCalculee {echeance, regle, plante, type}
}
```

Fichier `src/Echeances/Calcul/CalculEcheances.php` (nom de la classe, PSR-4). `MaterialiserManquees::executer(Collection $calculees): int`.
**Tests d'abord :** `tests/Persistance/CalculEcheancesTest.php` ; premier rouge attendu : « classe CalculEcheances absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les quatre classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V71, V72, V73, V74, V75
**Fin :** le critère 13 est vrai depuis la base, et une manquée s'écrit une seule fois.

### 5.1 Modèles

- [ ] Implémenter `src/Echeances/Domaine/Regle.php` (trait de jardin ; `fenetres()`, `intervalles()` ; `versDomaine(): Moteur\Regle`, `effetLe` = `cree_le` de la version), `Fenetre.php`, `IntervalleSaisonnier.php`, `EcheanceMaterialisee.php` (table `echeances`), `src/Journal/Domaine/Intervention.php`, `src/Bibliotheque/Domaine/TypeIntervention.php`.

### 5.2 Pont

- [ ] Implémenter `src/Echeances/Calcul/CalculEcheances.php` : `chargerRegles` (règles actives des plantes actives du jardin, avec fenêtres et intervalles par `with`) ; `chargerHistorique` : une requête `Intervention::query()->select(['plante_id','type_intervention_id','faite_le'])->distinct(['plante_id','type_intervention_id'])->orderBy('plante_id')->orderBy('type_intervention_id')->orderByDesc('faite_le')` (A9, `DISTINCT ON` par le grammaire PostgreSQL de Laravel, sans SQL brut), et une requête des échéances matérialisées (closes) ; le décalage vient de `profilClimatique->decalage_jours` ; `aujourdhui` = `CarbonImmutable::today('Europe/Paris')` converti, le seul endroit qui lit l'horloge.
- [ ] Implémenter `src/Echeances/Calcul/MaterialiserManquees.php` : pour chaque échéance `Manquee` rendue, `EcheanceMaterialisee::query()->insertOrIgnore([...])` avec `jardin_id`, `due_le`, période, `decalage_climatique_applique_j`, `etat = manquee`, `explication = enPhrase(...)` ; idempotent par `(regle_id, due_le)`.

### 5.3 Tests

- [ ] `tests/Persistance/CalculEcheancesTest.php` : V71 (`DB::enableQueryLog`, 5 puis 500 plantes, même nombre ≤ 6) ; V74 (deux jardins, même nom de plante, même type).
- [ ] `tests/Persistance/DeuxClimatsTest.php` : V72. Groupe `critere-13`.
- [ ] `tests/Persistance/ManqueesTest.php` : V73 (`travelTo` 01/04/2027 ; deux calculs, une ligne). Groupe `critere-6`.
- [ ] `tests/Persistance/ChangementDeProfilTest.php` : V75.
- [ ] Activer `tests/Persistance/NidificationBoutEnBoutTest.php` (`todo()` du 4b) (V70).

---

## Sprint 6 — Tableau de bord et fiche de plante
<!-- taille : 5 assertions, 8 fichiers, 4 classes de test -->

**Objectif :** le tableau de bord du bureau, la liste « à faire » du terrain et la fiche d'une plante rendent leurs données, groupées, expliquées et cloisonnées, à toute date.
**Constructeur :** back
**Risque :** complet
**Lecture :** `conception-ecrans.md` §4 (B4, B6, T1, T5) et §6 ; CDC §3.9, §8.7, critères 3, 6, 11 ; RG-06, RG-19, RG-40.
**Contrat :** `GET /` → `Bureau/TableauDeBord` (B4) props `{aujourdhui, semaine, mois, planning}` ; chaque item `{cle: {regle_id, due_le}, plante {id, nom, code_court, zone}, type {id, libelle}, etat, periode, explication}` ; au-delà de 10 items d'un même (zone, type), un groupe `{zone, type, nombre}` ; `GET /echeances/groupe?zone=&type=&periode=` → JSON (détail) ; `GET /terrain` → `Terrain/AFaire` (T1), mêmes items du jour, par zone dans l'ordre de tournée ; `GET /plantes/{plante}` → `Bureau/FichePlante` (B6) et `Terrain/FichePlante` (T5) selon `?contexte=terrain` ; `GET /plantes/{plante}/historique?page=` → JSON, 20 par page.
**Tests d'abord :** `tests/Routes/TableauDeBordTest.php` ; premier rouge attendu : « route / absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les quatre classes / `make verifier`, et `make balayage` (groupe `volume`).
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet (les écrans B4, T1, B6, T5 sont en partie B)
**Question UX possible :** aucune — le seuil de groupement (10) est une valeur technique de chargement, réglable par la partie B quand les maquettes de B4 seront validées ; écrit en D-24
**Assertions :** V76, V77, V78, V79, V80
**Fin :** à trois dates figées, le tableau de bord rend les bonnes échéances ; à 500 plantes, il tient son seuil.
**Coût :** jeu de 500 plantes et 1 500 règles à chaque essai, boucle lente → **accepter**, en itérant sur un jeu de 20 plantes et en lançant le test de volume une fois (groupe `volume`, lancé par `make balayage`, D-25).

### 6.1 Lectures

- [ ] Implémenter `src/Echeances/TableauDeBord/RegrouperEcheances.php` : à partir de `CalculEcheances::pourJardin` puis `MaterialiserManquees`, range en aujourd'hui (`etat ∈ {ouverte, optimale, urgente}` et R1 due ≤ aujourd'hui), semaine, mois, planning (le reste des `a_venir`, par mois) ; groupe au-delà de 10.
- [ ] Implémenter `src/Echeances/TableauDeBord/TableauDeBordController.php` (`bureau`, `terrain`) et `src/Echeances/TableauDeBord/DetailGroupeController.php` ; `src/Echeances/routes.php`.
- [ ] Implémenter `src/Jardin/Plantes/PlanteController.php` (`afficher` : plante par le scope, `404` sinon ; échéances à venir et manquées de ses règles) ; `src/Journal/Historique/HistoriqueController.php` ; `src/Jardin/routes.php`, `src/Journal/routes.php`.
- [ ] Inscrire les cinq routes au registre du test de cloisonnement.

### 6.2 Tests

- [ ] `tests/Routes/TableauDeBordTest.php` : V76 (trois dates `travelTo`, sans session, autre jardin, relecture : seules des manquées écrites) ; V80. Groupe `critere-3`.
- [ ] `tests/Routes/AgregationTest.php` : V77 (14 tailles dans une zone). Groupe `critere-11`.
- [ ] `tests/Routes/FichePlanteTest.php` : V78 (dont manquée visible). Groupe `critere-6`.
- [ ] `tests/Routes/Volume500Test.php` : V79 (nombre de requêtes constant, temps mesuré, seuil écrit dans `decisions-agents.md`). Groupes `volume`, `critere-11`.

---

## Sprint 7a — Zones, création de plante, code court
<!-- taille : 4 assertions, 7 fichiers, 3 classes de test -->

**Objectif :** déclarer des zones et créer une plante qui reçoit son code court et une copie des règles de son espèce.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-09, RG-13, RG-39, §3.9, parcours P0 et P4 ; chapitre 4 S8 ; chapitre 3 §2 (Inventaire, Identification, `AppliquerReglesTypes`) ; décision D-03.
**Contrat :** `POST /zones {nom}` ; `PUT /zones/{zone} {nom}` (B3) ; `POST /plantes {nom, zone_id, espece_id?, repere?, variete?, plantee_le?, regles_ecartees?: string[], ajustements?: {<cle_regle>: {...}}}` → `302 /plantes/{id}` ou JSON `201 {id, code_court}` (B7, T3) ; `AttribuerCodeCourt::pour(?Espece): string` ; `AppliquerReglesTypes::executer(Plante, Espece, array $ecartees, array $ajustements): void`.
**Tests d'abord :** `tests/Persistance/CodeCourtTest.php` ; premier rouge attendu : « classe AttribuerCodeCourt absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V81, V82, V83, V84
**Fin :** une plante créée porte un code jamais réattribué et les règles de son espèce, au `jardin_id` du jardin courant.

### 7a.1 Code court

- [ ] Implémenter `src/Jardin/Domaine/CompteurCodeCourt.php` (trait de jardin, clé composée) et `src/Jardin/Identification/AttribuerCodeCourt.php` : préfixe = trois premières lettres de `especes.categorie` sans accent, en majuscules (`PLA` sans espèce) (D-03) ; `CompteurCodeCourt::query()->upsert(...)` puis relecture `lockForUpdate` dans la transaction de création, `dernier + 1` ; format `PREFIXE-NNN` (3 chiffres minimum). Jamais `COUNT`.

### 7a.2 Zones et plantes

- [ ] Implémenter `src/Jardin/Zones/ZoneController.php` (`creer`, `renommer`) : validation `nom` requis, 1 à 120, `unique:zones,nom` **restreint au jardin courant** ; zone par le scope, `404` sinon.
- [ ] Implémenter `src/Jardin/Plantes/CreerPlante.php` : transaction ; zone et espèce vérifiées (zone par le scope) ; `AttribuerCodeCourt` ; `Plante::create` ; si espèce, `AppliquerReglesTypes`.
- [ ] Implémenter `src/Bibliotheque/ReglesTypes/AppliquerReglesTypes.php` : pour chaque règle de référence en vigueur non écartée, crée `regles` (version 1, active, `regle_reference_id`, confiance) puis ses `fenetres` et `intervalles_saisonniers` depuis `parametres`, ajustements appliqués ; tout au `jardin_id` courant.
- [ ] Modifier `src/Jardin/Plantes/PlanteController.php` (`creer`) et `src/Jardin/routes.php` ; inscrire les routes au registre du test de cloisonnement.

### 7a.3 Tests

- [ ] `tests/Persistance/CodeCourtTest.php` : V81 (séquence, archivage puis création : code nouveau ; deux créations concurrentes par deux connexions : codes distincts).
- [ ] `tests/Routes/ZonesTest.php` : V82, les quatre cas.
- [ ] `tests/Routes/CreerPlanteTest.php` : V83 (les quatre cas) et V84.

---

## Sprint 7b — Création en lot et archivage
<!-- taille : 4 assertions, 6 fichiers, 2 classes de test -->

**Objectif :** créer 150 plantes en une demande, tout ou rien, et archiver une plante sans rien perdre.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC §8.7, parcours P0, critères 1 et 24, RG-02, §2.1 (rôles) ; chapitre 3 §2 (`ImporterEnLot`, `ArchiverPlante`).
**Contrat :** `POST /plantes/lot {lignes: [{nom, zone_id, espece_id?, quantite?: 1..50, repere?}]}` (max 500 lignes) → `302 /plantes` ou `422` avec erreurs `lignes.N.<champ>` (B3) ; `POST /plantes/{plante}/archiver {motif}` (propriétaire).
**Tests d'abord :** `tests/Routes/ImportEnLotTest.php` ; premier rouge attendu : « route /plantes/lot absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les deux classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** **Q-UX-2** (une ligne fautive bloque-t-elle tout le lot ?) et **Q-UX-3** (comment nommer les douze plantes d'une ligne « 12 pommiers ») ; V85 et V86 en dépendent. Les questions sont posées dès l'écriture du plan.
**Assertions :** V85, V86, V87, V88
**Fin :** 150 lignes créent 150 plantes en moins de 5 s ; une ligne fautive n'en crée aucune ; une plante archivée garde son histoire.

### 7b.1 Lot

- [ ] Implémenter `src/Jardin/Inventaire/ImporterEnLot.php` : valide toutes les lignes d'abord (zones par le scope, espèces, noms uniques dans la zone **et** dans le lot, noms produits par la quantité selon Q-UX-3) ; puis une transaction qui appelle `CreerPlante` par plante ; une erreur → rien d'écrit (selon Q-UX-2 ; recommandation : tout ou rien) ; règles de référence chargées une fois par espèce pour le lot.
- [ ] Implémenter `src/Jardin/Inventaire/ImportController.php` ; modifier `src/Jardin/routes.php`.

### 7b.2 Archivage

- [ ] Implémenter `src/Jardin/Plantes/ArchiverPlante.php` : `etat = archivee`, `archivee_le`, motif ; règles `est_active = false` sans `remplacee_le` (D-22) ; code court conservé ; le calcul ignore les plantes archivées (déjà dans `chargerRegles`).
- [ ] Implémenter `src/Jardin/Policies/PlantePolicy.php` (`archiver`, `modifier` : propriétaire) ; modifier `PlanteController` (`archiver`) ; inscrire les routes au registre.

### 7b.3 Tests

- [ ] `tests/Routes/ImportEnLotTest.php` : V85 (150 lignes sur 6 zones, temps mesuré ; ligne `quantite: 12`), V86, V87. Groupes `critere-1`, `critere-24`.
- [ ] `tests/Routes/ArchiverPlanteTest.php` : V88, les quatre cas.

---

## Sprint 7c — Liste, modification en masse, types d'intervention, zones archivées
<!-- taille : 4 assertions, 8 fichiers, 4 classes de test -->

**Objectif :** une liste de 500 plantes qui ne déborde jamais, des modifications en masse cloisonnées, un catalogue de types extensible, et l'archivage des zones.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC §8.7, RG-03, critère 11 ; `conception-ecrans.md` (B5, B11, T2) ; décision D-04.
**Contrat :** `GET /plantes?q=&zone=&etat=&page=` → `Bureau/Plantes` (B5), 50 par page, `total` ; `PUT /plantes/lot {ids: uuid[], zone_id?, repere?}` ; `GET /types-intervention` → JSON `{recents: [5], tous: [...]}` (T2, B11) ; `POST /types-intervention {libelle, famille, demande_quantite?, demande_produit?}` ; `POST /zones/{zone}/archiver`.
**Tests d'abord :** `tests/Routes/ListePlantesTest.php` ; premier rouge attendu : « route /plantes absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les quatre classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** **Q-UX-6** (archiver une zone qui contient des plantes actives) ; V92 en dépend.
**Assertions :** V89, V90, V91, V92
**Fin :** les quatre routes passent leurs quatre cas, et la liste rend 50 lignes à 500 plantes.

### 7c.1 Plantes

- [ ] Modifier `src/Jardin/Plantes/PlanteController.php` (`lister`) : recherche par nom (`ILIKE`) ou code court exact (insensible à la casse), filtres, pagination, miniature de couverture (nulle avant 11b).
- [ ] Implémenter `src/Jardin/Plantes/ModifierEnMasse.php` : charge les `ids` par le scope ; un id absent → `422`, rien ne change ; transaction ; noms uniques dans la zone d'arrivée vérifiés avant écriture.

### 7c.2 Types

- [ ] Implémenter `src/Bibliotheque/TypesIntervention/TypeInterventionController.php` (`lister`, `creer`) et `src/Bibliotheque/TypesIntervention/CatalogueTypes.php` : `TypeIntervention` n'a pas le trait (fournis à `jardin_id` NULL) ; un scope local `visibles()` = `jardin_id IS NULL OR jardin_id = JardinCourant::id()`, appliqué par **toute** lecture, vérifié par un test d'architecture ajouté à `ModelesCloisonnesTest` ; `recents` = les 5 types des interventions les plus récentes du jardin ; libellé unique parmi les visibles ; un type fourni n'est pas modifiable (aucune route de modification).
- [ ] Modifier `src/Bibliotheque/routes.php` (ces routes ne sont **pas** dans les exceptions du cloisonnement).

### 7c.3 Zones

- [ ] Implémenter `src/Jardin/Zones/ArchiverZone.php` : `archivee_le = now()` ; comportement avec plantes actives selon Q-UX-6 (recommandation : refus avec le nombre de plantes) ; modifier `ZoneController` et les listes de zones (`whereNull('archivee_le')`). Inscrire les routes au registre.

### 7c.4 Tests

- [ ] `tests/Routes/ListePlantesTest.php` : V89 (500 plantes, 50 lignes, total, code exact). Groupe `critere-11`.
- [ ] `tests/Routes/MasseTest.php` : V90, les quatre cas.
- [ ] `tests/Routes/TypesInterventionTest.php` : V91, les quatre cas, ordre des récents.
- [ ] `tests/Routes/ArchiverZoneTest.php` : V92.

---

## Sprint 8a — Enregistrer une intervention, idempotente
<!-- taille : 5 assertions, 6 fichiers, 3 classes de test -->

**Objectif :** une intervention s'enregistre sur une ou plusieurs plantes avec un identifiant fourni par le client, et son renvoi ne crée jamais de doublon ni de fuite.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-04, RG-05, RG-50, §3.8, critère 2 ; chapitre 1 H5, H9 ; chapitre 4 S11 ; décisions D-06, D-08, D-15.
**Contrat :** `POST /interventions` (T2, T3, T4, B8), JSON :

```json
{ "lot_saisie_id": "uuid|null", "type_intervention_id": "uuid",
  "faite_le": "AAAA-MM-JJ", "note": "≤ 2000|null", "duree_min": "1..1440|null",
  "position": {"lng": 0, "lat": 0, "precision_m": 0} ,
  "lignes": [{"id": "uuid", "plante_id": "uuid"}] }
```

→ `200 {interventions: [{id, plante_id, type_intervention_id, faite_le, note, duree_min, auteur_id}]}` : **l'état stocké**, identique au premier envoi et aux renvois ; `422` avec erreurs `lignes.N` « Plante introuvable dans ce jardin. » pour une plante absente **comme** pour un identifiant d'intervention existant dans un autre jardin (D-08). Une seule ligne = intervention simple ; plusieurs = `lot_saisie_id` requis (D-06).
**Tests d'abord :** `tests/Routes/IdempotenceTest.php` ; premier rouge attendu : « route /interventions absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** **Q-UX-4** (date d'intervention dans le futur) ; seul le dernier cas de V97 en dépend : sans réponse, la tâche 8a.1 « date future » est laissée non cochée et le sprint est mis de côté **après** avoir fusionné le reste (une demande de fusion partielle, cases cochées sauf celle-là).
**Assertions :** V93, V94, V95, V96, V97
**Fin :** la même demande envoyée deux fois laisse une seule intervention, deux réponses identiques ; un identifiant de B ne révèle rien.

### 8a.1 Action

- [ ] Implémenter `src/Journal/Saisie/SaisieIntervention.php` (données validées : `ids()`, `plantesIds()`, `lignesAInserer(string $jardinId, string $auteurId, \DateTimeImmutable $saisieLe): array` — `position_capturee` encodée en JSON, `jardin_id` explicite car `insertOrIgnore` ne passe pas par `creating`).
- [ ] Implémenter `src/Journal/Saisie/EnregistrerIntervention.php`.
- [ ] Date future : selon Q-UX-4 (recommandation : refus au-delà du lendemain, fuseau `Europe/Paris`) — tâche en attente de la réponse.

**Cible — idempotence** (`EnregistrerIntervention::executer`) :

```php
public function executer(SaisieIntervention $saisie, Utilisateur $auteur): Collection
{
    return DB::transaction(function () use ($saisie, $auteur) {
        $jardinId = $this->jardinCourant->id();

        // 1. Plantes et type lus SOUS FILTRE : absent du jardin courant = introuvable (H9 : jamais de refus métier).
        $presentes = Plante::query()->whereKey($saisie->plantesIds())->pluck('id');
        $this->exigerToutes($saisie, $presentes);                 // sinon Introuvable::lignes([...]) → 422
        TypeIntervention::query()->visibles()->findOrFail($saisie->typeId);

        // 2. Insérer sans écraser : un renvoi ne fait rien (RG-50, H5, S11 : ON CONFLICT (id) DO NOTHING).
        Intervention::query()->insertOrIgnore($saisie->lignesAInserer($jardinId, $auteur->id, $this->horloge->maintenant()));

        // 3. Relire SOUS FILTRE. Un id qui manque existe dans un autre jardin :
        //    même réponse qu'une plante introuvable, et la transaction est annulée (D-08).
        $stockees = Intervention::query()->whereKey($saisie->ids())->get();
        if ($stockees->count() !== count($saisie->ids())) {
            throw Introuvable::lignes($saisie->lignesAbsentesDe($stockees));
        }

        // 4. Rendre l'état STOCKÉ, pas la demande : un renvoi au contenu différent reçoit ce qui a été gardé (D-15).
        return $stockees;
    });
}
```

- [ ] Implémenter `src/Journal/Saisie/InterventionController.php` (validation, appel, réponse JSON) et `src/Journal/Saisie/Introuvable.php` (exception rendue en `422` avec erreurs `lignes.N`) ; modifier `src/Journal/routes.php` ; inscrire la route au registre du cloisonnement (charge valide fournie).

### 8a.2 Tests

- [ ] `tests/Routes/EnregistrerInterventionTest.php` : V93 (les quatre cas) ; V97 (plante archivée entre-temps, échéance déjà soldée, date passée de plusieurs mois ; date future selon Q-UX-4).
- [ ] `tests/Routes/IdempotenceTest.php` : V94 (deux envois, une ligne, deux réponses égales ; renvoi au contenu différent → ligne inchangée) ; V96 (six plantes, `lot_saisie_id` commun, renvoi). Groupe `critere-2`.
- [ ] `tests/Routes/InterventionAutreJardinTest.php` : V95 (réponse octet pour octet égale à celle d'une plante introuvable, hors identifiants ; B intacte). Groupe `critere-12`.

---

## Sprint 8b — « Fait » depuis une échéance, rétroactif, position
<!-- taille : 4 assertions, 6 fichiers, 4 classes de test -->

**Objectif :** « fait » sur une échéance la solde, une saisie rétroactive recalcule la suite, et la position du téléphone est gardée sans défaire le plan.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-15, RG-48, parcours P2, critères 7 et 17 ; chapitre 1 H9, H11 ; chapitre 4 S16, S18 ; décision D-17.
**Contrat :** `POST /echeances/fait {id: uuid, regle_id, due_le}` (T1) → `200` comme `/interventions` ; `SolderEcheance::pourInterventions(Collection $interventions): void` ; `EnregistrerPosition::depuisIntervention(Plante, array $position): void`.
**Tests d'abord :** `tests/Routes/FaitTest.php` ; premier rouge attendu : « route /echeances/fait absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les quatre classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V98, V99, V100, V101
**Fin :** le critère 7 est vrai, et le critère 17 l'est côté serveur.

### 8b.1 Solde

- [ ] Implémenter `src/Echeances/Solde/SolderEcheance.php` : pour chaque intervention, règle active (plante, type) ; calcule par le moteur, avec l'historique **antérieur** à l'intervention, l'échéance dont la période contient `faite_le` (R2) ou la dernière due ≤ `faite_le` (R1) ; `EcheanceMaterialisee::query()->insertOrIgnore` en `soldee` avec `intervention_id` et explication ; rien si aucune (V54, H9) ; jamais deux soldées pour une même `(regle_id, due_le)` (V100). Appelée à l'étape 4 de `EnregistrerIntervention`.
- [ ] Implémenter `src/Echeances/Solde/FaitController.php` : règle par le scope (`404` sinon), active (`422` sinon) ; fabrique une `SaisieIntervention` (une ligne, `faite_le` = aujourd'hui) et appelle `EnregistrerIntervention` ; modifier `src/Echeances/routes.php` ; inscrire au registre.

### 8b.2 Position

- [ ] Implémenter `src/Jardin/Domaine/Geometrie.php` (trait de jardin, `coordonnees` en tableau GeoJSON) et `src/Jardin/Localisation/EnregistrerPosition.php` : l'ordre de fiabilité `plan > manuel > gps` ; `gps` remplace `gps` seulement si `precision_m` est plus petite ; absente → créée en point `gps` (D-17, RG-48, S18). Modifier `EnregistrerIntervention` : `position_capturee` toujours gardée ; `EnregistrerPosition` appelée seulement pour une intervention à une seule ligne.

### 8b.3 Tests

- [ ] `tests/Routes/FaitTest.php` : V98, les quatre cas, et le renvoi.
- [ ] `tests/Persistance/RetroactifTest.php` : V99 (`travelTo` lundi ; taille du samedi). Groupe `critere-7`.
- [ ] `tests/Persistance/DeuxDansFenetreTest.php` : V100.
- [ ] `tests/Persistance/PositionInterventionTest.php` : V101 (sources `plan`, `manuel`, `gps` plus et moins précise, absente ; intervention à six plantes). Groupe `critere-17`.

---

## Sprint 9 — Correction de règle versionnée, suspension, règles libres
<!-- taille : 5 assertions, 7 fichiers, 3 classes de test -->

**Objectif :** corriger une règle crée une version sans réécrire le passé, sauter une occurrence se consigne, et une plante sans espèce reçoit ses propres règles.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-08, §3.9, §4.3–4.4, parcours P7 ; modèle de données D10 ; chapitre 3 §2 (Reglages, Solde).
**Contrat :** `PUT /regles/{regle} {periodicite_annees?, intervalles?, fenetres?}` (B9) → `302` ou JSON `{regle: {id, version}, prochaine: {...}}` ; `POST /plantes/{plante}/regles {type_intervention_id, type_regle, ...}` (B6, B9) ; `POST /echeances/suspendre {regle_id, due_le, motif}` (B6). Validation commune des fenêtres : dates réelles, optimale incluse, butoirs par paires, au moins un jour après rognage pour les cinq profils.
**Tests d'abord :** `tests/Routes/CorrectionRegleTest.php` ; premier rouge attendu : « route /regles/{regle} absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** **Q-UX-7** (confiance et explication d'une règle créée de toutes pièces) ; V106 en dépend.
**Assertions :** V102, V103, V104, V105, V106
**Fin :** une correction donne v1 inactive et v2 active, et les échéances passées gardent leur explication d'origine.

### 9.1 Correction

- [ ] Implémenter `src/Echeances/Reglages/ValiderFenetres.php` (règle de validation partagée, utilise `Fenetre::projeter` sur les cinq profils).
- [ ] Implémenter `src/Echeances/Reglages/ModifierRegle.php` : transaction ; `lockForUpdate` sur la règle ; v1 `est_active = false`, `remplacee_le = now()` **avant** l'insertion de v2 (l'index unique partiel l'exige) ; v2 = copie, `version + 1`, valeurs corrigées, `regle_reference_id` gardé ; fenêtres et intervalles copiés puis corrigés ; v1 et ses fenêtres jamais modifiées au-delà de ces deux colonnes. Rend la prochaine échéance par `CalculEcheances` restreint à la plante.
- [ ] Implémenter `src/Echeances/Reglages/RegleController.php` (`corriger`, `creer`) et `src/Echeances/Policies/ReglePolicy.php` (propriétaire).

### 9.2 Suspension et règles libres

- [ ] Implémenter `src/Echeances/Solde/SuspendreEcheance.php` : vérifie que `(regle_id, due_le)` est une échéance que le moteur rend aujourd'hui pour cette règle (`422` sinon) ; `insertOrIgnore` en `suspendue` avec motif.
- [ ] Implémenter `src/Echeances/Reglages/CreerRegle.php` : règle sans `regle_reference_id`, confiance selon Q-UX-7 (recommandation : `indicative`, l'explication dit « règle que vous avez définie ») ; seconde règle active du même type refusée par l'index, rendue en `422`.
- [ ] Modifier `src/Echeances/routes.php` ; inscrire les trois routes au registre.

### 9.3 Tests

- [ ] `tests/Routes/CorrectionRegleTest.php` : V102 (quatre cas), V103, V104.
- [ ] `tests/Routes/SuspendreTest.php` : V105, quatre cas et la répétition.
- [ ] `tests/Routes/RegleLibreTest.php` : V106.

---

## Sprint 10a — Géométries, priorité des sources, plantes proches
<!-- taille : 5 assertions, 7 fichiers, 4 classes de test -->

**Objectif :** poser ou tracer une plante, garder la position la plus fiable, calculer les échéances des lignes et polygones, et proposer les plantes proches d'une position.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-14, RG-15, RG-48, Q8, critères 9, 17, 18, 24 ; chapitre 4 S17, S18 ; chapitre 3 §2 (Localisation, A4) ; décision D-17.
**Contrat :** `PUT /plantes/{plante}/geometrie {type: Point|LineString|Polygon, coordinates}` (B13) → source `plan` ; `GET /plantes/proches?lng=&lat=&precision_m=&q=&zone=` → JSON `{proches: [≤ 10, {id, nom, code_court, distance_m}], zone: [...]}` (T3) ; `POST /plantes` accepte `position {lng, lat, precision_m}` (T3).
**Tests d'abord :** `tests/Routes/GeometrieTest.php` ; premier rouge attendu : « route /plantes/{plante}/geometrie absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les quatre classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V107, V108, V109, V110, V111
**Fin :** les critères 17, 18 et 24 sont vrais côté serveur ; le critère 9 l'est pour la liste des proches.

### 10a.1 Géométries

- [ ] Implémenter `src/Jardin/Domaine/GeoJson.php` : validation (type, bornes WGS 84, ligne ≥ 2 points, polygone fermé ≥ 4 points, un seul anneau en V1) et point représentatif (le point ; le sommet médian d'une ligne ; le centre des sommets d'un polygone) (D-26).
- [ ] Implémenter `src/Jardin/Plantes/GeometrieController.php` ; modifier `EnregistrerPosition` (`depuisPlan` : remplace toujours ; `depuisCreation` : point `gps`) et `CreerPlante` (position facultative).

### 10a.2 Proches

- [ ] Implémenter `src/Jardin/Localisation/PlantesProches.php` : géométries des plantes actives du jardin (une requête), distance haversine en PHP au point représentatif (D-26), 10 premières, puis les plantes de la zone courante, recherche par nom ou code ; jamais de présélection (aucun champ `choisie`). Implémenter `src/Jardin/Localisation/ProchesController.php` ; modifier `src/Jardin/routes.php` ; inscrire au registre.

### 10a.3 Tests

- [ ] `tests/Routes/GeometrieTest.php` : V107, quatre cas.
- [ ] `tests/Persistance/PrioriteSourcesTest.php` : V108, les neuf couples. Groupe `critere-17`.
- [ ] `tests/Persistance/LignesPolygonesTest.php` : V109 (groupe `critere-18`), V110 (groupe `critere-24`).
- [ ] `tests/Routes/ProchesTest.php` : V111 (création avec position ; 30 plantes témoins à coordonnées inventées). Groupe `critere-9`.

---

## Sprint 10b — Code court, étiquettes, QR
<!-- taille : 3 assertions, 6 fichiers, 3 classes de test -->

**Objectif :** l'adresse `/p/{code}` ouvre la fiche dans son jardin seulement, et les étiquettes d'une zone portent un QR qui dit exactement cette adresse.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-13, critères 10 et 22 ; chapitre 3 §2 (Identification, `EncodeurQR`) ; `conception-ecrans.md` (B10, T6).
**Contrat :** `GET /p/{code}` → `302 /plantes/{id}?contexte=terrain` ou `404` (T6, B6) ; `GET /zones/{zone}/etiquettes` → `Bureau/Etiquettes` (B10) props `[{code_court, nom, repere, qr_svg}]` ; `EncodeurQR::svg(string $url): string`.
**Tests d'abord :** `tests/Routes/CodeCourtTest.php` ; premier rouge attendu : « route /p/{code} absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V112, V113, V114
**Fin :** les critères 10 et 22 sont vrais côté serveur.
**Coût :** décodeur de QR en test jamais essayé (bibliothèque PHP et ses extensions) → **essai d'abord** : 10b.1.

### 10b.1 Essai d'abord

- [ ] Ajouter `chillerlan/php-qrcode` (encodage SVG) et, en développement, un décodeur (`khanamiryan/qrcode-detector-decoder` sur un rendu PNG du même encodeur) ; un test jetable encode puis décode une URL. Si le décodage n'est pas fiable, comparer la matrice produite à celle d'un second encodeur et l'écrire (D-27).

### 10b.2 Routes

- [ ] Implémenter `src/Jardin/Identification/ResoudreCodeCourt.php` (par le scope, `upper()`), `src/Jardin/Identification/CodeCourtController.php`, `src/Jardin/Identification/EncodeurQR.php`, `src/Jardin/Identification/EtiquettesController.php` (zone par le scope, plantes actives triées par code) ; modifier `src/Jardin/routes.php` et `composer.json` ; inscrire au registre.

### 10b.3 Tests

- [ ] `tests/Routes/CodeCourtTest.php` : V112 (casse ; `FRU-001` dans A et B mène à A ; code de B seul → `404` identique à inexistant). Groupe `critere-22`.
- [ ] `tests/Routes/EtiquettesTest.php` : V113. Groupe `critere-10`.
- [ ] `tests/Routes/QrTest.php` : V114. Groupe `critere-22`.

---

## Sprint 11a — Photos et dérivées en file
<!-- taille : 4 assertions, 8 fichiers, 3 classes de test -->

**Objectif :** une photo se joint à une plante ou à une intervention, ses dérivées WebP se produisent hors de la requête, et le quota protège le disque.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-35, RG-38, §8.4, §8.6, critère 14 ; chapitre 1 H10 ; chapitre 3 §12 (A19, A20, A21) ; chapitre 4 §3.7.
**Contrat :** `POST /photos` (multipart) `{id: uuid, fichier, plante_id?|intervention_id?, prise_le?}` → `200 {id, url, derivees_pretes}` (idempotent comme V94) ; `413`/`422` hors limites ; `422` « Quota atteint » ; `CompresserPhoto` (job, `JobDeJardin`) ; `CalculerOccupation::pourJardin(): int` (Ko).
**Tests d'abord :** `tests/Routes/AttacherPhotoTest.php` ; premier rouge attendu : « route /photos absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V115, V116, V117, V118
**Fin :** une photo envoyée est stockée, ses deux dérivées existent après le job, et un envoi au-delà du quota ne stocke rien.
**Coût :** encodage WebP jamais observé dans le conteneur DDEV ni dans la CI (GD ou Imagick compilé avec WebP) → **essai d'abord** : 11a.1.

### 11a.1 Essai d'abord

- [ ] Vérifier `imagewebp` (GD) dans DDEV et dans la CI ; à défaut, Imagick ; ajouter `intervention/image` ; écrire le pilote retenu (D-28). Limites : 20 Mo, 12 000 px de côté, types `image/jpeg|png|heic|webp` (HEIC selon le pilote, écrit).

### 11a.2 Stockage

- [ ] Implémenter `src/Journal/Domaine/Photo.php` (trait de jardin), `src/Journal/Photos/AttacherPhoto.php` (vérifie plante ou intervention par le scope ; quota par `CalculerOccupation` avant écriture ; empreinte SHA-256 ; `photos/{jardin}/{annee}/{empreinte}.{ext}` sur le disque `local` privé ; `insertOrIgnore` sur `id` ; envoie `CompresserPhoto`), `src/Journal/Photos/PhotoController.php`, `src/Journal/Photos/CompresserPhoto.php` (160 px et 1 200 px, WebP, `{empreinte}-160.webp`, `{empreinte}-1200.webp`, puis `derivees_pretes = true` ; échec → trois essais, l'original reste servi), `src/Journal/Photos/ServirPhoto.php` (route `GET /photos/{photo}/{taille}`, par le scope, original tant que les dérivées ne sont pas prêtes), `src/Comptes/Quotas/CalculerOccupation.php` (somme de `photos.taille_ko` et des fonds `image`) ; modifier `src/Journal/routes.php` et `composer.json` ; inscrire au registre.

### 11a.3 Tests

- [ ] `tests/Routes/AttacherPhotoTest.php` : V115 (quatre cas, renvoi), V118 (intervention présente, envoi de photo en échec). Groupe `critere-14`.
- [ ] `tests/Persistance/DeriveesTest.php` : V116 (`Queue::fake` pour l'envoi, puis job exécuté : fichiers et drapeau relus).
- [ ] `tests/Routes/QuotaTest.php` : V117.

---

## Sprint 11b — Photo de couverture, fond de plan, nettoyage
<!-- taille : 3 assertions, 7 fichiers, 3 classes de test -->

**Objectif :** choisir la photo de couverture d'une plante, importer et caler un fond de plan, et nettoyer les fichiers orphelins jardin par jardin.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC RG-35, RG-47, §6 lot 4, critères 14 et 16 ; chapitre 4 §3 bis ; chapitre 3 §12.
**Contrat :** `PUT /plantes/{plante}/couverture {photo_id}` (B6) ; `POST /fonds-de-plan` (multipart) `{fichier, points: [{x, y, lng, lat}] (2 ou 3)}` (B13) → le fond actif ; `GET /fonds-de-plan/actif` → JSON `{url, calage}` ; `php artisan photos:nettoyer {jardin}`.
**Tests d'abord :** `tests/Routes/CouvertureTest.php` ; premier rouge attendu : « route /plantes/{plante}/couverture absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V119, V120, V121
**Fin :** le critère 14 est vrai côté serveur ; le critère 16 l'est pour le stockage du fond et de son calage.

- [ ] Implémenter `src/Jardin/Plantes/CouvertureController.php` : photo par le scope, rattachée à la plante ou à l'une de ses interventions, sinon `422` ; modifier `PlanteController` (`lister`, `afficher` : URL de la miniature, ou de l'original tant que `derivees_pretes` est faux).
- [ ] Implémenter `src/Jardin/Domaine/FondDePlan.php` (trait de jardin), `src/Jardin/Plan/ImporterFondDePlan.php` (quota ; 2 ou 3 points, bornes ; transaction : ancien `actif = false` puis nouveau `actif = true` — l'index partiel l'exige ; le calage est stocké tel quel, la transformation affine est en partie B), `src/Jardin/Plan/FondDePlanController.php`.
- [ ] Implémenter `src/Journal/Photos/NettoyerOrphelines.php` (commande, jardin en argument obligatoire, positionne `JardinCourant` ; fichiers de `photos/{jardin}/` sans ligne, de plus de 7 jours ; `--a-blanc` par défaut, `--supprimer` pour agir) ; modifier `CalculerOccupation` et les routes ; inscrire au registre.
- [ ] `tests/Routes/CouvertureTest.php` : V119, quatre cas. Groupe `critere-14`.
- [ ] `tests/Routes/FondDePlanTest.php` : V120, quatre cas. Groupe `critere-16`.
- [ ] `tests/Persistance/NettoyageTest.php` : V121.

---

## Sprint 12 — Export intégral
<!-- taille : 3 assertions, 6 fichiers, 3 classes de test -->

**Objectif :** tout le jardin s'exporte en une archive lisible sans l'outil, qui ne contient que lui.
**Constructeur :** back
**Risque :** complet
**Lecture :** CDC §8.1, RG-11, RG-28, critère 8 ; `conception-ecrans.md` (B12).
**Contrat :** `GET /export` → `200 application/zip`, en flux, nom `jardin-<nom>-AAAA-MM-JJ.zip` : `donnees.json`, `zones.csv`, `plantes.csv`, `geometries.csv`, `regles.csv` (toutes versions), `fenetres.csv`, `interventions.csv`, `echeances.csv`, `LISEZMOI.txt`, `photos/` selon Q-UX-5 ; CSV en UTF-8 avec BOM, séparateur `;`, en-têtes en français.
**Tests d'abord :** `tests/Routes/ExportTest.php` ; premier rouge attendu : « route /export absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** les trois classes / `make verifier`, et `make balayage` (groupe `volume`).
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** **Q-UX-5** (quelles photos dans l'export) ; V122 en dépend pour son dernier point.
**Assertions :** V122, V123, V124
**Fin :** le critère 8 est vrai côté serveur.
**Coût :** jeu de 10 000 interventions et mesure de mémoire, boucle lente → **accepter**, test de volume lancé une fois (groupe `volume`).

- [ ] Ajouter `maennchen/zipstream-php` ; implémenter `src/Comptes/Export/ExporterJardin.php` (lit par `lazy()` sous filtre, écrit chaque fichier en flux ; noms lisibles des zones, espèces, types joints), `src/Comptes/Export/EcrireCsv.php`, `src/Comptes/Export/ExportController.php` (`StreamedResponse`), `resources/export/LISEZMOI.txt` (description de chaque fichier et de chaque colonne) ; modifier `src/Comptes/routes.php` et `composer.json` ; inscrire au registre.
- [ ] Photos : selon Q-UX-5 (recommandation : les dérivées 1 200 px, l'original en option) — tâche en attente de la réponse.
- [ ] `tests/Routes/ExportTest.php` : V122 (archive ouverte, chaque entité relue en base présente). Groupe `critere-8`.
- [ ] `tests/Routes/ExportCloisonneTest.php` : V123 (même utilisateur, deux jardins). Groupe `critere-12`.
- [ ] `tests/Routes/ExportVolumeTest.php` : V124 (`memory_get_peak_usage`). Groupe `volume`.

---

## Sprint 13 — Jardin de démonstration et seuils
<!-- taille : 2 assertions, 4 fichiers, 2 classes de test -->

**Objectif :** un jardin fictif de 500 plantes se crée en une commande, et les seuils de performance tiennent dessus.
**Constructeur :** back
**Risque :** léger (données de démonstration, tests seuls)
**Lecture :** chapitre 6 §3 (`jardin-demo`, C4) ; chapitre 4 §5 (jeu de démonstration) ; décision D-14.
**Contrat :** `make jardin-demo` → `php artisan jardin:demo` ; refuse si `APP_ENV=production`.
**Tests d'abord :** `tests/Persistance/JardinDemoTest.php` ; premier rouge attendu : « commande jardin:demo absente ».
**Preuve :** rouge
**Itérer sur / lancer une fois :** le test / `make verifier` et `make balayage`.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** sans objet
**Question UX possible :** aucune
**Assertions :** V125, V126
**Fin :** `make jardin-demo` puis le tableau de bord et la liste tiennent leurs seuils, mesurés et écrits.

- [ ] Implémenter `database/seeders/DemonstrationSeeder.php` : un jardin « Démonstration » (profil Plaine), 8 zones, 500 plantes réparties sur les espèces chargées (par `CreerPlante`), dont 20 lignes et 10 polygones, coordonnées inventées autour d'un point fictif écrit dans le fichier (jamais celui du jardin pilote, C4), deux ans d'interventions (par `EnregistrerIntervention`, `travelTo`) ; relancé, il supprime puis recrée ce seul jardin.
- [ ] Implémenter `src/Support/Console/JardinDemo.php` ; modifier `Makefile` (`jardin-demo`).
- [ ] `tests/Persistance/JardinDemoTest.php` : V125 (nombres, formes, relance, refus en production). Groupe `critere-23`.
- [ ] `tests/Persistance/SeuilsDemoTest.php` : V126. Groupe `volume`.

---

## Revue des coûts

Faite après le premier jet (P7). Classée de la plus chère à la moins chère.

| Sprint | Boucle lente | Autre facteur | Décision |
|---|---|---|---|
| 0e | Chaque essai de CI réelle est un envoi et un run de plusieurs minutes | Fusion automatique et ruleset jamais observés sur ce dépôt ; mutation jamais lancée | **Sprint d'outil** pour la mutation (V13, classe témoin), **accepter** pour la CI en itérant sur le test qui lit le YAML et en n'envoyant qu'une fois |
| 3e | `pest --mutate` sur tout le moteur | Seuil versionné, instrument neuf | **Accepter**, itérer par classe ; l'instrument est essayé au 0e |
| 4b | Recherche documentaire, sources à consulter une à une | Valeurs réelles projetées sur cinq profils jamais observées | **Couper** : les espèces à sourcer passent au 4c |
| 0b | `ddev start` et construction des conteneurs | DDEV sur OrbStack jamais lancé pour ce projet (le démon n'était pas démarré à l'écriture du plan) | **Essai d'abord** (0b.1) |
| 6 | Jeu de 500 plantes à chaque essai | — | **Accepter**, itérer sur 20 plantes, groupe `volume` lancé une fois |
| 12 | 10 000 interventions et mesure de mémoire | — | **Accepter**, groupe `volume` |
| 11a | — | Encodage WebP jamais observé dans DDEV ni dans la CI | **Essai d'abord** (11a.1) |
| 10b | — | Décodeur de QR en test jamais essayé | **Essai d'abord** (10b.1) |
| 4c | Recherche documentaire | Résultat incertain (l'espèce peut sortir) | **Accepter**, petit sprint isolé |
| 0a | Attente de l'accord de Léon | Acte qui engage Léon | **Accepter** : c'est l'arrêt prévu |
