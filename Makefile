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

.PHONY: setup moteur statique test balayage e2e mutation criteres verifier

setup:
ifeq ($(X),ddev exec)
	ddev start
endif
	$(X) composer install --no-interaction --prefer-dist
	$(X) sh -c '[ -f .env ] || cp .env.example .env'
	$(X) php artisan key:generate --no-interaction

moteur:
	$(call echec,moteur)
	@echo "(vide)"

statique:
	$(call echec,statique)
	@echo "(vide)"

test:
	$(call echec,test)
	$(X) vendor/bin/pest

balayage:
	$(call echec,balayage)
	@echo "(vide)"

e2e:
	$(call echec,e2e)
	@echo "(vide)"

mutation:
	$(call echec,mutation)
	@echo "(vide)"

criteres:
	$(call echec,criteres)
	@echo "(vide)"

# Dans cet ordre ; la première cible qui échoue arrête l'enchaînement (C5, V5).
verifier:
	$(MAKE) statique
	$(MAKE) test
	$(MAKE) balayage
	$(MAKE) e2e
	$(MAKE) mutation
	$(MAKE) criteres
	@echo "verifier : les six cibles sont passées"
