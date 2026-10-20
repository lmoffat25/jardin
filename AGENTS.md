# Consignes des agents

**Le produit.** Un service web de gestion de jardin, vendu à des particuliers et
des professionnels. Il **trace** ce qui a été fait sur chaque végétal, et
**annonce** ce qui doit l'être, quand, et ce qu'il en coûte d'attendre.

## Où sont les documents

| Document | Ce qu'il fixe |
|---|---|
| `docs/cahier-des-charges-fonctionnel.md` | Le quoi : règles RG, critères d'acceptation (§10), décisions (§12), questions ouvertes à Léon (§11) |
| `docs/technique-01-*.md` à `docs/technique-06-*.md` | Le comment : hors-ligne, tests, architecture, schéma, déploiement, environnement et livraison |
| `docs/plan/README.md` | La méthode : quand s'arrêter, ce qu'est un sprint |
| `docs/plan/tasks.md`, `docs/plan/validation-contract.md` | Les sprints, et les assertions V qu'ils prouvent |
| `docs/plan/decisions-agents.md` | Les décisions techniques des agents, avec ce qui a été écarté |
| `docs/plan/questions-ux.md` | Les questions d'expérience utilisateur, tranchées par la recommandation |
| `docs/protocole-bibliotheque.md` | Comment sourcer une périodicité agronomique (B1–B4) |
| `.claude/rules/` | Le détail : `git.md`, `tests.md`, `documentation.md` |

## Les commandes

Toutes passent par `make` (chapitre 6 §4), sur le poste comme en CI.

| Cible | Rôle |
|---|---|
| `make setup` | Installation, base, crochets git — une fois par poste ou par worktree |
| `make moteur` | Les tests du moteur seuls, sous la seconde |
| `make types` | Types TypeScript générés depuis les classes PHP `#[TypeScript]` ; à relancer après en avoir modifié une |
| `make statique` | Types générés à jour, PHPStan, Pint, tsc, ESLint, Prettier, audits des dépendances, puis `make plan` |
| `make plan` | Taille de chaque sprint du plan (P5) : une ligne par sprint, `1` au-delà du budget |
| `make test` | Toute la suite Pest, et les tests des outils |
| `make balayage` | Invariants calendaires sur toute l'année, et tests de volume à 500 plantes |
| `make e2e` | Parcours Playwright |
| `make mutation` | Mutation du moteur |
| `make criteres` | Critères du CDC sans test ; `LIVRAISON=1` le rend bloquant |
| `make jardin-demo` | Le jardin fictif de 500 plantes et deux ans d'historique, remplacé s'il existe ; jamais en production |
| `make sources` | Les sources de la bibliothèque interrogées, à la main ; jamais en CI |
| `make verifier` | Tout, dans l'ordre : **c'est la définition de « terminé »** (C5) |

## Les règles de travail

1. **Un chantier = une branche = une demande de fusion**, ouverte en brouillon dès le premier envoi.
2. **Un worktree par session**, jamais deux sessions dans le même répertoire.
3. **Les tests des critères d'abord**, le code ensuite.
4. **Jamais d'envoi direct sur `main`, jamais de déploiement en production.**
5. **Un attendu de test modifié se signale** dans la demande de fusion (RG-49).
6. **Les identifiants sont stables** : RG, critères, décisions. Une nouvelle règle prend le numéro suivant.
7. **Le comportement et sa documentation changent dans la même demande de fusion.**
8. **Une agronomie qui manque se source, elle ne s'invente pas** (B1–B4). Sans source, l'espèce reste hors de la liste de départ, et c'est écrit.
9. **Chaque commit porte les trailers `Agent:` et `Session:`** (`.claude/rules/git.md`).

## Quand s'arrêter

L'agent travaille sans interruption (`docs/plan/README.md` §3). Une décision
technique se prend et s'écrit dans `decisions-agents.md`. Une question
d'expérience utilisateur se tranche par la recommandation, s'inscrit dans
`questions-ux.md`, et le travail continue.

**L'agent s'arrête seulement devant un acte qui engage Léon** : rendre un dépôt
public, poser un secret ou créer un compte, dépenser de l'argent, déployer en
production. Il n'existe aucun contournement. La fusion dans `main` est
automatique sur CI verte ; la production ne part que sur le geste de Léon
(C22), et aucun agent ne connaît ni ne cherche la phrase qui la déclenche.
