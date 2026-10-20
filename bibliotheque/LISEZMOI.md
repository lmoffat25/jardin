# Bibliothèque

Les espèces et les règles de référence que l'outil propose (CDC §5), en YAML,
un fichier par catégorie (D-19). **Aucune valeur ne s'écrit ici sans source** :
le protocole est `docs/protocole-bibliotheque.md` (B1 à B8).

- `<categorie>.yaml` : les espèces de la catégorie et leurs règles ; le schéma
  est dans `docs/plan/tasks.md`, sprint 4a, et dans `docs/plan/decisions-agents.md` (D-93).
- `sources-exclues.yaml` : les domaines refusés comme sources (B3, D-52).
- `retraits.yaml` (4c) : les espèces et règles écartées faute de source, avec leur raison.
- `climat/` : le référentiel des communes (D-86), lu par `jardin:importer-communes`.

`php artisan bibliotheque:charger` lit le dossier, refuse tout fichier qui
échoue aux contrôles de structure (B-T4 à B-T9, B-T11, B2, B3), et charge le
reste en base, versionné : recharger sans changement ne modifie rien, une
règle corrigée reçoit une version suivante, et aucun jardin n'est touché
(RG-24).
