# Git

- Une branche par chantier, nommée `sprint/<id>` pour un sprint du plan, `correctif/<sujet>` sinon.
- La demande de fusion s'ouvre en brouillon (`gh pr create --draft`) dès le premier envoi : c'est ce qui signale aux autres sessions qu'un endroit est pris.
- Quand le travail est fini et `make verifier` passe : `gh pr ready`, puis `gh pr merge --auto --squash`. La fusion se fait seule sur CI verte (C24 révisée) ; on ne fusionne jamais une CI rouge, et on ne contourne jamais le ruleset de `main`.
- Un worktree par session (`git worktree add`), avec son propre `make setup` : les crochets s'installent par `git rev-parse --git-common-dir` (C17).
- Jamais de `git push --force` sur une branche partagée, jamais d'envoi sur `main`.
- Les crochets (`pre-commit` : formatage ; `pre-push` : `make statique` puis `make test`) ne se sautent pas : pas de `--no-verify`.
- Chaque message de commit finit par ses trailers, un par ligne, après une ligne vide :

  ```
  Agent: <modèle, par exemple claude-opus-5-5>
  Session: <identifiant de la session de l'agent>
  ```
