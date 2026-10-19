# Chapitre 7 — Plan de réalisation : la méthode

Version 0.1 — 19/10/2026
Applique le CDC v0.13 et les chapitres techniques 1 à 6. Décisions **P1 à P8**.
Points ouverts **O27 et O28**.

La méthode reprend celle de Treize Pourcent (skills `featureplan`, phase 3, et
`buildfeature`, avec le script `buildfeature-run.sh`), **adaptée** : ici, le code
part de zéro, la vérification passe par la CI GitHub du chapitre 6 et non par
flix, et WordPress n'existe pas. Ce qui en est gardé, c'est ce qui y a fait ses
preuves : des sprints taillés pour un seul agent, un contrat de validation écrit
avant le code, une revue des coûts, un audit du plan par un lecteur neuf, et un
agent neuf par sprint.

---

## 1. Les fichiers du plan

| Fichier | Contenu | Qui l'écrit |
|---|---|---|
| `tasks.md` | Les sprints, dans l'ordre, avec leurs tâches à cocher. **En cas d'écart avec les chapitres techniques, ce fichier l'emporte** | Le plan, puis les agents (cases cochées) |
| `validation-contract.md` | Les assertions de comportement, fixées avant le code, et la carte sprint → assertions | Le plan |
| `decisions-agents.md` | Chaque décision technique prise par un agent en cours de route : date, sprint, décision, ce qui a été écarté et pourquoi | Les agents |
| `questions-ux.md` | Les questions qui attendent Léon (§3) | Les agents ; Léon y répond |
| `handoffs.jsonl`, `run.log` | L'état de l'enchaînement : une ligne par sprint terminé, et le journal des passes | Le script d'enchaînement |

Le plan est versionné dans le dépôt, dans `docs/plan/`. Chez Treize Pourcent il
vit dans `temp/`, que git ignore. Ici, le plan fait partie du projet.

## 2. Deux parties

| Partie | Contenu | Quand l'écrire |
|---|---|---|
| **A — Socle** | Chantier 0, schéma, cloisonnement, moteur d'échéances, bibliothèque, actions métier et leurs tests de routes | **Maintenant** : rien n'y dépend des écrans |
| **B — Interface** | Composants, pages, parcours, carte | Après la validation des wireframes de chaque vague (`conception-ecrans.md`) |

> **P1** — La partie A se construit pendant que les wireframes se conçoivent.
> La partie B ne s'écrit qu'une fois une vague validée : des tâches d'interface
> écrites avant les écrans seraient à refaire.

## 3. Quand l'agent s'arrête, et quand il ne s'arrête pas

**C'est la règle centrale de cette méthode** (décision de Léon, 19/10/2026) :
l'agent travaille sans interruption. Il n'interrompt Léon que pour ce qui lui
appartient réellement : **l'expérience utilisateur**, et les quelques actes qui
engagent Léon à l'extérieur. Jamais pour une décision technique.

| Situation | L'agent… |
|---|---|
| Choix technique : bibliothèque, structure, nommage, algorithme dans le cadre des chapitres | **décide**, et l'écrit dans `decisions-agents.md` avec ce qu'il a écarté |
| Test rouge, CI rouge, dépendance qui casse | **corrige** |
| Ambiguïté technique dans un chapitre | **tranche** dans le sens du chapitre, et l'écrit |
| Agronomie absente de la bibliothèque | **la source** selon le protocole de bibliothèque (B1–B4) ; sans source vérifiable, l'espèce reste hors de la liste de départ, et c'est écrit. Jamais inventée |
| Attendu de test technique à modifier (RG-49) | le **signale** dans la demande de fusion, et continue |
| **Question d'expérience utilisateur** que ni le CDC, ni une maquette validée, ni la charte ne tranchent ; **ou** attendu d'un critère d'acceptation qui changerait ce que l'utilisateur voit ou fait | **pose la question** dans `questions-ux.md`, met le sprint de côté et **passe au suivant qui n'en dépend pas** |
| **Acte qui engage Léon** : créer le dépôt public, poser un secret ou créer un compte, dépenser de l'argent, déployer en production | **s'arrête**. Il n'existe aucun contournement |

> **P2** — L'agent ne s'arrête que pour une question d'expérience utilisateur
> ou un acte qui engage Léon. Tout le reste se décide, s'écrit et continue.

> **P3** — Une question d'expérience utilisateur **n'arrête pas l'enchaînement**
> tant qu'un autre sprint peut avancer sans elle. L'enchaînement ne s'arrête
> que lorsque tous les sprints restants attendent une réponse.

**Forme d'une question** dans `questions-ux.md` : une question, l'écran ou le
parcours concerné, deux ou trois options avec la recommandation de l'agent en
premier, et ce qui attend la réponse. Léon répond dans le fichier ; la passe
suivante reprend.

**Pour que Léon puisse relire après coup sans être interrompu** :
`decisions-agents.md` et les demandes de fusion (modèle du chapitre 6, §10)
disent ce qui a été choisi et ce qui a été écarté. Léon relit quand il veut, et
peut revenir sur une décision par une demande de correction, qui devient un
sprint.

> **P4** — La fusion est **automatique quand la CI est verte** (C24 révisée,
> chapitre 6). La production reste déclenchée par Léon seul (C22) : c'est ce
> qui rend la fusion automatique acceptable, rien n'atteint un utilisateur sans
> son geste.

## 4. Un sprint

### Taille

> **P5** — Un sprint tient dans **5 assertions, 8 fichiers de code modifiés et 4
> classes de test** au plus. Ce budget vient de Treize Pourcent, où il a été
> mesuré : un sprint de 6 assertions, 5 classes de test et 6 consommateurs a
> occupé un agent deux heures pour finir 3 de ses 10 tâches. Un sprint qui
> dépasse se coupe (`3` → `3a`, `3b`).

### En-tête

```
## Sprint N — <titre>

**Objectif :** une phrase.
**Constructeur :** back | front | principal   (+ pourquoi)
**Risque :** complet | léger
  léger = types, déclarations, documentation, tests seuls, inventaire.
  Tout le reste est complet ; dans le doute, complet.
**Lecture :** seulement ce que le constructeur ne charge pas déjà
  (sections précises des chapitres, du CDC, de ce plan).
**Contrat :** signatures, routes et codes de réponse, formes des données,
  fichiers concernés. En code quand ça lève une ambiguïté.
**Tests d'abord :** les tests de comportement écrits avant le code, et le
  premier rouge attendu (« route absente », « table inexistante »).
**Preuve :** pour chaque fichier qui porte un risque : `mutation` (moteur,
  `pest --mutate`) ou `rouge` (le rouge observé suffit).
**Itérer sur / lancer une fois :** le test sur lequel on boucle, et la suite
  lancée une fois avant de rendre.
**Porte :** `make verifier`, puis la CI de la demande de fusion.
**Vérification en marche :** si le sprint change ce qu'un utilisateur voit,
  le parcours Playwright concerné. Sinon sans objet.
**Question UX possible :** aucune | <laquelle, et ce qui en dépend>
**En attente :** Q-UX-n   (seulement si le sprint attend une réponse ; le
  sprint ne contient alors que le travail qui en dépend, décision D-31)
**Assertions :** les numéros du contrat de validation.
**Fichiers :** la liste complète des fichiers de code créés, modifiés ou
  supprimés, en chemins complets ; leur nombre est celui du budget P5
  (décision D-29).
**Classes de test :** la liste complète des fichiers de test créés, modifiés ou
  supprimés (`tests/**/*Test.php`, `scripts/**/tests/test-*.sh`).
**Fin :** ce qui est vrai et prouvé quand le sprint est fini.
```

Sous l'en-tête, des groupes de tâches numérotés (`### N.1`, `### N.2`) et des
cases à cocher qui nomment le fichier, la méthode, les champs, le contrôle de
jardin, la persistance et le comportement en échec. Une tâche dont la logique
n'est pas triviale porte un extrait **Cible** : la signature et le cœur dont
l'ordre compte, pas tout le fichier.

**Pas de porte d'approbation avant la V1** : il n'existe pas encore de données
de production. Le jour où il en existe, un sprint qui touche le schéma ou les
données persistées s'arrête pour approbation (la porte `production-migration`
de Treize Pourcent).

## 5. Avant de construire

> **P6** — Le contrat de validation est écrit **avant** les tâches de code, et
> les tests sont jugés contre lui, pas contre le code. Les critères du CDC §10
> en sont la base ; chacun y devient une ou plusieurs assertions.

> **P7** — Avant la construction, deux passes :
> - **une revue des coûts** : les sprints qui bouclent sur une suite lente,
>   reposent sur un comportement jamais observé, ou demandent un instrument qui
>   n'existe pas, sont coupés, précédés d'un essai, ou acceptés en le disant ;
> - **un audit par un agent neuf**, qui lit le plan entier et cherche les
>   assertions qu'aucun sprint ne couvre, les sprints qui n'en couvrent
>   aucune, et les valeurs écrites qu'aucun code ne lit.

## 6. L'enchaînement

> **P8** — Un agent neuf par sprint. Un script lance une session sans
> surveillance par sprint, jusqu'à la fin du plan ou un arrêt du §3 ; l'état
> entre deux passes est celui du plan (cases cochées, `handoffs.jsonl`). Le
> script de Treize Pourcent (`scripts/plan/buildfeature-run.sh`) ne cite rien
> de propre à ce projet : il est repris au chantier 0, avec ses tests.

Le chantier 0 crée le dépôt public : c'est un acte qui engage Léon (§3), donc
le seul arrêt prévu d'avance. Tout ce qui précède se fait dans `~/jardin`, sans
rien publier.

## 7. Décisions

| # | Décision | Enjeu |
|---|---|---|
| P1 | Partie A maintenant, partie B après chaque vague de wireframes validée | Pas de tâches d'interface à refaire |
| P2 | L'agent ne s'arrête que pour l'expérience utilisateur ou un acte qui engage Léon | Décision de Léon : travail sans interruption |
| P3 | Une question UX met un sprint de côté, pas l'enchaînement | Léon répond quand il peut |
| P4 | Fusion automatique sur CI verte, production par Léon seul | Pas de relecture bloquante, aucun utilisateur atteint sans son geste |
| P5 | 5 assertions, 8 fichiers, 4 classes de test par sprint | Budget mesuré à Treize Pourcent |
| P6 | Contrat de validation avant le code | Les tests jugent l'intention, pas le code |
| P7 | Revue des coûts et audit par un agent neuf avant construction | L'auteur ne voit pas ses propres trous |
| P8 | Un agent neuf par sprint, enchaînés par script | Contexte petit pour chaque agent |

## 8. Points ouverts

| # | Question | Quand |
|---|---|---|
| O27 | Comment Léon est prévenu d'une question en attente ou d'un arrêt : notification sur le téléphone, mail (`envoyer-mail`), ou les deux ? | Posé en Q-UX-1 (non bloquant : sans réponse, le script ne prévient pas) |
| O28 | ~~Adapter `check-sprint-size.sh` de Treize Pourcent au format de ce plan, ou compter à la main pour la partie A ?~~ | **Tranché** par D-29 : listes de fichiers dans chaque en-tête, recomptées par `check-sprint-size.sh` au sprint 0g |
