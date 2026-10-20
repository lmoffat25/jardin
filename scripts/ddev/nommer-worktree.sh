#!/usr/bin/env bash
# Donne à un worktree son propre projet DDEV (D-73). Deux copies du dépôt sous le
# même nom `jardin`, DDEV refuse : « a project … already exists for jardin that
# was created at ~/jardin ». Dans un worktree, ce script écrit
# .ddev/config.worktree.yaml (ignoré par git) avec `name: jardin-<dossier>` ;
# dans le clone principal, il ne fait rien. Appelé par `make setup`.
set -eu
racine="$(git rev-parse --show-toplevel)"
dir_git="$(git rev-parse --path-format=absolute --git-dir)"
commun="$(git rev-parse --path-format=absolute --git-common-dir)"
fichier="$racine/.ddev/config.worktree.yaml"
if [ "$dir_git" = "$commun" ]; then
  echo "nommer-worktree : clone principal, projet DDEV « jardin »"
  exit 0
fi
nom="jardin-$(basename "$racine" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9-\n' '-')"
if [ -f "$fichier" ]; then
  echo "nommer-worktree : $fichier existe déjà, laissé tel quel"
  exit 0
fi
printf '# Écrit par scripts/ddev/nommer-worktree.sh : ce worktree a son propre projet DDEV.\nname: %s\n' "$nom" > "$fichier"
echo "nommer-worktree : projet DDEV « $nom » pour ce worktree"
