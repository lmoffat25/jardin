---
name: construire-sprint
description: >
  Construit le prochain sprint du plan du jardin (docs/plan/tasks.md) de bout en
  bout : worktree, tests d'abord, construction, make verifier, demande de fusion
  en fusion automatique, attente de la fusion, ligne de handoff. Avec
  --un-sprint, un seul sprint puis fin de passe : c'est ce que lance
  scripts/plan/enchainer.sh, une session neuve par sprint. Adapté du skill
  buildfeature de Treize Pourcent.
---

Tu construis **un sprint** du plan, sans surveillance : personne ne répond
pendant la passe. Entre deux passes, rien ne survit que le plan (`tasks.md` sur
`main`) et `docs/plan/handoffs.jsonl` du clone principal. Ce que tu as appris et
qui n'est pas dans ta ligne de handoff, ta demande de fusion ou
`decisions-agents.md` est perdu.

## 0. Prologue

1. Le **clone principal** est `racine=$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")`. Il reste sur `main` : tu n'y fais ni branche, ni commit, ni modification, sauf l'ajout à `docs/plan/handoffs.jsonl` (étape 7).
2. Lis `AGENTS.md`, `.claude/rules/*.md`, puis `docs/plan/README.md` §3 à §6.
3. Lis les trois dernières lignes de `$racine/docs/plan/handoffs.jsonl` : points faibles et décisions des passes précédentes.

## 1. Choisir le sprint

Le premier `## Sprint` de `docs/plan/tasks.md` qui a une case `- [ ]` et dont
l'en-tête (avant le premier `###`) ne porte pas `**En attente :**`. Lis tout son
en-tête : **Lecture**, **Contrat**, **Tests d'abord**, **Porte**, **Fin**, et ses
assertions V dans `validation-contract.md` : ce sont elles qui disent « fini ».

**Passe coupée.** Si la branche `sprint/<id>` existe déjà (locale, distante, ou
son worktree), la passe précédente a été coupée : reprends-la, ne la refais pas.
Lis son diff contre le contrat, relance les tests du sprint, et continue à
l'étape où elle s'est arrêtée. Si sa demande de fusion est déjà fusionnée, il ne
manque que l'étape 7.

## 2. Worktree

```bash
git -C "$racine" fetch -q origin
git -C "$racine" worktree add "$racine-arbres/<id>" -b sprint/<id> origin/main
cd "$racine-arbres/<id>" && make setup
```

`make setup` donne au worktree son propre projet DDEV (`jardin-<id>`, D-73) et
installe les crochets. Un seul worktree DDEV à la fois : le poste est un
portable.

## 3. Tests d'abord, puis construire

- Écris les tests des assertions du sprint, lance-les, et **relève le premier rouge réel**. S'il diffère de celui que prévoit le plan, dis-le dans la demande de fusion.
- Construis. Itère sur la classe de test que tu changes ; la suite entière, une fois à la fin.
- Un test qui passe du premier coup n'a rien prouvé : casse le code qu'il garde, vois-le rougir, remets le code.
- Chaque décision technique va dans `docs/plan/decisions-agents.md` (`D-NN` suivant), avec ce qui a été écarté. Coche les cases du sprint dans `tasks.md` dans la même demande de fusion.

**Question d'expérience utilisateur** (règle de Léon, 20/10/2026) : tu ne
t'arrêtes pas. Tranche par ta recommandation, inscris la question dans
`docs/plan/questions-ux.md` (forme du README §3, `Q-UX-n` suivant, avec « Appliquée :
la recommandation »), construis sur elle, et cite-la dans le bloc « En attente »
du bilan. Le marqueur `**En attente :**` d'un en-tête n'est posé que par Léon.

## 4. Vérifier, envoyer

1. `make verifier` ; sa dernière ligne va dans la demande de fusion (C5).
2. Commit : messages au format de `.claude/rules/git.md`, trailers `Agent:` et `Session:` compris. Jamais `--no-verify`.
3. Premier envoi : `git push -u origin sprint/<id>`, puis `gh pr create --draft` avec le modèle `.github/pull_request_template.md`, chaque titre rempli.
4. Quand tout est prêt : `gh pr ready`, puis `gh pr merge --auto --squash`.

## 5. Attendre la fusion

```bash
gh pr checks --watch --fail-fast
```

Rouge : lis le journal du job (`gh run view <id> --log-failed`), corrige,
renvoie, attends de nouveau. Trois échecs de la même nature : arrête-toi sans
handoff, sur un paragraphe `Bloqué :` qui dit ce que tu as essayé (la boucle
relance une fois, puis s'arrête). Vert : attends `gh pr view --json state` à
`MERGED` (la fusion automatique prend une à deux minutes). **Ne fusionne jamais
toi-même**, et ne termine jamais ton tour pendant qu'une tâche de fond tourne.

## 6. Ranger

```bash
cd "$racine-arbres/<id>" && ddev delete --omit-snapshot --yes
git -C "$racine" worktree remove "$racine-arbres/<id>" && git -C "$racine" branch -D sprint/<id>
```

## 7. La ligne de handoff

Une ligne JSON ajoutée à `$racine/docs/plan/handoffs.jsonl`, **seulement pour un
sprint fusionné** (la boucle compte ces lignes : une ligne pour un sprint non
fini le ferait passer pour fini) :

```json
{"sprint":"0g","date":"2026-10-21","pr":7,"commit":"<sha de la fusion>","assertions":{"V22":"pass"},"tests":["scripts/plan/tests/test-x.sh"],"premier_rouge":"script absent","decisions":["D-74"],"questions_ux":[],"commandes":[{"cmd":"make verifier","code":0}],"points_faibles":["…"]}
```

## 8. Le bilan, dernier message de la passe

La boucle en extrait ces blocs, titres exacts, en début de ligne :

```
Sprint <id> fusionné : PR #<n>, commit <sha>, CI verte.

**Décisions prises — à contester si fausses :**
- D-NN : …

**Points faibles :**
- …

**En attente :**
- Q-UX-n tranchée par la recommandation : …   (ou « rien »)

Suivant : sprint <id suivant>.
```

## 9. Les arrêts

Un paragraphe qui commence par `ARRET:` en début de ligne arrête la boucle. Il
est réservé aux **actes qui engagent Léon** (README §3) : dépôt public, secret
ou compte, dépense, production, et aux tâches du plan marquées **ARRÊT**. Il dit
ce que Léon doit faire, avec les commandes exactes. Aucun contournement.
Jamais d'`ARRET:` pour une question technique ou d'expérience utilisateur.

Avec `--un-sprint`, la passe s'arrête après l'étape 8 : un sprint, pas deux.
