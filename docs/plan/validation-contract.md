# Contrat de validation — partie A (socle)

Version 0.1 — 19/10/2026. Écrit avant le code (P6). Applique le CDC v0.13 et les
chapitres techniques 1 à 6, et la méthode du chapitre 7 (`README.md`).

## Sommaire

- [Lire ce contrat](#lire-ce-contrat)
- [Chantier 0 — V1 à V22, V127](#chantier-0--v1-à-v22-v127)
- [Schéma — V23 à V31, V129](#schéma--v23-à-v31-v129)
- [Cloisonnement et comptes — V32 à V41, V128, V135, V136](#cloisonnement-et-comptes--v32-à-v41-v128-v135-v136)
- [Moteur d'échéances — V42 à V61](#moteur-déchéances--v42-à-v61)
- [Bibliothèque — V62 à V70](#bibliothèque--v62-à-v70)
- [Calcul en base et lectures — V71 à V80](#calcul-en-base-et-lectures--v71-à-v80)
- [Inventaire — V81 à V92, V131, V132](#inventaire--v81-à-v92-v131-v132)
- [Journal — V93 à V101, V133](#journal--v93-à-v101-v133)
- [Règles — V102 à V106](#règles--v102-à-v106)
- [Positions et identification — V107 à V114](#positions-et-identification--v107-à-v114)
- [Photos et fonds de plan — V115 à V121](#photos-et-fonds-de-plan--v115-à-v121)
- [Export et démonstration — V122 à V126, V130, V134](#export-et-démonstration--v122-à-v126-v130-v134)
- [Critères du CDC §10 et assertions](#critères-du-cdc-10-et-assertions)
- [Carte de couverture](#carte-de-couverture)

---

## Lire ce contrat

Une assertion décrit un **comportement observable**, jamais une implémentation.
Les tests sont jugés contre elle, pas contre le code (P6). Les numéros sont
stables : une assertion retirée garde son numéro, barré.

**Niveaux** — chaque assertion en porte un :

| Niveau | Ce qui est observé | Comment |
|---|---|---|
| **utilisateur** | Ce qu'une personne voit à l'écran | Playwright — **aucune en partie A**, tout l'écran est en partie B |
| **base** | Ce qui est relu en base après l'action | Pest N2, requête de relecture distincte de l'action |
| **API** | Code de réponse, composant Inertia, props, redirection, erreurs | Pest N3 |
| **câblage** | Commandes, outillage, CI, dépôt, architecture | Script de test, test d'architecture, lecture de l'API GitHub |
| **moteur** | Sortie du moteur pur pour des entrées données | Pest N1, sans base |

**Flux critiques.** Une assertion marquée *flux critique* porte quatre cas, tous
exigés :

- **Nominal** — ce qui se passe quand tout va bien ;
- **Refus** — l'entrée invalide ou interdite, et ce qui **ne change pas** ;
- **Autre jardin** — la même action avec un identifiant du jardin B, depuis le
  jardin A : aucun effet, aucune fuite (RG-21, critère 12) ;
- **En base** — la relecture qui prouve l'effet, faite par une requête séparée.

**Écran alimenté.** Une route de la partie A qui sert un écran de la partie B
nomme cet écran (`conception-ecrans.md`, §4 : T1 à T7, B1 à B13). Les
contrôleurs rendent le composant Inertia du même nom ; les tests l'acceptent
sans que le fichier de page existe encore (décision D-20).

**Critères.** Le test qui vérifie un critère du CDC §10 porte le groupe Pest
`critere-NN` (chapitre 6, §6.3). Un critère n'est entièrement couvert que si
toutes ses assertions le sont ; la partie B complète ceux qui ont un versant
écran.

**Données de test.** Les fenêtres et intervalles écrits dans les tests du moteur
sont des **jeux témoins**, pas des données agronomiques : ils reprennent les
ordres de grandeur de l'annexe A du CDC pour que les cas aient du sens, et ne
sont jamais copiés dans la bibliothèque. Les valeurs de la bibliothèque viennent
du protocole (B1–B4), et de lui seul.

---

## Chantier 0 — V1 à V22, V127

**V1 — L'historique local est audité avant toute publication** · câblage ·
C25, C4, décision D-33
Avant le premier envoi vers le dépôt public, un scanner de secrets parcourt
**tous les commits** de `~/jardin` et ne rapporte rien ; une recherche de
coordonnées, d'adresses et de noms de lieux réels ne rapporte rien non plus. La
liste des lieux est lue hors du dépôt (`$LIEUX_INTERDITS`, par défaut
`~/.config/jardin/lieux-interdits.txt`) ; absente ou vide, l'audit échoue. Le
rapport (commande, nombre de commits lus, résultat) est écrit dans
`decisions-agents.md`. Un résultat non vide arrête le sprint (`ARRET:`).

**V2 — Le dépôt public existe avec ses protections** · câblage · Q16, C2, C25, O20
Relu par l'API GitHub : dépôt public ; détection de secrets et blocage à
l'envoi actifs ; un envoi direct sur `main` est refusé par un ruleset ; la
réécriture d'historique est refusée. L'historique publié est celui audité en V1,
commit pour commit.

**V3 — Un clone propre démarre en une commande** · câblage · C3, chap. 6 §3, décision D-35
Sur le clone neuf de la CI (`actions/checkout` puis `make setup`), `GET /sante`
rend `200` et `{"etat":"ok"}`. Les versions de PHP, PostgreSQL et Node servies,
dans DDEV comme dans la CI, sont celles de `.ddev/config.yaml`, relues par un
test. Un clone neuf local est lancé une fois, en HTTPS local, et relevé dans
`decisions-agents.md`.

**V4 — Une route se déclare dans son module** · câblage · A16
Un fichier `src/<Module>/routes.php` présent est chargé sans modifier aucun
autre fichier ; son absence ne casse rien. Vérifié sur un module témoin de test.

**V5 — `make verifier` enchaîne et s'arrête au premier échec** · câblage · C5, chap. 6 §4
`make verifier` lance `statique`, `test`, `balayage`, `e2e`, `mutation`,
`criteres` dans cet ordre. Une cible qui échoue arrête l'enchaînement avec un
code non nul, et les suivantes ne tournent pas. Toutes les cibles existent, même
vides.

**V6 — La CI de la demande de fusion n'appelle que `make`** · câblage · C6, chap. 6 §11.7
Le premier run de `verifier.yml` sur une demande de fusion est vert sur le test
trivial de V3. Chaque étape qui fait un travail appelle une cible `make`, et
rien d'autre.

**V7 — Un seul bundle Inertia, en TypeScript strict** · câblage · chap. 3 §3 (encart V1), A13 suspendue
`vite build` produit un seul point d'entrée applicatif ; `tsc --noEmit` passe
avec `strict: true` ; une route de test rend un composant Inertia nommé, reçu
par le test avec ses props.

**V8 — Les types TypeScript générés depuis PHP sont tenus à jour** · câblage · chap. 6 §5, O5
`make statique` régénère les types depuis les classes PHP désignées et échoue si
le résultat diffère de ce qui est versionné. Le générateur est choisi au sprint
et écrit dans `decisions-agents.md`.

**V9 — L'analyse statique est au niveau le plus strict tenable** · câblage · C8, O18
`make statique` échoue sur une violation introduite exprès dans chacun des
outils : PHPStan (Larastan), Pint, `tsc`, ESLint, Prettier, et sur une
dépendance déclarée vulnérable (`composer audit`, `npm audit`). Le niveau
PHPStan retenu est écrit avec sa justification (O18).

**V10 — Les crochets git tiennent dans un worktree** · câblage · C16, C17
Après `make setup`, puis `git worktree add`, un envoi depuis le worktree lance
`make statique` puis `make test` ; un commit qui indexe un fichier PHP mal
formaté le reçoit formaté (`pre-commit`). Les deux sont vérifiés par un test de
script. L'installation passe par `git rev-parse --git-common-dir`.

**V11 — Les frontières du chapitre 3 sont des tests** · câblage · A2, C7, chap. 3 §9
Un `use Illuminate\…`, `Carbon`, `now()`, `time()` ou `date()` dans
`src/Echeances/Moteur/` fait échouer `make test`. Un `DB::table`, `DB::select`
ou `DB::statement` dans `src/` aussi.

**V12 — `make criteres` est le tableau d'avancement** · câblage · C12, RG-46
`make criteres` lit les numéros 1 à 24 du §10 du CDC, cherche les groupes
`critere-NN` dans `tests/`, et liste ceux qui n'en ont pas. Il rend `0`
pendant le jalon, et `1` avec `LIVRAISON=1` s'il en manque un.

**V13 — La mutation tourne avant d'en dépendre** · câblage · C9, O16, décision D-37
`pest --mutate` tourne dans le conteneur DDEV et dans la CI, avec un pilote de
couverture, sur une classe témoin placée dans `src/Echeances/Moteur/Temoin/` :
une mutation survivante y est rapportée, une mutation tuée aussi, et le job
`mutation` de la demande de fusion du 0e travaille réellement. C'est
l'instrument dont le sprint 3e a besoin ; la classe témoin est retirée au
sprint 3a.

**V14 — Les jobs conditionnels ne bloquent jamais la fusion** · câblage · chap. 6 §8, décision D-37
Les jobs `e2e` et `mutation` démarrent sur toute demande de fusion. Sans
changement qui les concerne, ils finissent en succès en moins d'une minute
(relevé sur la demande de fusion du 0f) ; avec un changement dans
`src/Echeances/Moteur/` ou ses tests, `mutation` travaille réellement (relevé
sur celle du 0e).

**V15 — Aucun job ne tourne sans limite ni pour rien** · câblage · C18, C19, C20
Chaque job porte un `timeout-minutes` ; le workflow a une `concurrency` par
branche avec annulation ; aucun déclencheur planifié n'existe. Vérifié par un
test qui lit le YAML.

**V16 — La fusion est automatique sur CI verte, et seulement alors** · câblage · C2, C24 révisée, P4
Relu par l'API GitHub : le ruleset de `main` exige les jobs de `verifier.yml`,
une branche à jour, aucune réécriture ; la fusion automatique est activée sur le
dépôt. Le ruleset se modifie par `PUT` avec la charge complète, versionnée.

**V17 — Un agent trouve la consigne dans le dépôt** · câblage · chap. 6 §10, CDC §8.8
`AGENTS.md` (court : produit, documents, commandes, règles), `.claude/rules/`
(git, tests, documentation), le modèle de demande de fusion avec ses six titres,
et `dependabot.yml` hebdomadaire groupé par écosystème (composer, npm, actions)
existent. Un test vérifie les six titres et la périodicité.

**V18 — L'enchaînement va au bout du plan** · câblage · P8
`scripts/plan/enchainer.sh` lance une session neuve par sprint et rend `0`
quand toutes les cases des sections `## Sprint` de `tasks.md` sont cochées ;
des cases hors de ces sections ne le retiennent pas.

**V19 — Une passe qui ne finit rien arrête l'enchaînement** · câblage · P8
Une passe qui n'a ajouté aucune ligne à `handoffs.jsonl` du clone principal
arrête la boucle avec le code `3` et montre la fin de `run.log` ; une passe
terminée sur un paragraphe `ARRET:` aussi. Avant chaque passe, la boucle met
`main` à jour (`git pull --ff-only`) et relit les cases de `tasks.md` depuis
`main`. Une passe coupée sans paragraphe `ARRET:` est
relancée une fois, une seule.

**V20 — Une question UX met un sprint de côté, pas l'enchaînement** · câblage · P3
Un sprint dont l'en-tête porte `**En attente :** Q-UX-n` est sauté. Quand tous
les sprints restants le sont, la boucle rend `5` et appelle la commande de
prévenance si elle est définie (O27).

**V21 — Aucun échec ne se lit comme une réussite** · câblage · P8
Une passe qui sort en erreur rend `1` ; un plan absent ou sans section
`## Sprint` rend `2`, jamais « terminé » ; le plafond `MAX_SPRINTS` atteint
rend `4`.

**V22 — La taille des sprints se mesure** · câblage · P5, O28, décision D-29
`scripts/plan/check-sprint-size.sh docs/plan` rend, pour chaque sprint de ce
plan, le nombre d'assertions (lues dans la carte de couverture), de fichiers et
de classes de test (lus dans les listes **Fichiers** et **Classes de test** de
l'en-tête) ; il rend `1` sur un sprint fabriqué à six assertions, sur un
commentaire `taille` qui ne dit pas le compte des listes, et sur un chemin
compté cité dans une tâche mais absent des listes.

**V127 — Une réponse de Léon arrive sur `main` par une demande de fusion** · câblage · P3, décision D-30
Une ligne « Réponse : » remplie dans `questions-ux.md` du clone principal est
portée sur `main` par `scripts/plan/porter-reponses.sh` : une branche, une
demande de fusion fusionnée, le marqueur `En attente` correspondant retiré de
`tasks.md`, la copie de travail rendue propre. Toute autre modification non
commitée fait rendre `1` sans rien envoyer ; rien n'est jamais envoyé
directement sur `main`.

---

## Schéma — V23 à V31, V129

**V23 — Les migrations du socle et le jeu de référence se rejouent** · base · chap. 4 §5, décision D-38
Les migrations 1 à 4 passent sur une base vide, et `migrate:fresh` les rejoue.
Le jeu de référence crée les 5 profils climatiques avec les décalages du CDC
§5.4 (−15, −7, 0, +7, +21) et les types d'intervention du CDC §3.7 ; lancé
deux fois, il laisse exactement les mêmes lignes. La base refuse un second type
fourni de même libellé, et un même libellé deux fois dans un jardin ; deux
jardins peuvent avoir chacun le leur.

**V24 — Un jardin a toujours un profil, un accès a toujours un rôle** · base · RG-23, RG-20
La base refuse un jardin sans profil climatique ; elle refuse une adhésion dont
le rôle n'est ni `proprietaire` ni `membre`, et une seconde adhésion du même
utilisateur au même jardin.

**V25 — Le code court est unique par jardin, le nom par zone** · base · RG-13, S8
Deux plantes de deux jardins peuvent porter le même code court ; deux plantes du
même jardin, non. Deux plantes de même nom dans une même zone sont refusées, et
acceptées dans deux zones différentes.

**V26 — Une règle de référence ne se dit pas vérifiée sans deux sources** · base · S9, B1, B2, RG-31, décision D-39
Au commit, la base refuse une règle de référence `verifiee` qui a moins de deux
sources, la même URL deux fois pour une règle, une règle sans `confiance`, et
deux versions en vigueur d'une même clé.

**V27 — Toute table métier porte son jardin** · base · RG-22, D1, décision D-02
Une requête sur le catalogue de PostgreSQL trouve `jardin_id NOT NULL` dans
toutes les tables, à l'exception d'une liste fermée relue par le test : tables
de référence (`profils_climatiques`, `familles_botaniques`, `especes`,
`regles_reference`), `utilisateurs`, `jardins` lui-même, tables techniques de
Laravel, et `types_intervention` où il est nullable (`NULL` = fourni). Une table
nouvelle hors de la liste et sans `jardin_id` fait échouer le test.

**V28 — Une seule règle active par plante et par type** · base · S15, D10
La base refuse une seconde règle active pour un même couple (plante, type
d'intervention) ; elle accepte une version inactive portant `remplacee_le` à
côté de la version active ; elle refuse une règle active qui porte
`remplacee_le`.

**V29 — Une échéance en base est une échéance matérialisée** · base · S10, S16, RG-06
La base refuse une échéance dans un état calculé (`a_venir`, `ouverte`,
`optimale`, `urgente`), une échéance sans explication, et deux échéances pour un
même couple `(regle_id, due_le)`.

**V30 — Les contraintes de forme tiennent** · base · RG-44, chap. 4 §3.5 et §3 bis
La base refuse un butoir donné à moitié (jour sans mois), une photo rattachée ni
à une plante ni à une intervention, un fond de plan `image` sans fichier ou sans
calage, et un second fond de plan actif dans un même jardin.

**V31 — Les index de lecture commencent par le jardin** · base · S12, A9
Les trois index du chapitre 4 §4 existent et commencent par `jardin_id` ; le
plan d'exécution de la requête d'historique `DISTINCT ON` de A9 utilise
`idx_interventions_historique`.

**V129 — L'entretien ne désigne jamais une zone** · base · RG-34, décision D-62
Aucune colonne `zone_id` n'existe sur `regles`, `interventions` ni `echeances` ;
`plantes.zone_id` est `NOT NULL`. Une intervention sur une zone passe par ses
plantes.

---

## Cloisonnement et comptes — V32 à V41, V128, V135, V136

**V32 — Le jardin courant n'est jamais nul** · API · A11
`JardinCourant::id()` sans positionnement préalable lève une exception ; il ne
rend jamais `null`. Positionné, il rend l'identifiant positionné.

**V33 — Le filtre par jardin s'applique sans qu'on l'écrive** · base · D1, RG-21, critère 12
Le jardin A positionné, une lecture de zones ou de plantes ne rend aucune ligne
du jardin B — par liste, par recherche d'identifiant, par relation. Une création
sans `jardin_id` reçoit celui de A.

**V34 — Un modèle métier sans filtre ne passe pas** · câblage · C7, RG-21, décision D-55
Un test d'architecture échoue si une classe de modèle de `src/*/Domaine/`
n'utilise pas le trait de cloisonnement, sauf les modèles de la liste
référentiel, relue par le test ; `TypeIntervention`, hors de cette liste, porte
le filtre global des types visibles.

**V35 — Un job connaît son jardin ou ne démarre pas** · base · A20, E3
Un job qui porte un `jardin_id` repositionne le jardin courant à son démarrage
et n'écrit que dans ce jardin ; un job sans `jardin_id` échoue au démarrage,
sans rien écrire.

**V36 — Connexion simple** · API · *flux critique* · CDC §6 lot 0, décision D-47 · écran B1
- Nominal : un courriel et un mot de passe justes ouvrent une session et
  redirigent (`Location`) vers `/` ; la page d'arrivée est vérifiée par V76.
- Refus : un mot de passe faux renvoie une erreur qui ne dit pas lequel des deux
  champs est faux ; sans session, toute route métier redirige (`Location`) vers
  `/connexion`.
- Autre jardin : sans objet (un compte, ses adhésions).
- En base : `derniere_connexion_le` est mise à jour, rien d'autre.

**V37 — Le compte propriétaire se crée à l'installation, une fois** · base · CDC §6 lot 0
Une commande crée le compte propriétaire, lit le mot de passe sur l'entrée
standard (jamais en argument), et refuse de s'exécuter si un compte existe déjà.

**V38 — Le jardin courant arrive sur toutes les pages** · API · RG-43, chap. 3 §4 · toutes les pages
Toute réponse Inertia d'une page authentifiée porte, en props partagées,
l'utilisateur, et le jardin courant (identifiant, nom, profil climatique).

**V39 — Créer un jardin et choisir son profil** · API · *flux critique* · RG-22, RG-23, RG-26 · écran B2
- Nominal : un nom et un profil créent le jardin et l'adhésion `proprietaire`
  de l'utilisateur, et le jardin devient le jardin courant.
- Refus : sans nom ou sans profil, ou avec un profil inconnu, rien n'est créé.
- Autre jardin : le nouveau jardin ne voit aucune donnée de l'autre jardin du
  même utilisateur (zones, plantes).
- En base : une ligne `jardins`, une ligne `adhesions` ; changer ensuite le
  profil ne modifie que `jardins.profil_climatique_id`. La commune est
  facultative ; un code de commune inconnu → `422`, rien n'est créé ; seul
  `jardins.commune_insee` est écrit, jamais des coordonnées (RG-28).

**V40 — On ne bascule que vers un jardin dont on est membre** · API · RG-20, RG-43
Basculer vers un jardin où l'utilisateur a une adhésion change le jardin
courant ; vers un autre, la réponse est `403`, et le jardin courant ne change
pas.

**V41 — Aucune route ne laisse passer un autre jardin** · API · C15, RG-21, **critère 12**
Le test énumère **toutes** les routes de l'application et crée deux jardins.
Pour chaque route qui prend un identifiant, appelée depuis A avec un identifiant
de B : `404` (ou `403`), corps sans aucun marqueur de B. Pour chaque route de
liste : aucun identifiant ni marqueur de B dans le corps. Pour chaque route qui
écrit : les lignes de B ont la même empreinte avant et après. Une route qui
écrit sans charge déclarée dans le registre du test, ou une route absente de la
liste d'exceptions (bibliothèque, santé, connexion), fait échouer le test.

**V128 — Un membre ne fait que ce que son rôle permet** · API · CDC §2.1, RG-20, décision D-49
Chaque route du registre déclare le rôle qu'elle exige. Connecté en membre du
jardin A, une route réservée au propriétaire rend `403` et laisse A intact ; une
route ouverte au membre (lecture, `POST /interventions`, `POST /echeances/fait`,
`POST /photos`, `GET /export`) rend le même code que pour le propriétaire.

**V135 — Le référentiel des communes s'importe à l'identique** · base · RG-52, §8.6, décision D-66
Importé deux fois depuis le même fichier témoin (communes, stations, altitudes
inventées), le référentiel donne les mêmes lignes ; chaque commune porte son
altitude, sa station, son écart de température, son décalage calculé et son
profil proposé. Une station dont la période est incomplète n'est jamais
retenue. Aucun appel réseau pendant l'import ni pendant les tests.

**V136 — Le profil se propose depuis la commune** · API · *flux critique* · RG-23, RG-26, RG-52, décision D-66 · écran B2
- Nominal : une commune du témoin à basse altitude rend le profil le plus
  proche de son décalage calculé, avec la station, l'écart et le décalage
  pour l'explication ; une commune au-dessus de 600 m rend « Montagne » ; une
  commune entre 400 et 800 m rend en plus `demander_altitude: true`.
- Recherche : par nom (sans accents ni casse) ou par code postal, 10
  résultats au plus.
- Refus : une commune inconnue → `404`, et la liste des cinq profils reste
  proposée.
- Rien n'est écrit : seul `POST /jardins` enregistre la commune et le profil
  choisis.

---

## Moteur d'échéances — V42 à V61

Toutes les assertions de cette section sont au niveau **moteur** : entrées
fixées, date du jour en paramètre, aucune base.

**V42 — Une fenêtre à cheval sur l'année est projetée sur la bonne année** · moteur · A7, TS-6 n°1 et 2
Fenêtre `01/12 → 31/03` : le 15/01/2027, la fenêtre courante est
`01/12/2026 → 31/03/2027` ; le 15/06/2027, la prochaine est
`01/12/2027 → 31/03/2028`.

**V43 — Le décalage déplace les deux bornes et l'optimale** · moteur · RG-25, RG-26, **critère 13**
Une même fenêtre projetée pour « Nord, Est, océanique frais » (+7) et pour
« Midi » (−15) donne des débuts, fins et optimales décalés de 22 jours.

**V44 — Un butoir rogne, il ne déplace pas** · moteur · RG-44, RG-45, A25, critère 4
Fenêtre de haie `16/08 → 15/03` avec butoirs `16/08` et `15/03` : en montagne
(+21) elle devient `06/09 → 15/03`, dans le Midi (−15) `16/08 → 28/02` (29/02
les années bissextiles). Sans butoir, les mêmes projections débordent
(`06/09 → 05/04`, `01/08 → 28/02`).

**V45 — Le 29 février n'est pas un cas d'erreur** · moteur · chap. 2 §3, décision D-09
Une borne au 29/02 est projetée au 29/02 les années bissextiles et au 28/02 les
autres ; une fenêtre `01/02 → 29/02` reste valide toute année.

**V46 — Chaque jour d'une fenêtre a exactement un état** · moteur · chap. 3 §7 (cascade), RG-40
Pour chaque jour du 01/09/2026 au 30/04/2027, la taille du pommier (jeu témoin
`01/12 → 31/03`, optimale `01/02 → 15/03`) a exactement un état, dans l'ordre
normatif : manquée, à venir, urgente (15 jours avant la fin), optimale, ouverte.
Pour le cerisier témoin (`01/08 → 30/09`, optimale jusqu'au 31/08), du 01/09 au
14/09 l'état est `ouverte` ; quand l'optimale court jusqu'à la fin, les quinze
derniers jours sont `urgente`.

**V47 — Une fenêtre close sans intervention est manquée** · moteur · RG-07, **critère 6**
Au 01/04/2027, sans intervention, la taille du pommier rend une échéance
`manquee` (fenêtre 2026-2027) et une prochaine échéance sur
`01/12/2027 → 31/03/2028`. Aucune manquée n'est rendue pour une fenêtre qui a
commencé avant la date d'effet de la règle.

**V48 — Une périodicité pluriannuelle saute des fenêtres** · moteur · CDC §4.2 (R2)
Périodicité 3 ans, taillé le 10/02/2027 : la prochaine fenêtre est celle qui
commence le 01/12/2029 ; les fenêtres sautées ne sont jamais rendues manquées.

**V49 — L'identité d'une échéance est déterministe** · moteur · H11, S16, CDC §9 point 5 bis, décision D-07
Deux calculs avec les mêmes entrées rendent les mêmes couples
`(regle_id, due_le)`. Pour une règle R2, `due_le` est le début de la fenêtre
projetée, décalée et rognée.

**V50 — Toute échéance s'explique** · moteur · RG-06, RG-26, RG-31, critère 3, critère 13, décision D-51
Chaque échéance rendue porte une explication structurée : type de règle,
fenêtre projetée ou intervalle, décalage appliqué, confiance, origine de la
règle (`bibliotheque`, `ajustee`, `libre`). Rédigée en phrase avec le libellé
du type et le nom de la plante, elle mentionne le décalage quand il n'est pas
nul et le caractère indicatif quand la règle l'est (« Taille du pommier,
fenêtre déc.–mars décalée de 15 jours pour votre climat. ») ; la phrase d'une
règle `libre` est celle de V106.

**V51 — Une règle à plusieurs fenêtres rend une seule échéance** · moteur · RG-36, A8
Règle témoin à deux fenêtres (novembre, février) : après une intervention le
10/11, au 15/12 la seule échéance à venir est celle de février.

**V52 — Plusieurs fenêtres manquées sont toutes consignées** · moteur · RG-07, RG-40
Si les deux fenêtres d'une règle se ferment sans intervention, les deux sont
rendues `manquee`, et une seule échéance à venir est rendue.

**V53 — Une occurrence close n'est plus proposée** · moteur · CDC §4.4, décision D-05
Une échéance déjà matérialisée `suspendue` ou `manquee` (fournie par
l'historique) n'est plus rendue comme à venir ni rendue manquée une seconde
fois ; la suivante est proposée.

**V54 — Une intervention hors fenêtre ne solde rien** · moteur · H9, RG-05
Une taille enregistrée en juin, hors de toute fenêtre, est prise comme dernière
occurrence sans solder de fenêtre ; la prochaine échéance est la fenêtre
suivante.

**V55 — L'intervalle d'une tonte suit la saison** · moteur · **critère 5**, CDC §4.2 (R1)
Jeu témoin (7 jours au printemps, 10 jours à l'automne, 0 en hiver), une seule
règle : une tonte du 03/04 rend une échéance au 10/04 ; une tonte du 05/10 rend
une échéance au 15/10.

**V56 — Hors saison, l'échéance est reportée à l'ouverture suivante** · moteur · **critère 15**, A24, RG-40
Une dernière tonte le 15/11 ne rend jamais une échéance en janvier : elle est
reportée au premier jour de la saison suivante. Une dernière tonte le 15/12
(intervalle 0) rend la même échéance reportée, jamais « aucune échéance ». Sans
aucune tonte, en janvier, l'échéance est l'ouverture de la saison (décision
D-10).

**V57 — Le décalage déplace la saison, jamais l'intervalle** · moteur · RG-25
Dans le Midi (−15), la saison de tonte s'ouvre quinze jours plus tôt qu'en
plaine, et l'intervalle reste de 7 jours ; aucune échéance R1 ne voit sa valeur
d'intervalle modifiée par un profil.

**V58 — Une tonte en retard est due aujourd'hui, sauf hors saison** · moteur · chap. 3 §7, étape 4
Une échéance R1 passée est rendue due aujourd'hui si la saison est ouverte, et
à l'ouverture suivante sinon.

**V59 — Les invariants tiennent chaque jour de l'année** · moteur · C11, chap. 6 §6.2, TS-8
`make balayage` évalue chaque jour d'une année bissextile et d'une année
ordinaire, pour les cinq profils et chaque règle du jeu témoin : aucune taille de
haie due du 16/03 au 15/08 ; exactement une échéance à venir par règle active ;
aucune échéance R1 due hors saison ; aucun intervalle décalé ; deux calculs
identiques, mêmes identités. `make moteur` reste sous la seconde.

**V60 — Une haie n'est jamais taillée en nidification, dans aucun climat** · moteur · **critère 4**, RG-44
Pour les cinq profils et chaque jour du 16/03 au 15/08, la règle de haie témoin
(`16/08 → 15/03`, butoirs `16/08` et `15/03`) n'est ni ouverte, ni optimale, ni
urgente.

**V61 — Les tests du moteur tuent ses mutations** · câblage · C9, C10, O16
`make mutation` sur `src/Echeances/Moteur/` atteint le seuil écrit dans le
dépôt ; ce seuil est la valeur mesurée au premier moteur complet ; le baisser
fait échouer un test qui compare au seuil versionné, sauf mention RG-49.

---

## Bibliothèque — V62 à V70

**V62 — La bibliothèque se charge et se recharge sans rien changer** · base · RG-24, chap. 4 §5
La commande de chargement lit les fichiers de `bibliotheque/` et remplit
familles, espèces et règles de référence (fenêtres, butoirs et intervalles dans
leurs paramètres) ; relancée sans changement de fichier, elle ne modifie aucune
ligne. Un fichier mal formé est refusé en entier, avec le fichier et la ligne en
cause, et la base reste telle qu'elle était.

**V63 — Corriger la bibliothèque ne touche aucun jardin** · base · RG-24, RG-39
Une règle de référence modifiée dans les fichiers crée, au chargement, une ligne
de version suivante ; l'ancienne reçoit `remplacee_le` ; aucune ligne de
`regles`, `fenetres` ou `echeances` d'aucun jardin n'est modifiée.

**V64 — La structure de la bibliothèque se teste comme du code** · base · B-T4 à B-T9, B-T11, B2, B3, chap. 6 §6.7, décision D-52
Les tests B-T4 à B-T9 et B-T11 tournent dans `make test` sur les fichiers du
dépôt, et chacun échoue sur une donnée fautive témoin. Une règle sans source,
une règle `verifiee` qui cite moins de deux sources (B2), une source sans URL,
libellé ou date de consultation, et une source dont le domaine figure dans
`bibliotheque/sources-exclues.yaml` (B3) échouent.

**V65 — Chercher une espèce, et ses règles proposées arrivent déjà calées** · API · CDC §5.1, RG-25, RG-31, RG-51 · écran B7
La recherche trouve une espèce par son nom commun ou latin, sans égard à la
casse ni aux accents, 20 résultats au plus. La route des règles proposées d'une
espèce rend ses règles de référence en vigueur, avec les fenêtres projetées
pour le profil du jardin courant, la confiance et les sources de chacune. Pour
une espèce à variantes de conduite, elle rend les règles communes et celles de
la variante demandée, et refuse (`422`) une demande sans variante. Les
deux routes sont dans la liste d'exceptions du test de cloisonnement (lecture
seule, partagée) ; aucune route n'écrit dans la bibliothèque.

**V66 — Les sources se vérifient à la main, jamais en CI** · câblage · C14, chap. 6 §6.7
`make sources` interroge chaque `source_url` et liste celles qui ne répondent
pas ; aucune cible lancée par la CI ne l'appelle.

**V67 — Toute espèce annoncée a une règle sourcée, ou sort de la liste** · base · B-T10, B1–B5, CDC §5.1, décisions D-52, D-63
Chaque espèce de la liste de départ du CDC §5.1 porte au moins une règle dont
les deux sources ont été consultées pendant la session qui l'a écrite (B4), ou
figure dans `bibliotheque/retraits.yaml` avec la raison de son retrait, lue par
le test et recopiée dans `decisions-agents.md`. Aucune valeur n'est écrite de
mémoire.

**V68 — La cohérence agronomique tient** · base · B-T1, B-T2, B-T3
Aucun fruitier à noyau n'a de fenêtre de taille qui recouvre décembre ou janvier,
et aucun, hors pêcher et nectarinier, ne recouvre février ;
toute haie a une fenêtre qui exclut le 16/03 → 15/08 ; aucun arbuste à floraison
printanière n'est taillé avant sa floraison.

**V69 — La bibliothèque tient dans les cinq climats** · base · B-T12, B-T13, B-T14, B7, B8
Toute haie porte les butoirs `16/08` et `15/03` ; pour chaque profil, chaque
fenêtre projetée puis rognée dure au moins 14 jours et contient sa période
optimale ; aucune fenêtre de haie projetée ne recouvre le 16/03 → 15/08. Le
calcul passe par la projection du moteur, pas par une copie.

**V70 — La haie livrée respecte la nidification de bout en bout** · base · **critère 4**
La règle de haie de la bibliothèque, chargée en base, copiée sur une plante de
chacun des cinq jardins témoins (un par profil), n'est jamais proposée entre le
16/03 et le 15/08 par le calcul complet.

---

## Calcul en base et lectures — V71 à V80

**V71 — Le calcul d'un jardin coûte un nombre fixe de requêtes** · base · A9, RG-19, décision D-44
Le calcul complet des échéances d'un jardin fait le même nombre de requêtes,
au plus dix, pour 5 plantes et pour 500 plantes à 3 règles ; la liste attendue
est écrite dans le test (jardin et profil, règles, fenêtres, intervalles,
plantes, zones, types, dernières interventions en une seule requête
`DISTINCT ON` (A9), échéances matérialisées).

**V72 — Deux climats, deux calendriers, depuis la base** · base · **critère 13**
Deux jardins en base, l'un « Nord, Est, océanique frais », l'autre « Midi »,
avec les mêmes espèces : les fenêtres de taille calculées sont décalées de
22 jours, et chaque explication mentionne le décalage appliqué.

**V73 — Une manquée se matérialise une fois, au calcul** · base · RG-07, D3, **critère 6**
Le 01/04/2027, le premier calcul écrit une échéance `manquee` avec son
explication et son décalage appliqué ; un second calcul n'écrit rien de plus.

**V74 — Le calcul d'un jardin ne lit que ce jardin** · base · D1, critère 12
Le calcul du jardin A ne charge aucune règle ni intervention de B, même pour une
plante du même nom et un type identique ; toute échéance matérialisée porte le
`jardin_id` de A.

**V75 — Changer de profil change les dates, pas les règles** · base · CDC §9 point 9, RG-26
Changer le profil d'un jardin modifie les dates calculées sans modifier aucune
ligne de `regles` ni de `fenetres` ; une échéance déjà matérialisée garde son
`decalage_climatique_applique_j` d'origine.

**V76 — Le tableau de bord, à toute date** · API · *flux critique* · **critère 3**, RG-06, décision D-47 · écrans B4, T1
- Nominal : à trois dates fixées (hiver, printemps, automne), la page rend les
  échéances dues aujourd'hui, cette semaine et ce mois, groupées par zone,
  chacune avec sa phrase d'explication et sa clé `(regle_id, due_le)`. Après
  une connexion, la redirection suivie arrive sur cette page.
- Refus : sans session, redirection vers la connexion.
- Autre jardin : aucune échéance, zone ou plante de B.
- En base : les manquées détectées sont matérialisées (V73), rien d'autre n'est
  écrit.

**V77 — Les échéances nombreuses s'agrègent** · API · CDC §8.7, critère 11 · écran B4
Une zone qui compte 14 tailles dues rend un groupe (zone, type, nombre = 14)
dont le détail ne part pas avec la page : il se charge à la demande.

**V78 — La fiche d'une plante** · API · *flux critique* · P6, RG-07, critère 6 · écrans B6, T5
- Nominal : la fiche rend la plante, les échéances à venir de ses règles, ses
  échéances manquées, et son historique chargé à la demande.
- Refus : plante inexistante → `404`.
- Autre jardin : plante de B → `404`, sans marqueur de B.
- En base : sans objet (lecture), hors matérialisation V73.

**V79 — Le tableau de bord reste rapide à 500 plantes** · base · RG-19, O7
À 500 plantes et 1 500 règles, le tableau de bord rend sa réponse avec un nombre
de requêtes constant ; le temps mesuré et le seuil retenu sont écrits dans
`decisions-agents.md`, et le test échoue au-delà du seuil.

**V80 — Ce qui n'est pas dû reste au planning** · API · CDC §3.9, RG-40
Une échéance `à venir` (hors fenêtre, ou tonte reportée à la saison suivante)
n'apparaît pas dans « aujourd'hui » ; elle apparaît dans le planning à son mois.

---

## Inventaire — V81 à V92, V131, V132

**V81 — Le code court est attribué une fois pour toutes** · base · RG-13, S8, décision D-03
Chaque plante créée reçoit un code `PREFIXE-NNN` tiré d'un compteur par jardin
et par préfixe ; un code n'est jamais réattribué, même après archivage ; deux
créations simultanées dans le même jardin, par deux connexions réelles dont les
transactions sont validées, reçoivent deux codes distincts.

**V82 — Déclarer et renommer des zones** · API · *flux critique* · CDC §6 lot 0, P0 · écran B3
- Nominal : créer une zone, la renommer.
- Refus : un nom déjà pris dans le jardin, ou vide → erreur sur le champ, rien
  ne change.
- Autre jardin : renommer une zone de B → `404`, la zone de B est intacte ; un
  nom déjà pris dans B est accepté dans A.
- En base : la zone porte le `jardin_id` de A et le nouveau nom.

**V83 — Créer une plante, avec les règles de son espèce** · API · *flux critique* · RG-39, RG-09, P4 · écrans B7, T3
- Nominal : nom, zone et espèce créent la plante, son code court, et une copie de
  chaque règle de référence en vigueur de l'espèce (avec fenêtres, butoirs,
  intervalles, confiance, et le lien `regle_reference_id`), sauf celles que la
  demande écarte. Sans espèce, la plante est créée sans règle.
- Refus : zone absente, nom déjà pris dans la zone, espèce inconnue → erreur,
  rien n'est créé.
- Autre jardin : zone de B → erreur, rien n'est créé ni dans A ni dans B.
- En base : une plante, N règles version 1 actives, leurs fenêtres et
  intervalles, toutes au `jardin_id` de A.

**V84 — Une règle proposée s'ajuste à la création** · base · P4, RG-09
Une valeur ajustée dans la demande (périodicité, bornes de fenêtre, intervalle)
est celle de la règle copiée ; la règle garde son lien vers la référence et la
version 1.

**V85 — Créer 150 plantes en une demande** · API · *flux critique* · **critère 1**, critère 24, CDC §8.7, décision D-62 · écran B3
- Nominal : une demande de 150 lignes (nom, espèce, zone) réparties sur 6 zones
  crée 150 plantes, leurs codes et leurs règles, en moins de 5 secondes dans
  l'environnement local. Une ligne qui porte une quantité de 12 (« 12
  pommiers ») crée douze plantes aux noms distincts dans la zone, nommées selon
  la réponse à Q-UX-3. Le versant écran (coller depuis un tableur, maquette
  B3 · 04) est en partie B.
- Refus : voir V86.
- Autre jardin : voir V87.
- En base : 150 plantes, toutes au `jardin_id` de A.

**V86 — Une ligne fautive est rattachée à son numéro** · API · CDC §8.7, Q-UX-2, Q-UX-3 · écran B3
Une demande dont une ligne a une espèce inconnue, une zone inconnue ou un nom
déjà pris rend les erreurs rattachées à leur numéro de ligne. Ce qui est créé
suit la réponse à Q-UX-2 : rien (tout ou rien), ou les seules bonnes lignes
(option de la maquette B3 · 04) ; jamais une ligne fautive.

**V87 — Un lot ne traverse pas les jardins** · API · RG-21, critère 12 · écran B3
Une ligne qui désigne une zone de B fait refuser la demande ; rien n'est créé
dans A ni dans B.

**V88 — Archiver une plante sans rien perdre** · API · *flux critique* · RG-02, RG-13, §2.1 · écran B6
- Nominal : archiver avec un motif ; la plante sort des listes courantes, son
  historique et ses échéances passées restent consultables, ses règles cessent
  de produire des échéances.
- Refus : sans motif → erreur ; un membre (non propriétaire) → `403`.
- Autre jardin : plante de B → `404`, intacte.
- En base : `etat = archivee`, `archivee_le`, motif ; règles `est_active = false`
  sans `remplacee_le` (décision D-22) ; le code court reste attribué.

**V89 — La liste des plantes ne déborde jamais** · API · **critère 11**, RG-19 · écran B5
À 500 plantes, la liste rend au plus 50 lignes par page, avec recherche par nom
ou code court, filtres par zone et par état, et le nombre total ; une recherche
par code court exact rend la plante.

**V90 — Modifier des plantes en masse** · API · *flux critique* · CDC §8.7 · écran B5
- Nominal : déplacer N plantes vers une autre zone, ou changer leur repère, en
  une demande.
- Refus : un nom qui deviendrait double dans la zone d'arrivée → rien ne change.
- Autre jardin : un identifiant de B parmi les N → rien ne change, ni dans A ni
  dans B.
- En base : les N plantes portent la nouvelle valeur.

**V91 — Le catalogue des types est extensible, et ordonné pour le terrain** · API · *flux critique* · RG-03, P1 · écrans B11, T2
- Nominal : la liste rend les types fournis et ceux du jardin ; créer un type
  l'ajoute au jardin ; pour le terrain, les 5 types utilisés le plus récemment
  dans le jardin viennent en tête.
- Refus : libellé vide, famille inconnue, ou libellé déjà présent → erreur.
- Autre jardin : un type créé par B n'apparaît pas dans A et n'y est pas
  utilisable.
- En base : le type porte le `jardin_id` de A ; les types fournis ont
  `jardin_id` nul et ne sont pas modifiables.

**V92 — Archiver une zone** · API · CDC §6 lot 0, Q-UX-6, décision D-04 · écran B3
Une zone archivée sort des listes et des choix de zone ; le traitement d'une
zone qui contient des plantes actives suit la réponse à Q-UX-6 ; une zone de B
→ `404`.

**V131 — La tournée du jour se propose depuis la position** · API · *flux critique* · **critère 21** (versant serveur), décision D-65 · écran T7
- Nominal : depuis une position, les échéances du jour sont rendues dans
  l'ordre du plus proche voisin, de plante en plante ; les plantes sans
  position suivent, groupées par zone dans l'ordre alphabétique.
- Sans position : le même groupement par zone, sans tri par distance.
- Autre jardin : aucune échéance de B dans la tournée de A.
- Rien n'est écrit en base : l'ordre modifié à l'écran ne vaut que pour la
  sortie en cours (T7 · 03).

**V132 — Modifier une plante** · API · *flux critique* · CDC §6 lot 0, RG-13, décision D-54 · écran B6
- Nominal : nom, zone, repère, variété, date de plantation et notes se
  modifient ; le code court, les règles et la géométrie ne changent pas.
- Refus : nom déjà pris dans la zone d'arrivée → `422`, rien ne change ; un
  membre → `403`.
- Autre jardin : plante ou zone de B → `404` ou `422`, B intact.
- En base : la ligne relue porte les nouvelles valeurs.

---

## Journal — V93 à V101, V133

**V93 — Enregistrer une intervention** · API · *flux critique* · RG-05, CDC §3.8 · écrans T2, T3, T4, B8
- Nominal : un identifiant fourni par le client, une plante, un type, une date,
  et facultativement une note et une durée, créent une intervention dont
  l'auteur est l'utilisateur connecté ; réponse de succès.
- Refus : type inconnu, plante absente, date invalide → erreur, rien n'est créé.
- Autre jardin : une plante ou un type de B → erreur, rien n'est créé.
- En base : une ligne au `jardin_id` de A, `faite_le` sans heure, `saisie_le`
  posé par le serveur.

**V94 — Renvoyer une saisie ne crée jamais de doublon** · base · RG-50, H5, S11, **critère 2**
La même demande envoyée deux fois (même identifiant) laisse une seule
intervention, et les deux réponses sont des succès identiques. Une demande
renvoyée avec le même identifiant et un contenu différent ne modifie pas la
ligne enregistrée.

**V95 — Un identifiant d'un autre jardin ne révèle rien** · API · H5, RG-21, critère 12
Une demande qui réutilise l'identifiant d'une intervention du jardin B est
refusée avec la même réponse qu'une plante introuvable ; rien n'est créé dans A,
rien n'est modifié dans B.

**V96 — Une intervention sur plusieurs plantes se décompose** · base · RG-04, P5 · écran B8
Une saisie sur 6 plantes, avec un identifiant client par plante, crée 6
interventions qui partagent un même `lot_saisie_id` ; renvoyée, elle en laisse
toujours 6.

**V97 — Une intervention n'est jamais refusée pour une raison métier** · API · H9, RG-05
Une intervention est acceptée sur une plante archivée entre-temps, sur une
échéance déjà soldée, et à une date passée de plusieurs mois. La date future
est traitée par V133.

**V98 — « Fait » depuis une échéance** · API · *flux critique* · P2, H11, décision D-57 · écran T1
- Nominal : « fait » sur une échéance désignée par `(regle_id, due_le)` et un
  identifiant client crée l'intervention du type de la règle sur sa plante,
  datée d'aujourd'hui, et solde **ce couple** : une échéance à venir est
  matérialisée `soldee`, une manquée passe de `manquee` à `soldee`.
- Refus : règle inconnue ou inactive, ou couple que le moteur ne rend pas pour
  cette règle → erreur, rien n'est créé.
- Autre jardin : règle de B → `404`, rien n'est créé.
- En base : une intervention ; une échéance `soldee` avec `intervention_id` ;
  renvoyée, la demande n'en crée pas d'autre.

**V99 — Une saisie rétroactive recalcule la suite** · base · **critère 7**, RG-05
Le lundi, une taille datée du samedi précédent solde l'échéance dont la fenêtre
contient ce samedi, et la prochaine échéance de la règle est recalculée à
partir du samedi.

**V100 — Deux interventions dans une même fenêtre** · base · H9, chap. 1 C1
La seconde intervention d'une fenêtre déjà soldée est enregistrée ; aucune
seconde échéance soldée n'est écrite ; l'historique de la plante montre les
deux interventions.

**V101 — La position du téléphone est gardée sans défaire le plan** · base · RG-15, RG-48, S18, **critère 17**, décision D-17
Une intervention sur une plante, saisie avec une position, garde cette position
et sa précision dans l'intervention. La géométrie de la plante n'est créée ou
remplacée que si elle est absente ou en source `gps` moins précise ; jamais si
elle est en source `plan` ou `manuel`. Une intervention sur plusieurs plantes ne
modifie aucune géométrie.

**V133 — Une intervention datée dans le futur** · API · RG-04, H9, Q-UX-4 · écrans T2–T4, B8
Une intervention datée d'aujourd'hui ou de demain (fuseau `Europe/Paris`) est
acceptée ; une date postérieure au lendemain est traitée selon la réponse à
Q-UX-4.

---

## Règles — V102 à V106

**V102 — Corriger une règle crée une version** · API · *flux critique* · RG-08, D10, P7 · écran B9
- Nominal : corriger la périodicité, une fenêtre ou un intervalle rend
  l'ancienne version inactive avec sa date de remplacement, crée la version
  suivante pour la même plante et le même type, et la réponse porte la
  prochaine échéance recalculée.
- Refus : voir V104.
- Autre jardin : voir V104.
- En base : deux lignes `regles` (v1 inactive, v2 active), les fenêtres de v1
  intactes, celles de v2 corrigées.

**V103 — Le passé reste explicable par la règle d'alors** · base · D10, CDC §9 point 3, RG-06
Une échéance matérialisée sous la version 1 garde son `regle_id` et son
explication après la correction.

**V104 — Une correction invalide ou étrangère ne change rien** · API · RG-21, §2.1
Une fenêtre impossible (31/02, optimale hors fenêtre, butoir à moitié) → erreur,
rien ne change ; un membre non propriétaire → `403` ; une règle de B → `404`, B
intacte.

**V105 — Sauter une occurrence** · API · *flux critique* · CDC §4.3–4.4 · écran B6
- Nominal : suspendre l'échéance `(regle_id, due_le)` avec un motif la
  matérialise `suspendue` ; la suivante est proposée.
- Refus : sans motif → erreur, rien n'est écrit.
- Autre jardin : règle de B → `404`.
- En base : une échéance `suspendue` avec son motif ; recommencer n'en écrit pas
  une seconde.

**V106 — Une plante libre reçoit ses propres règles** · API · CDC §3.9, RG-09, Q-UX-7 · écrans B6, B9
Pour une plante sans espèce, créer une règle R1 ou R2 de toutes pièces la rend
active et productrice d'une échéance ; la confiance qu'elle porte, et ce qu'en
dit l'explication, suivent la réponse à Q-UX-7 ; une seconde règle active du
même type est refusée.

---

## Positions et identification — V107 à V114

**V107 — Poser ou tracer une plante sur le plan** · API · *flux critique* · RG-14, RG-48, critère 17, décision D-59 · écrans B13, B6
- Nominal : un point, une ligne ou un polygone GeoJSON en WGS 84 enregistre la
  géométrie de la plante en source `plan` ; des coordonnées saisies l'enregistrent
  en source `manuel`.
- Refus : GeoJSON invalide (type inconnu, coordonnées hors bornes, polygone non
  fermé) → erreur, la géométrie précédente reste.
- Autre jardin : plante de B → `404`, sa géométrie intacte.
- En base : une seule géométrie par plante, `source = plan` ou `manuel`,
  `relevee_le`.

**V108 — La position la plus fiable gagne, à l'écriture** · base · RG-48, S18
Pour chaque couple (source en place, source reçue) parmi `plan`, `manuel`,
`gps`, la géométrie n'est remplacée que si la source reçue est au moins aussi
fiable ; un relevé `gps` moins fiable est conservé dans l'intervention, jamais
dans la géométrie.

**V109 — Lignes et polygones reçoivent leurs échéances** · base · **critère 18**, A4
Une haie tracée en ligne et une pelouse en polygone, avec leurs règles, ont
leurs échéances calculées et listées au tableau de bord comme une plante en
point.

**V110 — Douze pommiers posés un par un** · base · **critère 24**
Douze plantes de même espèce créées dans une zone, puis posées une par une,
portent chacune une géométrie de source `plan`, sans aucun relevé GPS préalable.
La création des douze en une ligne de lot est vérifiée par V85.

**V111 — Une plante créée sur le terrain est située, et les voisines viennent en tête** · API · **critère 9**, RG-15, Q8 · écrans T3, B7
Une plante créée avec la position du téléphone porte une géométrie point de
source `gps` avec sa précision, sans champ de plus. La liste des plantes proches
d'une position rend au plus 10 candidates triées par distance à leur position
affichée, puis les plantes de la zone courante, et accepte une recherche par nom
ou code court ; elle ne présélectionne jamais une plante seule ; aucune plante
de B n'y figure.

**V112 — Un code court ouvre la fiche, dans son jardin seulement** · API · **critère 22**, RG-21 · écrans T6, B6
L'adresse `/p/{code}` d'une plante du jardin courant redirige vers sa fiche,
quelle que soit la casse ; un code inexistant, ou un code qui n'existe que dans
B (même un `FRU-001` présent dans les deux jardins mène à la plante de A), rend
le même `404`, jamais la fiche de B.

**V113 — Les étiquettes d'une zone** · API · **critère 10** · écran B10
Les étiquettes d'une zone rendent toutes ses plantes actives avec code court,
nom, repère et le QR de leur adresse ; une zone de B → `404`.

**V114 — Le QR dit exactement l'adresse de la fiche** · API · critère 22
Le QR produit, relu par un décodeur en test, rend exactement l'adresse absolue
`/p/{code}` de la plante.

---

## Photos et fonds de plan — V115 à V121

**V115 — Joindre une photo** · API · *flux critique* · RG-35, RG-38, H10, **critère 14** · écrans T4, T5, B6
- Nominal : une image avec un identifiant client, rattachée à une plante ou à
  une intervention du jardin, est stockée sous
  `photos/{jardin}/{annee}/{empreinte}.{ext}` ; renvoyée, elle reste unique.
- Refus : type non image, poids ou dimensions hors limites → erreur, rien n'est
  stocké.
- Autre jardin : plante ou intervention de B → erreur, aucun fichier écrit.
- En base : une ligne `photos` avec `derivees_pretes = false`, empreinte et
  taille.

**V116 — Les dérivées se font hors de la requête** · base · A19, A20, A21
Une miniature 160 px et une image d'affichage 1 200 px au format WebP sont
produites par un job, qui porte le `jardin_id` ; elles existent sur le disque
sous un nom qui contient l'empreinte, et `derivees_pretes` passe à vrai. La
requête d'envoi n'attend pas le job.

**V117 — Le quota protège le disque** · API · CDC §8.4, §8.6, décision D-60
Un envoi qui ferait dépasser le quota du jardin est refusé avec un message, sans
rien stocker ; l'occupation compte les photos du jardin, et seulement elles (les
fonds de plan image s'y ajoutent avec V120).

**V118 — La photo ne bloque jamais le fait** · base · H10, RG-38
Une intervention enregistrée existe sans sa photo quand l'envoi de la photo
échoue ; aucun des deux enregistrements n'attend l'autre.

**V119 — Choisir la photo de couverture** · API · *flux critique* · RG-35, critère 14 · écrans B6, B5, T3
- Nominal : une photo de la plante (ou d'une de ses interventions) devient sa
  couverture ; les listes rendent l'adresse de sa miniature, ou de l'original
  tant que les dérivées ne sont pas prêtes.
- Refus : photo d'une autre plante → erreur.
- Autre jardin : photo ou plante de B → `404` ou erreur, rien ne change.
- En base : `plantes.photo_couverture_id`.

**V120 — Importer et caler un fond de plan** · API · *flux critique* · CDC §6 lot 4, RG-47, **critère 16** (versant serveur) · écran B13
- Nominal : une image avec deux ou trois points de calage (pixel, longitude,
  latitude) devient le fond actif du jardin, et le précédent cesse de l'être.
- Refus : un seul point, ou des coordonnées hors bornes → erreur, rien n'est
  stocké.
- Autre jardin : le fond de B reste actif dans B et invisible dans A.
- En base : un seul fond actif par jardin ; l'image compte dans le quota.

**V121 — Les photos orphelines se nettoient jardin par jardin** · base · chap. 3 §12, A11, décision D-60
La commande de nettoyage prend un jardin en argument ; sans `--supprimer`, elle
liste les fichiers de ce jardin référencés nulle part depuis plus de 7 jours et
ne supprime rien ; avec `--supprimer`, elle les supprime. Ceux des autres
jardins, et les fichiers récents, restent.

---

## Export et démonstration — V122 à V126, V130, V134

**V122 — Exporter tout le jardin, lisible sans l'outil** · API · *flux critique* · **critère 8**, CDC §8.1, RG-11, décision D-61 · écran B12
- Nominal : l'export rend une archive avec un `donnees.json` (toutes les
  entités du jardin, avec les noms lisibles des zones, espèces et types), un
  CSV par table (zones, plantes, géométries, règles en toutes versions,
  fenêtres, intervalles saisonniers, interventions, échéances, photos en
  métadonnées, types du jardin et types fournis utilisés, fonds de plan avec
  leurs images) aux en-têtes en français, et un `LISEZMOI.txt` qui décrit
  chaque fichier. Les fichiers de photos sont l'objet de V134.
- Refus : sans session → connexion.
- Autre jardin : voir V123.
- En base : relue, chaque ligne de chacune de ces tables se retrouve dans
  l'archive, colonne à colonne.

**V123 — L'export d'un jardin ne contient que lui** · API · RG-21, RG-28, critère 12
L'export de A ne contient aucun identifiant, nom ni fichier de B, même quand le
même utilisateur possède les deux jardins.

**V124 — L'export tient à dix ans d'historique** · base · CDC §8.7
À 500 plantes et 10 000 interventions, l'export se produit en flux, avec une
mémoire de pointe sous 128 Mo.

**V125 — Un jardin fictif de 500 plantes, en une commande** · base · C4, chap. 4 §5, chap. 6 §3, critère 23 (données)
`make jardin-demo` crée un jardin fictif de 500 plantes, avec des points, des
lignes et des polygones sur des coordonnées inventées, et deux ans d'historique ;
relancée, elle remplace ce seul jardin ; elle refuse de tourner en production.

**V126 — Les seuils tiennent sur le jardin de démonstration** · base · RG-19, O7
Sur le jardin de démonstration, le tableau de bord et la liste des plantes
tiennent les seuils de V79 et V89 ; la mesure est réécrite dans
`decisions-agents.md`.

**V130 — Un inventaire incomplet ne casse aucune lecture** · API · RG-27, critère 3, décision D-62 · écrans B4, B5, B6, B12
Pour un jardin vide, et pour une plante sans espèce, sans géométrie ou sans
règle, le tableau de bord, la liste des plantes, la fiche de la plante et
l'export répondent `200`.

**V134 — Les photos dans l'export** · API · **critère 8**, Q-UX-5 · écran B12
L'archive d'export contient les fichiers de photos du jardin sous la forme
choisie en réponse à Q-UX-5, produits en flux ; la mémoire de pointe reste
sous la borne de V124.

---

## Critères du CDC §10 et assertions

| Critère | Assertions de la partie A | Versant de la partie B |
|---|---|---|
| 1 — 150 plantes en moins d'une heure | V85, V86 | Coller depuis un tableur (B3 · 04, D-62) et chronométrage du parcours P0 (B3) |
| 2 — Saisie terrain en 15 s, renvoi sans doublon | V94, V96 | 3 gestes, état « non envoyé » (T1–T4) |
| 3 — Tableau de bord à toute date, avec explication | V50, V76, V130 | Affichage groupé (B4) |
| 4 — Haie jamais taillée du 16/03 au 15/08, cinq profils | V44, V60, V70 | — |
| 5 — Tonte à ~7 j en avril, ~10 j en octobre | V55 | — |
| 6 — Taille non soldée au 31 mars : manquée, suivante l'hiver d'après | V47, V73, V78 | Historique de la plante (B6) |
| 7 — Saisie rétroactive, recalcul | V99 | — |
| 8 — Export en un clic, compréhensible | V122, V123, V134 | Le clic (B12) |
| 9 — Position à la création, voisines en tête | V111 | Capture silencieuse du téléphone (T3) |
| 10 — Étiquettes d'une zone | V113 | Aperçu et impression (B10) |
| 11 — Utilisable à 500 plantes | V77, V79, V89, V126 | Recherche et filtres à l'écran (B4, B5, T3) |
| 12 — Aucune donnée d'un autre jardin | V33, V41, V74, V87, V95, V123 (et tout cas « autre jardin ») | — |
| 13 — Deux climats, 22 jours, décalage expliqué | V43, V50, V72 | — |
| 14 — Photo de couverture, photo jointe sur le terrain | V115, V119 | Sans quitter les 3 gestes (T4) |
| 15 — Tonte jamais proposée en janvier | V56 | — |
| 16 — Plan importé, calé par trois points, plantes à leur place | V120 (stockage du fond et du calage) | Calage affine et affichage (B13, Vitest) |
| 17 — Pommier déplacé en `plan`, pas déplacé par le GPS | V101, V107, V108 | Glisser-déposer (B13) |
| 18 — Haie en ligne, pelouse en polygone, avec échéances | V109 | Tracé (B13) |
| 19 — Carte terrain avec fond, position, plantes dues | — | Entièrement partie B (T7) |
| 20 — Service d'orthophotographie coupé | — | Entièrement partie B (Playwright, C14) |
| 21 — Ordre de tournée modifiable | V131 | Réordonner à l'écran et revenir à l'ordre proposé (T7, Vitest) |
| 22 — QR scanné, fiche ouverte, rien hors du jardin | V112, V114 | Lecture caméra (T6) |
| 23 — Carte fluide à 500 plantes | V125 (données) | Entièrement partie B pour la fluidité (B13) |
| 24 — 12 pommiers créés puis posés sur la carte | V85, V110 | Placement à la carte (B13) |

---

## Carte de couverture

Chaque assertion est couverte par au moins un sprint de `tasks.md`.

| Sprint | Assertions |
|---|---|
| 0a | V1, V2 |
| 0b | V3, V4, V5, V6 |
| 0c | V7 |
| 0d | V9, V10, V11, V12 |
| 0e | V13, V14, V15, V16, V17 |
| 0f | V18, V19, V20, V21 |
| 0g | V22, V127 |
| 0h | V8 |
| 1a | V23, V24, V25 |
| 1b | V27, V28, V29, V30, V129 |
| 2a | V32, V33, V34 |
| 2b | V35 |
| 2c | V36, V37, V38 |
| 2d | V39, V40 |
| 2e | V41, V128 |
| 2f | V135, V136 |
| 3a | V42, V43, V44, V45 |
| 3b | V46, V47, V48, V49, V50 |
| 3c | V51, V52, V53, V54 |
| 3d | V55, V56, V57, V58 |
| 3e | V59, V60, V61 |
| 4a | V26, V62, V63, V64 |
| 4b | V65 |
| 4c | V66, V68, V69 |
| 4d | V67 |
| 5a | V31, V71, V72, V74 |
| 5b | V70, V73, V75 |
| 6 | V76, V77, V78, V79, V80 |
| 7a | V81, V82, V83, V84 |
| 7b | V88, V132 |
| 7c | V89, V90, V91 |
| 7d | V85, V86, V87 |
| 7e | V92 |
| 8a | V93, V94, V95, V96, V97 |
| 8b | V98, V99, V100, V101 |
| 8c | V133 |
| 9a | V102, V103, V104, V105 |
| 9b | V106 |
| 10a | V107, V108, V109, V110, V111 |
| 10b | V112, V113, V114 |
| 10c | V131 |
| 11a | V115, V116, V117, V118 |
| 11b | V119, V120, V121 |
| 12a | V122, V123, V124, V130 |
| 12b | V134 |
| 13 | V125, V126 |

Total : 134 assertions, 44 sprints. Les sprints marqués `En attente` (7d, 7e,
8c, 9b, 12b) attendent une réponse de `questions-ux.md`. Aucune assertion sans sprint, aucun sprint
sans assertion.
