# La commande unique (chapitre 6 §4). La CI appelle ces cibles et rien d'autre (C6).
# Sur le poste, chaque commande passe par DDEV ; dans DDEV ou dans la CI
# (DANS_CI=1), elle est lancée directement.
ifneq ($(or $(filter 1,$(DANS_CI)),$(filter true,$(IS_DDEV_PROJECT))),)
  X :=
else
  X := ddev exec
endif

# Échec forcé d'une cible, pour le test de l'enchaînement (V5) : ECHEC=<cible>.
echec = @if [ "$(ECHEC)" = "$(1)" ]; then echo "échec forcé : $(1)"; exit 1; fi

.PHONY: setup installer crochets formater moteur statique test balayage e2e mutation criteres verifier

setup:
ifeq ($(X),ddev exec)
	ddev start
endif
	$(MAKE) installer
	$(X) php artisan migrate --force --no-interaction
	$(MAKE) crochets

# Dépendances et .env, sans base : ce dont les jobs statique et mutation ont besoin.
installer:
	$(X) composer install --no-interaction --prefer-dist
	$(X) npm ci --no-audit --no-fund
	$(X) sh -c '[ -f .env ] || cp .env.example .env'
	$(X) php artisan key:generate --no-interaction

# Crochets git copiés là où git les lit, worktrees compris : `git rev-parse
# --git-common-dir`, jamais un test `-d .git` (dans un worktree, .git est un fichier, C17).
crochets:
	@d="$$(git rev-parse --git-common-dir)/hooks"; mkdir -p "$$d" \
	  && cp scripts/hooks/pre-commit scripts/hooks/pre-push "$$d/" && chmod +x "$$d/pre-commit" "$$d/pre-push" \
	  && echo "crochets installés dans $$d"

# Pint et Prettier sur une liste de fichiers (FICHIERS=…), pour le crochet pre-commit.
formater:
	$(if $(filter %.php,$(FICHIERS)),$(X) vendor/bin/pint $(filter %.php,$(FICHIERS)))
	$(if $(filter %.ts %.tsx %.css,$(FICHIERS)),$(X) npx prettier --write $(filter %.ts %.tsx %.css,$(FICHIERS)))

moteur:
	$(call echec,moteur)
	@echo "(vide)"

statique:
	$(call echec,statique)
	$(X) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(X) vendor/bin/pint --test
	$(X) npm run -s typecheck
	$(X) npm run -s lint
	$(X) npm run -s format:check
	$(X) composer audit --locked --no-interaction
	$(X) npm audit --audit-level=high

test:
	$(call echec,test)
	$(X) vendor/bin/pest
	$(X) bash scripts/tests/test-statique.sh
	$(X) bash scripts/hooks/tests/test-crochets.sh

balayage:
	$(call echec,balayage)
	@echo "(vide)"

e2e:
	$(call echec,e2e)
	@echo "(vide)"

# Le moteur seul (C9) : les tests de tests/Moteur déclarent par mutates() la classe
# qu'ils gardent. Pas de seuil avant le premier moteur complet (C10, sprint 3e).
mutation:
	$(call echec,mutation)
	$(X) vendor/bin/pest tests/Moteur --mutate --no-cache

# Informatif pendant un jalon, bloquant à sa livraison : LIVRAISON=1 (C12).
criteres:
	$(call echec,criteres)
	@LIVRAISON=$(LIVRAISON) scripts/criteres.sh

# Dans cet ordre ; la première cible qui échoue arrête l'enchaînement (C5, V5).
verifier:
	$(MAKE) statique
	$(MAKE) test
	$(MAKE) balayage
	$(MAKE) e2e
	$(MAKE) mutation
	$(MAKE) criteres
	@echo "verifier : les six cibles sont passées"
