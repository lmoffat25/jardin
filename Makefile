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

.PHONY: setup installer crochets formater moteur types statique plan test balayage e2e mutation criteres verifier

setup:
ifeq ($(X),ddev exec)
	bash scripts/ddev/nommer-worktree.sh
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
	$(X) vendor/bin/pest tests/Moteur

# Types TypeScript générés depuis les classes PHP #[TypeScript] (V8, D-77). Les tests
# désignent d'autres sources et sorties : TYPES_SOURCES, TYPES_SORTIE, TYPES.
TYPES ?= resources/js/types/generes.d.ts
TYPES_SOURCES ?= app src
types:
	$(X) bash scripts/types/types.sh ecrire $(or $(TYPES_SORTIE),$(TYPES)) $(TYPES_SOURCES)

statique:
	$(call echec,statique)
	$(X) bash scripts/types/types.sh comparer $(TYPES) $(TYPES_SOURCES)
	$(X) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(X) vendor/bin/pint --test
	$(X) npm run -s typecheck
	$(X) npm run -s lint
	$(X) npm run -s format:check
	$(X) composer audit --locked --no-interaction
	$(X) npm audit --audit-level=high
	@# make plan, sans sous-make (make -n verifier ne doit lister que ses six cibles, V5) ;
	@# en silence quand le plan est juste, ses erreurs seules sinon.
	@$(X) bash scripts/plan/check-sprint-size.sh docs/plan > /dev/null \
	  || { $(X) bash scripts/plan/check-sprint-size.sh docs/plan | grep -v ' : [0-9]* assertions'; exit 1; }

# Taille de chaque sprint du plan (P5, D-29) : une ligne par sprint, 1 au-delà du budget.
plan:
	$(X) bash scripts/plan/check-sprint-size.sh docs/plan

test:
	$(call echec,test)
	$(X) vendor/bin/pest
	$(X) bash scripts/tests/test-statique.sh
	$(X) bash scripts/hooks/tests/test-crochets.sh
	$(X) bash scripts/plan/tests/test-enchainer.sh
	$(X) bash scripts/plan/tests/test-check-sprint-size.sh
	$(X) bash scripts/plan/tests/test-porter-reponses.sh
	$(X) bash scripts/ddev/tests/test-nommer-worktree.sh

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
