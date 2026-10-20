# Document technique — Chapitre 6 : environnement de code, intégration et livraison continues

Version 0.1 — 19/10/2026
Suite de `technique-05-deploiement.md` (v0.2). Applique le CDC v0.13, §1.5 et §8.8.
Décisions **C1 à C26**. Points ouverts à partir de **O16**.

**Stack :** Laravel 12 + Inertia + React/TypeScript, PostgreSQL (chapitres 1 à 4).

**Ce que ce chapitre traite.** Tout ce qui entoure le code sans en être : le
dépôt, le poste de développement, l'outillage de qualité, la façon dont les
tests du chapitre 2 sont lancés et contrôlés, l'intégration continue, la
livraison, et les règles de travail des agents IA qui écrivent le produit.

**Ce qu'il ne traite pas.** Les contraintes d'exploitation créées par
l'architecture (service worker, worker de file, mutations versionnées,
migrations en production) restent au chapitre 5. Le découpage en chantiers est
au chapitre 7, à écrire.

> Comme aux chapitres 2 à 4, les extraits sont des **esquisses** qui montrent la
> forme. Rien n'a encore été exécuté : le dépôt n'existe pas.

> **Hors-ligne suspendu pour la V1 — 19/10/2026** (CDC v0.13). Ce qui touche
> le service worker, la file d'attente et l'API terrain dans ce chapitre
> (§6 Vitest, déclencheurs du job `e2e`, version du service worker en recette)
> attend la reprise. En V1, le job `e2e` se déclenche sur les pages terrain et
> la carte.

---

## 1. Le principe : vérifier coûte plus cher qu'écrire

Le produit est écrit par des agents (CDC §1.5). Un agent produit en quelques
minutes un moteur d'échéances qui *a l'air* juste. Ce qui le distingue d'un
moteur juste, c'est uniquement une suite de tests qui échoue quand il a tort.
Tout ce chapitre découle de là.

Trois boucles de vérification, de la plus rapide à la plus lente :

| Boucle | Où | Durée visée | Ce qu'elle attrape |
|---|---|---|---|
| **Sauvegarde** | Poste, à chaque modification | < 1 s | Le moteur (N1) — l'essentiel des bugs subtils (TS-2, TS-8) |
| **Envoi** | Poste, crochet `pre-push` | < 3 min | Formatage, typage, analyse statique, N1 à N3 |
| **Demande de fusion** | Intégration continue | < 10 min | Tout ce qui précède dans un environnement propre, plus N4 et mutation quand ils sont concernés |

> **C1** — **Chaque boucle refait le travail de la précédente dans un
> environnement plus propre, jamais un travail différent.** Un test qui ne
> tourne qu'en intégration continue est un test qu'aucun agent ne lance avant
> d'annoncer « c'est fini ».

Le coût monétaire suit la même pente : le poste ne coûte rien, l'intégration
continue se paie en minutes. Constaté sur un autre projet de ce poste en août
2026 : un budget d'Actions épuisé en vingt jours, dont l'essentiel venait de
relances de diagnostic et d'un run resté pendu. Les suites lancées en local
prenaient 2 à 5 minutes, contre plus de 20 en intégration continue.

---

## 2. Le dépôt

**Un seul dépôt GitHub** pour le code, la documentation et les données de la
bibliothèque. Les documents de conception — dont ce chapitre — y entrent dans
`docs/`. Ils évoluent dans la même demande de fusion que le comportement qu'ils
décrivent.

```
jardin/
  AGENTS.md                 point d'entrée de tout agent (§10)
  .claude/rules/            règles détaillées : git, tests, documentation
  Makefile                  la commande unique (§4)
  .ddev/                    environnement local (§3)
  .github/
    workflows/verifier.yml  intégration continue (§8)
    workflows/livrer.yml    livraison (§9)
    pull_request_template.md
    dependabot.yml
  src/<Module>/             code applicatif, espace de noms `Src\` (chapitre 3 ; D-01 du plan)
  app/  bootstrap/          amorce Laravel (fournisseurs de services)
  resources/js/             un seul bundle en V1 (chapitre 3, encart V1 ; A13 suspendue)
  database/migrations/      migrations centralisées (S14)
  bibliotheque/             données curées et leurs sources (protocole)
  tests/
    Moteur/  Persistance/  Routes/   N1 à N3 (Pest)
    Front/                            logique pure du front (Vitest, §6.4)
    Navigateur/                       N4 (Playwright)
    Architecture/                     règles de dépendance (§5)
  docs/                     CDC, modèle de données, chapitres techniques
```

**Public pendant le développement, privé ensuite** : décision de Léon du
19/10/2026 (Q16 du CDC). Le passage de l'un à l'autre se fait dans les réglages
du dépôt, dans les deux sens. Ce que chaque état change :

| | Privé (compte personnel, plan Pro) | Public |
|---|---|---|
| Minutes d'Actions | Quota mensuel, puis payant | Gratuites sur les runners standards |
| Relecteur obligatoire avant un déploiement (environnement protégé) | **Indisponible** — constaté par l'API le 21/08/2026 sur un autre dépôt du même compte | Disponible, d'après la documentation GitHub ; **à vérifier** à la création |
| File de fusion (*merge queue*) | Indisponible (même constat) | Réservée aux organisations |
| Code et historique des décisions | Privés | Lisibles par tous |
| Détection de secrets et blocage à l'envoi (*push protection*) | Payants | Gratuits, d'après la documentation GitHub ; à activer à la création |

**« Personne ne le trouvera » est faux, et c'est sans importance pour le code.**
Des robots parcourent les dépôts publics dès leur création, précisément pour y
chercher des clés et des mots de passe. Des archives publiques en conservent
des copies, et une copie faite pendant la période publique survit au passage en
privé. Ce qui ne doit jamais être lu ne doit donc **jamais** entrer dans le
dépôt, même une minute, même dans un commit effacé ensuite.

> **C25** — **Tout ce qui a été public le reste.** Pendant la période publique :
> aucun secret, aucune donnée réelle du jardin pilote (C4), aucun fichier `.env`
> versionné. La détection de secrets et le blocage à l'envoi de GitHub sont
> activés dès la création. Un secret qui a fuité ne se corrige pas en
> l'effaçant de l'historique : on le révoque et on en émet un nouveau.

**Le jour du passage en privé** : les minutes d'Actions deviennent décomptées
sur le quota du compte, et les fonctions de la colonne « public » disparaissent.
Si le garde de production reposait sur un environnement protégé, il faut le
remplacer au même moment par la phrase de confirmation (C22). Plus simple : la
poser dès le départ, elle fonctionne dans les deux états.

> **C2** — La branche `main` est protégée par un **ruleset** : fusion par
> demande uniquement, vérifications requises vertes, branche à jour avant
> fusion, aucune réécriture d'historique. Personne ne pousse directement sur
> `main`, Léon compris. Un ruleset se modifie en `PUT` avec la charge complète,
> jamais en `PATCH` (constat du 25/08/2026 sur l'autre dépôt).

---

## 3. Le poste de développement

**DDEV**, déjà installé et utilisé sur ce poste pour les sites WordPress. Il
fournit PHP, PostgreSQL, Node et un HTTPS local, avec la même configuration pour
tout agent et toute machine.

```yaml
# .ddev/config.yaml — esquisse
type: laravel
php_version: "8.4"
database:
  type: postgres
  version: "17"          # même version majeure que la production (Q17)
nodejs_version: "22"
webserver_type: nginx-fpm
```

*Non vérifié à ce jour :* les versions exactes acceptées par la version de DDEV
installée. Le moteur Docker de ce poste (OrbStack) n'était pas démarré au
moment de la rédaction, donc `ddev version` n'a pas pu répondre. À contrôler au
chantier 0.

> **C3** — **La version majeure de PostgreSQL est la même partout** : poste,
> intégration continue, recette, production. Les comportements qui comptent ici
> (contraintes partielles, `ON CONFLICT`, tri des `jsonb`) ne doivent pas
> changer entre l'endroit où on teste et l'endroit où ça tourne.

**HTTPS depuis un téléphone.** La décision E1 et le point O13 du chapitre 5
restent entiers : certificat local installé sur le téléphone, ou tunnel. C'est
le premier point à trancher au chantier 0, parce que le mode terrain ne se
teste pas sans lui.

**Données de développement.** `make jardin-demo` crée un jardin fictif de
500 plantes, avec des lignes et des polygones, sur des coordonnées inventées.
C'est le jeu qui sert à vérifier RG-19 et le critère 23.

> **C4** — **Aucune donnée réelle du jardin pilote dans le dépôt**, ni dans les
> tests, ni dans les fixtures. La localisation d'un jardin désigne un domicile
> (RG-28) : un dépôt public la publierait, un dépôt privé la confierait à
> chaque agent.

---

## 4. La commande unique

Un `Makefile` porte toutes les commandes. L'intégration continue appelle les
**mêmes cibles**, et rien d'autre.

| Cible | Contenu | Quand |
|---|---|---|
| `make setup` | Installation, base, crochets git (§7) | Une fois par poste ou par worktree |
| `make moteur` | N1 seul — doit rester sous la seconde (TS-8) | En continu, pendant qu'on écrit le moteur |
| `make types` | Types TypeScript générés depuis les classes PHP `#[TypeScript]` (§5, D-77) | Après avoir modifié une classe annotée |
| `make statique` | Formatage vérifié, analyse statique, typage TS, types générés à jour, audit des dépendances, taille des sprints (`make plan`) | Avant tout envoi |
| `make plan` | Taille de chaque sprint du plan (`docs/plan/README.md` P5, décision D-75) | Quand le plan change |
| `make test` | N1 à N3 et front (§6.4) | Avant tout envoi |
| `make balayage` | Invariants calendaires sur toute l'année (§6.2) | Avant tout envoi qui touche le moteur ou la bibliothèque |
| `make e2e` | N4 (Playwright) | Avant un envoi qui touche le terrain ou la carte |
| `make mutation` | Tests de mutation du moteur (§6.1) | Avant un envoi qui touche le moteur |
| `make criteres` | Correspondance critères du CDC ↔ tests (§6.3) | Toujours |
| `make verifier` | Tout ce qui précède, dans cet ordre | Avant d'annoncer qu'un travail est terminé |

> **C5** — **`make verifier` est la définition de « terminé ».** Un agent ne
> déclare pas un travail fini sans l'avoir lancée, et il colle la dernière
> ligne de sa sortie dans la demande de fusion. Une affirmation sans cette
> ligne ne vaut rien, pour un agent comme pour un humain.

> **C6** — **L'intégration continue n'a aucune logique propre** : chaque étape
> d'un workflow appelle une cible `make`. Une vérification qui n'existerait
> qu'en YAML ne serait pas reproductible sur le poste, et donc pas
> diagnostiquable sans payer des minutes.

---

## 5. L'outillage de qualité

| Rôle | PHP | TypeScript |
|---|---|---|
| Formatage | **Pint** (fourni avec Laravel) | **Prettier** |
| Analyse statique | **PHPStan** avec **Larastan** | `tsc --noEmit` en mode `strict` |
| Règles de style et pièges | — | **ESLint** |
| Tests | **Pest** (chapitre 2) | **Vitest** (§6.4), **Playwright** (N4) |
| Dépendances vulnérables | `composer audit` | `npm audit --audit-level=high` |

**Les règles d'architecture deviennent des tests.** Le chapitre 3 pose des
frontières : le moteur ne connaît ni la base ni le framework (D3, TS-7), un
module n'appelle pas les classes internes d'un autre. Pest sait les exprimer
sous forme de tests d'architecture (`arch()`), qui échouent dès qu'une
dépendance interdite apparaît.

```php
// tests/Architecture/MoteurTest.php — esquisse
arch('le moteur ne dépend d\'aucune couche Laravel')
    ->expect('Src\Echeances\Moteur')
    ->not->toUse(['Illuminate', 'App\Models']);
```

> **C7** — **Toute frontière du chapitre 3 a son test d'architecture.** Un
> agent qui cherche le chemin le plus court vers une fonctionnalité qui marche
> franchira une frontière qu'aucun test ne garde. Il le fera de bonne foi, et le
> moteur deviendra intestable sans que personne l'ait décidé.

**Types générés.** Les types TypeScript produits depuis PHP (chapitre 3) sont
régénérés par `make statique`, qui échoue s'ils diffèrent de ceux du dépôt.
Le générateur est `spatie/laravel-typescript-transformer` (O5 tranchée, décision
D-77) : les classes PHP marquées `#[TypeScript]` deviennent des types exportés de
`resources/js/types/generes.d.ts`, écrit par `make types`.

> **C8** — Le niveau de PHPStan est **le plus strict tenable dès le premier
> commit**, et il ne baisse jamais. Monter le niveau sur une base existante
> coûte une campagne de corrections. Le tenir depuis le départ ne coûte rien à
> un agent.

---

## 6. Ce que les tests ajoutent au chapitre 2

Le chapitre 2 reste la stratégie de test : quatre niveaux, N1 majoritaire, pas
de mock dans le moteur, aucun test qui lit l'heure réelle. Ce paragraphe ajoute
ce que rend nécessaire un code écrit par des agents, et ce qu'ajoute la V1.

### 6.1 Contrôler les tests eux-mêmes : la mutation

Un agent sait écrire un test qui passe sans rien vérifier : une assertion sur la
mauvaise variable, un attendu recopié depuis la sortie du code. La couverture ne
le détecte pas (TS-9). **Le test de mutation, si** : l'outil altère le code
exprès (un `<` devient `<=`, un `+ 1` disparaît) et relance les tests. Si aucun
test n'échoue, la mutation « survit », et un comportement n'est pas vérifié.

Pest l'intègre (`pest --mutate`, avec un seuil `--min`), d'après sa
documentation officielle.

> **C9** — **Le moteur est soumis au test de mutation, et lui seul.** C'est là
> que se concentre la difficulté (TS-2), et c'est le seul code assez pur pour
> que l'exercice reste rapide. Sur les contrôleurs ou l'interface, il produirait
> surtout du bruit.

> **C10** — Le seuil de mutations tuées part de la valeur mesurée au premier
> moteur complet, et **ne fait que monter**. Le baisser est une décision
> soumise à RG-49 : elle se signale dans la demande de fusion, avec son motif.

### 6.2 Les balayages : vérifier toute l'année, pas trois dates

Les bugs les plus coûteux trouvés à la relecture du CDC n'étaient visibles que
sur une combinaison : une fenêtre juste, un décalage juste, et une haie taillée
en avril (RG-44). Trois dates choisies à la main ne les trouvent pas.

Un **balayage** évalue le moteur pour **chaque jour de l'année**, **chacun des
cinq profils climatiques**, et **chaque règle de la bibliothèque**, puis vérifie
des invariants :

| Invariant | Règle |
|---|---|
| Aucune taille de haie n'est due entre le 16/03 et le 15/08 | RG-44, critère 4 |
| Une règle active produit exactement une échéance à venir | RG-40 |
| Une échéance R1 n'est jamais due hors saison | Critère 15 |
| Le décalage ne s'applique jamais à la valeur d'un intervalle | RG-25 |
| Deux calculs identiques donnent la même identité d'échéance | CDC §9, point 5 bis |

> **C11** — Les balayages sont des tests N1, sans base, mais **à part** de la
> suite rapide (`make balayage`). Ils peuvent dépasser la seconde, et la suite
> rapide ne le doit pas (TS-8).

### 6.3 Chaque critère d'acceptation a son test

Le CDC §10 numérote ses critères (1 à 15 pour le lot 0, 16 à 24 pour le lot 4,
tous requis pour la V1), et
RG-46 exige que chacun soit vérifié automatiquement. Chaque test qui vérifie un
critère porte le groupe Pest `critere-NN`, ou l'étiquette Playwright
équivalente.

`make criteres` lit les numéros du §10 dans `docs/cahier-des-charges-fonctionnel.md`,
cherche les groupes dans `tests/`, et liste les critères sans test.

> **C12** — `make criteres` est **informatif pendant le développement d'un
> jalon, bloquant pour sa livraison**. Un jalon ne se livre pas avec un critère
> sans test. Avant ce moment, la liste sert de tableau d'avancement.

### 6.4 Le front a aussi de la logique pure

Le chapitre 2 ne prévoyait pas de tests unitaires côté navigateur. La file
d'attente hors ligne en contenait déjà, et la V1 en ajoute :

| Logique | Où | Test |
|---|---|---|
| File d'attente des mutations, rejeu, versions de format | Terrain (chapitre 1) | Vitest |
| Tri par proximité | Terrain | Vitest |
| Calage d'une image par 2 ou 3 points (transformation affine) | Carte, V1 | Vitest |
| Ordre de tournée (plus proche voisin) | Carte, V1 | Vitest |
| Choix de la position affichée selon la source (RG-48) | Serveur, module `Jardin` | Pest N2 (S18) |

> **C13** — **Toute logique de calcul côté navigateur vit dans un module sans
> React**, testé par Vitest. Un composant d'interface ne calcule rien qu'on
> voudrait tester. C'est la même séparation que moteur/ORM (D3), appliquée au
> front.

### 6.5 La carte : tester la panne du fond

RG-47 dit qu'aucune fonction ne dépend du fond de carte. Cette promesse ne se
vérifie qu'en coupant le service. Playwright sait intercepter les requêtes
d'une page et les faire échouer. Le test du critère 20 bloque toutes les
requêtes vers `data.geopf.fr`, puis vérifie que la carte, les échéances et
l'ordre de tournée s'affichent.

> **C14** — **Aucun test ne dépend d'un service extérieur.** Les tests qui
> affichent une carte servent des tuiles fictives depuis le poste. Le service
> IGN n'est jamais appelé en intégration continue : un test qui échoue parce
> qu'un tiers est lent ne dit rien sur le produit.

### 6.6 Le cloisonnement couvre les routes qui n'existent pas encore

Le critère 12 (aucune donnée d'un autre jardin visible) est la faute la plus
grave possible du produit (CDC §8.4). Un test écrit à la main pour chaque route
oublie la route ajoutée le mois suivant.

> **C15** — Le test de cloisonnement **énumère les routes de l'application**,
> crée deux jardins, et appelle chaque route du jardin A avec les identifiants
> du jardin B. Une route nouvelle entre dans le test sans que personne l'y
> ajoute. Une route volontairement hors cloisonnement (la bibliothèque, §5.2 du
> CDC) est déclarée dans une liste d'exceptions relue à chaque modification.

### 6.7 La bibliothèque est testée comme du code

Les tests de cohérence du protocole de bibliothèque (§3) tournent dans
`make test` : chaque règle a une source, un niveau de confiance, des fenêtres
valides, et respecte ses butoirs. La vérification que les liens des sources
répondent toujours **ne tourne pas** en intégration continue, parce qu'elle
dépend du réseau (C14). C'est une commande à part, `make sources`, lancée à la
main avant chaque version de la bibliothèque.

---

## 7. Les crochets git

Installés par `make setup`, versionnés dans le dépôt.

| Crochet | Lance | Durée visée |
|---|---|---|
| `pre-commit` | Pint et Prettier sur les fichiers modifiés | Quelques secondes |
| `pre-push` | `make statique` puis `make test` | < 3 min |

> **C16** — Le `pre-push` lance la suite N1 à N3. Sur l'autre projet de ce poste,
> c'est ce qui a fait passer les vérifications de 20 minutes d'intégration
> continue à 2 à 5 minutes locales, pour le même résultat.

> **C17** — Le script d'installation des crochets passe par
> `git rev-parse --git-common-dir`, jamais par un test `-d .git`. Dans un
> worktree, `.git` est un fichier. Le test naïf échoue en silence, et les agents
> qui travaillent en parallèle dans des worktrees n'ont alors plus de crochets.

---

## 8. L'intégration continue

Un workflow, `verifier.yml`, sur chaque demande de fusion et chaque fusion dans
`main`.

| Job | Contenu | Quand il travaille vraiment |
|---|---|---|
| `changements` | Détecte les chemins modifiés | Toujours — quelques secondes |
| `statique` | `make statique`, `make criteres` | Toujours |
| `tests` | `make test`, `make balayage`, avec un service PostgreSQL de même version (C3) | Toujours |
| `e2e` | `make e2e` (Chromium) | Si les pages terrain, la carte ou leurs routes changent, ou sur l'étiquette `e2e` (le service worker est suspendu en V1) |
| `mutation` | `make mutation` | Si le moteur ou ses tests changent |

**Le piège des vérifications requises conditionnelles.** Un job requis que
GitHub saute à cause d'un filtre de chemins reste « en attente » et bloque la
fusion. D'où le job `changements` : `e2e` et `mutation` démarrent toujours, et
terminent en succès immédiat quand rien ne les concerne.

> **C18** — **Chaque job a un `timeout-minutes`** proportionné à sa durée
> normale (de l'ordre du double). Sans lui, un job bloqué tourne six heures. Sur
> l'autre projet, un run resté pendu était le premier poste de coût du mois.

> **C19** — `concurrency` par branche avec `cancel-in-progress` : un nouvel
> envoi annule la vérification de l'envoi précédent, qui ne sert plus à rien.

> **C20** — **Pas de job planifié la nuit au départ.** Une suite nocturne que
> personne ne lit reste rouge sans conséquence : sur l'autre projet, quatorze
> nuits d'échecs sont passées inaperçues. Si un job planifié s'ajoute un jour,
> il prévient quelqu'un quand il échoue, ou il n'existe pas.

**Caches** : dépendances Composer et npm, navigateurs Playwright. Le cache du
navigateur est épinglé à la version de Playwright du dépôt.

**Dépendances** : Dependabot, **hebdomadaire et groupé** par écosystème (une
demande de fusion PHP, une JavaScript, une Actions par semaine au plus). Une
demande par paquet multiplierait les vérifications, et donc les minutes.

---

## 9. La livraison

Deux cibles : une **recette** et la **production**, sur le serveur de
production (Q17) ou à côté de lui.

**Recette, automatique.** Chaque fusion dans `main` construit une archive de
version (dépendances sans outils de développement, assets compilés, numéro de
version du service worker égal au commit) et la déploie en recette. La recette
a sa propre base, remplie par `make jardin-demo`. Elle ne contient jamais de
données réelles.

**Production, sur décision de Léon.** `livrer.yml` se déclenche à la main
(`workflow_dispatch`) avec la version à livrer. Il déploie **l'archive déjà
testée en recette**, sans rien reconstruire.

> **C21** — **On livre en production ce qui a tourné en recette, octet pour
> octet.** Reconstruire au moment de livrer, c'est livrer autre chose que ce
> qu'on a vérifié.

> **C22** — **La production ne reçoit une version que sur le geste de Léon**
> (CDC §8.8). Le dépôt deviendra privé (§2), et un dépôt privé de ce compte ne
> peut imposer aucun relecteur obligatoire. Le garde est donc, dès le départ,
> une phrase de confirmation saisie au déclenchement et comparée à un secret que
> seul Léon connaît. Le job échoue
> si le secret manque. C'est le mécanisme mis en place le 21/08/2026 sur l'autre
> projet. Aucun agent ne connaît cette phrase ni ne cherche à l'obtenir.

**Pourquoi une recette dès le premier déploiement**, alors que le chapitre 5 (O15) la renvoyait
au lot Ouverture. L'élément nouveau est le §1.5 du CDC : Léon ne relit plus
chaque ligne. Il relit des comportements, et il lui faut un endroit où les voir
tourner qui ne soit pas son vrai jardin. O15 est donc tranché pour la recette.
La question d'une préproduction alimentée par des données réelles, elle, reste
ouverte.

**Le déroulé d'un déploiement** suit le chapitre 5 : dépôt de l'archive dans un
nouveau répertoire de version, migrations (§4 du chapitre 5), bascule du lien
`current`, `queue:restart` (E2), puis appel d'une route de santé. Si la route de
santé échoue, le lien revient sur la version précédente. Ce retour arrière n'est
sûr que si les migrations de la version sont compatibles avec l'ancien code,
ce que le chapitre 5 impose déjà.

**Secrets.** Ceux de la production vivent dans les secrets d'environnement
GitHub et dans le coffre de Léon, jamais dans le dépôt. La clé SSH de
déploiement n'a accès qu'au répertoire de l'application.

---

## 10. Le travail des agents

`AGENTS.md`, à la racine, est le premier fichier qu'un agent lit. Il est court :
ce qu'est le produit en trois lignes, où sont les documents, les commandes du
§4, et les règles ci-dessous. Le détail vit dans `.claude/rules/`, un fichier
par sujet.

**Les règles de travail**

1. **Un chantier = une branche = une demande de fusion**, ouverte en brouillon
   dès le premier envoi. C'est ce qui permet à deux sessions parallèles de voir
   qu'elles travaillent au même endroit. Rien d'autre ne les coordonne.
2. **Un worktree par session**, jamais deux sessions dans le même répertoire.
3. **Les tests des critères d'abord** (TS-6), le code ensuite.
4. **Jamais d'envoi direct sur `main`, jamais de déploiement en production.**
5. **Un attendu de test modifié se signale** (RG-49).
6. **Les identifiants sont stables** : RG, critères, décisions. Une nouvelle
   règle prend le numéro suivant.
7. **Le comportement et sa documentation changent dans la même demande de
   fusion.** Une décision nouvelle entre dans le tableau de décisions du
   document concerné, et dans le journal du §12 du CDC si elle touche le
   périmètre.
8. **Une agronomie qui manque se source, elle ne s'invente pas.** Un agent qui
   a besoin d'une périodicité absente de la bibliothèque la cherche selon le
   protocole de bibliothèque (B1–B4). Sans source vérifiable, l'espèce reste
   hors de la liste de départ et c'est écrit ; l'agent continue (chapitre 7, §3).
9. **Chaque commit porte les trailers `Agent:` et `Session:`**, pour retrouver
   d'où vient un changement.

**La demande de fusion**, par son modèle :

```markdown
## Ce que ça change
## Critères du CDC concernés        (numéros)
## Tests ajoutés
## Attendus de tests modifiés       (RG-49 — vide, ou justifié règle par règle)
## Décisions prises, et ce qui a été écarté
## Vérification locale              (dernière ligne de `make verifier`)
```

La section « ce qui a été écarté » n'est pas décorative. Léon relit des choix :
« j'ai fait X » ne se conteste pas, « j'ai fait X plutôt que Y parce que Y
casse Z » se conteste.

> **C23** — **Léon relit des comportements, pas des lignes** : critères
> concernés, tests ajoutés, attendus modifiés, décisions, et la recette pour
> voir tourner. C'est ce que la demande de fusion doit lui donner sans qu'il
> ouvre le diff.

> ~~**C24** — La fusion est faite par Léon jusqu'à la V1.~~ **Révisée le
> 19/10/2026** : la fusion est **automatique quand la CI est verte**. Décision de
> Léon : l'agent travaille sans interruption, sauf pour l'expérience
> utilisateur (chapitre 7, §3). La production reste déclenchée par Léon seul
> (C22) : aucun utilisateur n'est atteint sans son geste. O17 est levé.

> **C26** — L'agent n'interrompt Léon que pour une question d'expérience
> utilisateur ou un acte qui l'engage (dépôt public, secret, compte, dépense,
> production). Chaque décision technique s'écrit dans
> `docs/plan/decisions-agents.md`, avec ce qui a été écarté (chapitre 7, P2).

---

## 11. Le chantier 0 — amorcer le dépôt

Ce qui doit exister avant la première ligne du moteur. Le reste du découpage est
au chapitre 7 (`docs/plan/`).

1. Auditer l'historique local de `~/jardin` (secrets, coordonnées, adresses,
   noms de lieux ; C25), puis créer le dépôt **public** (Q16), activer la
   détection de secrets et le blocage à l'envoi (C25), envoyer `main`, et
   seulement ensuite poser le ruleset (C2), qui refuserait l'envoi initial.
   Le dépôt local a déjà des commits : c'est l'audit, et non l'ordre, qui
   protège l'historique (décision D-33 du plan).
2. Squelette Laravel 12, Inertia, React, TypeScript strict ; configuration DDEV
   (§3) et contrôle des versions réellement disponibles.
3. E1 / O13 (HTTPS depuis le téléphone) : reporté au premier sprint terrain de
   la partie B (décision D-11 du plan) ; le certificat local de DDEV suffit au
   bureau.
4. `Makefile` avec toutes les cibles du §4, même vides.
5. Outillage du §5, configuré au niveau le plus strict (C8).
6. Crochets git (§7).
7. `verifier.yml` (§8). Le premier run doit être vert sur un test trivial.
   `make criteres` liste alors les 24 critères sans test : c'est le tableau de
   départ (C12).
8. `AGENTS.md`, `.claude/rules/`, modèle de demande de fusion, `dependabot.yml`.
9. Les documents de `docs/` entrent dans le dépôt.

La recette et `livrer.yml` attendent Q17 (serveur de production), et ne bloquent
rien avant le premier déploiement.

---

## 12. Décisions de ce chapitre

| # | Décision | Enjeu |
|---|---|---|
| C1 | Chaque boucle de vérification refait la précédente, plus proprement | Un test qui ne tourne qu'en CI n'est lancé par aucun agent |
| C2 | `main` protégée par ruleset ; aucun envoi direct, Léon compris | Tout passe par une vérification |
| C3 | Même version majeure de PostgreSQL partout | On teste ce qui tourne |
| C4 | Aucune donnée réelle du jardin pilote dans le dépôt | RG-28 |
| C5 | `make verifier` définit « terminé », sa sortie est collée dans la PR | Une affirmation sans preuve ne vaut rien |
| C6 | La CI n'appelle que des cibles `make` | Tout échec se reproduit sur le poste, gratuitement |
| C7 | Toute frontière du chapitre 3 a son test d'architecture | Un agent franchit de bonne foi une frontière non gardée |
| C8 | Analyse statique au niveau le plus strict dès le premier commit, sans jamais baisser | Monter plus tard coûte une campagne |
| C9 | Test de mutation sur le moteur seul | Détecte les tests qui ne vérifient rien |
| C10 | Le seuil de mutation ne fait que monter | Le baisser est une décision RG-49 |
| C11 | Balayages calendaires séparés de la suite rapide | Ils trouvent les bugs de combinaison, sans ralentir N1 |
| C12 | `make criteres` informatif en cours de jalon, bloquant à la livraison | RG-46 |
| C13 | La logique de calcul du front vit hors de React, testée par Vitest | Même séparation que D3 |
| C14 | Aucun test ne dépend d'un service extérieur | Un tiers lent ne dit rien du produit |
| C15 | Le test de cloisonnement énumère toutes les routes | La route de demain est couverte sans qu'on y pense |
| C16 | `pre-push` lance N1 à N3 | Les minutes de CI coûtent, le poste non |
| C17 | Crochets installés via `git rev-parse --git-common-dir` | Les worktrees gardent leurs crochets |
| C18 | `timeout-minutes` sur chaque job | Un run pendu était le premier poste de coût ailleurs |
| C19 | `concurrency` avec annulation par branche | Ne pas payer la vérification d'un état dépassé |
| C20 | Pas de job nocturne sans alerte | Une suite rouge que personne ne lit n'existe pas |
| C21 | La production reçoit l'archive testée en recette | Ne livrer que ce qu'on a vérifié |
| C22 | La production ne part que sur le geste de Léon | CDC §8.8 |
| C23 | La PR donne à Léon des comportements à relire | Il ne relit plus chaque ligne |
| C24 | ~~Fusion par Léon jusqu'à la V1~~ — révisée : fusion automatique sur CI verte | L'agent travaille sans interruption ; la production reste à Léon |
| C26 | L'agent n'interrompt Léon que pour l'UX ou un acte qui l'engage | Décision de Léon, chapitre 7 §3 |
| C25 | Dépôt public au départ : ni secret ni donnée réelle, jamais ; détection de secrets active dès la création | Ce qui a été public le reste |

---

## 13. Points ouverts

| # | Question | Quand |
|---|---|---|
| O16 | Seuil initial de mutations tuées | Au premier moteur complet — à mesurer, pas à supposer |
| O17 | ~~Conditions de passage à la fusion automatique (C24)~~ | **Levé le 19/10/2026** : fusion automatique dès le départ (C24 révisée) |
| O18 | Niveau PHPStan réellement tenable avec Larastan sur Laravel 12 | Au chantier 0 |
| O19 | Fournisseur du tunnel ou du certificat pour le téléphone (E1, O13) | Au chantier 0, avant le premier écran terrain |
| O20 | Disponibilité réelle de la détection de secrets gratuite sur le dépôt public | À la création du dépôt |
| O21 | Date du passage en privé | À la livraison de la V1, ou avant si du contenu sensible doit y entrer |
