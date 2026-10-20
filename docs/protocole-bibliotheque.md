# Protocole de remplissage de la bibliothèque

Version 0.2 — 24/08/2026
Compagnon de `cahier-des-charges-fonctionnel.md` (§5) — RG-24, RG-30, RG-31,
RG-37, RG-44.

La bibliothèque est le **chemin critique du produit** : sans règles justes,
l'outil calcule des échéances fausses et ne vaut rien. Ce document dit comment
la remplir, y compris avec une IA, sans transformer des suppositions en
certitudes.

---

## 1. Le risque, précisément

| | Erreur de code | Erreur de périodicité |
|---|---|---|
| Se voit | à l'exécution | **l'année suivante, ou jamais** |
| Diagnostiquée par | un test qui échoue | personne — l'arbre produit moins, c'est tout |
| Coût | du temps | parfois **un arbre** (cerisier taillé en hiver : gommose, chancre) |

C'est ce délai qui rend le remplissage dangereux, pas sa difficulté. Une IA
— ou un humain pressé — produira une valeur inventée avec exactement la même
assurance qu'une valeur vérifiée.

**Deux garde-fous existent déjà dans le CDC** et il faut s'en servir :

- **RG-31** — toute règle porte `verifiee` ou `indicative`, et l'indicative est
  signalée à l'utilisateur au moment où il l'applique.
- **RG-24** — la bibliothèque est versionnée : une erreur est **corrigible**, et
  la correction est proposée aux jardins concernés sans écraser leurs réglages.

L'erreur n'est donc pas fatale. Ce qui serait fatal, c'est de ne pas savoir
**lesquelles** des 600 lignes sont sûres.

---

## 2. Les règles de remplissage

> **B1 — Sourcer au moment où l'on écrit, jamais après.** Une règle sans
> `source_url` au moment de sa création ne sera jamais vérifiée : relire
> 200 espèces coûte plus cher que de les chercher. La base le **refuse** : une
> règle `verifiee` sans `source_url` viole une contrainte `CHECK` (S9), elle ne
> s'enregistre pas. Ce n'était pas le cas jusqu'au 24/08/2026 — la colonne était
> nullable et seul le test B-T8 y veillait.

> **B2 — `verifiee` exige deux sources indépendantes concordantes.** Une seule
> source, ou deux sources qui se recopient, donnent `indicative`. Le doute est
> la valeur par défaut.

> **B3 — Hiérarchie des sources.** Dans l'ordre : instituts techniques et
> chambres d'agriculture, jardins botaniques et conservatoires, ouvrages de
> référence, sites de vulgarisation établis. **Jamais** : sites marchands,
> blogs à contenu généré, contenus sans auteur ni date.

> **B4 — L'IA cherche, elle ne se souvient pas.** Toute valeur doit venir d'une
> page consultée pendant la session, avec son URL. Une valeur produite « de
> mémoire » est une invention plausible — le mode d'erreur le plus difficile à
> repérer, parce qu'elle est toujours vraisemblable.

> **B5 — Quinze espèces vérifiées valent mieux que deux cents indicatives.**
> Commencer par la liste du §5.1 du CDC — qui distingue depuis le 24/08/2026 ce
> qui est **sourcé et prêt** de ce qui **reste à sourcer**. Une espèce absente ne
> bloque personne : l'utilisateur crée ses propres règles (RG-09). Une espèce
> annoncée sans règle, si : elle fait échouer B-T10.

> **B6 — Le descriptif est libre.** Nom latin, famille, exposition, rusticité,
> hauteur, floraison : RG-30 autorise le remplissage massif, ces colonnes
> n'entrent dans aucun calcul. **Seul l'opérationnel est contraint** — fenêtres,
> périodicités, cadences.

---

## 3. Les tests de cohérence

C'est l'apport le plus utile de ce protocole : **la bibliothèque se teste comme
du code**. Ces tests ne prouvent pas qu'une valeur est juste — ils attrapent
les valeurs manifestement fausses, qui sont l'essentiel des erreurs de
génération.

Ils tournent au niveau N2 (§2 du chapitre 2), sur le jeu de données de
référence.

### Cohérence agronomique

| # | Test | Ce qu'il attrape |
|---|---|---|
| B-T1 | Aucun fruitier à **noyau** n'a de fenêtre de taille recouvrant décembre → février | La confusion pépins/noyaux, l'erreur la plus coûteuse |
| B-T2 | Toute espèce de type **haie** a une fenêtre excluant le 16 mars → 15 août | RG-37, nidification |
| B-T3 | Un arbuste à floraison **printanière** n'est pas taillé avant sa floraison | Suppression de la floraison de l'année |

### Cohérence structurelle

| # | Test | Ce qu'il attrape |
|---|---|---|
| B-T4 | Toute fenêtre dure entre **14 jours et 5 mois** — sauf celle qui porte un **butoir d'interdiction**, tolérée jusqu'à **9 mois** | Une fenêtre de 11 mois = une valeur non renseignée déguisée. L'exception couvre une fenêtre de haie qui irait d'un butoir à l'autre, `16/08 → 15/03` = 7 mois (critère 4 du CDC) |
| B-T5 | Tout intervalle de tonte **non nul** est entre **5 et 21 jours** | Un `90` saisi pour `9`. Le `0` est une valeur légitime : il marque le hors-saison (RG-40), il n'est pas une saisie manquante |
| B-T6 | La période optimale est **incluse** dans sa fenêtre | Incohérence de saisie |
| B-T7 | Une espèce n'a **pas deux règles** du même type d'intervention pour une même variante de conduite, et une règle de variante ne double pas une règle commune du même type (RG-51) | Doublon d'import |
| B-T8 | Toute règle `verifiee` a une `source_url` **non nulle** | B1, vérifié par la machine |
| B-T9 | Toute règle a une `confiance` explicite | S9 |

### Cohérence de couverture

| # | Test | Ce qu'il attrape |
|---|---|---|
| B-T10 | Toute espèce de la liste de départ a **au moins une règle** | Une espèce ajoutée au catalogue et oubliée côté règles |
| B-T11 | Toute règle référence un type d'intervention **applicable** à l'espèce | Une taille sur une pelouse |

### Cohérence après décalage climatique

C'est la famille ajoutée le 24/08/2026, et la seule qui teste la bibliothèque
**telle que l'utilisateur la recevra** — pas telle qu'elle est écrite. Les trois
tests ci-dessus vérifient le climat de référence ; ceux-ci vérifient les cinq.

| # | Test | Ce qu'il attrape |
|---|---|---|
| B-T12 | Toute espèce de type **haie** porte un butoir de début au 16/08 et un butoir de fin au 15/03 | Une fenêtre correcte mais non bornée — RG-44 |
| B-T13 | Pour **chacun des cinq profils climatiques**, la fenêtre projetée puis rognée dure ≥ 14 jours et contient toujours sa période optimale | Un butoir qui rogne trop, ou une fenêtre dont l'optimale sort du butoir |
| B-T14 | Pour chacun des cinq profils, aucune fenêtre de haie projetée ne recouvre le 16/03 → 15/08 | **Le bug qui a motivé RG-44** : à +21 j la fenêtre débordait sur fin mars–avril, à −15 j sur début août |

> **B8 — Un test qui ne tourne que sur le climat de référence ne prouve rien.**
> C'était le défaut de la première version de ce protocole : B-T1 et B-T2
> lisaient la valeur écrite en bibliothèque, alors que le moteur en sert une
> autre à quatre jardins sur cinq. Toute règle opérationnelle se teste sur les
> cinq profils.

> **B7** — Ces tests tournent **avant chaque publication d'une version de la
> bibliothèque** (RG-24). Une version qui échoue n'est pas publiée. C'est ce qui
> rend le remplissage assisté acceptable : la machine attrape ce que la
> relecture humaine laisse passer après la trentième espèce.

---

## 4. Le processus, espèce par espèce

```
1. CHERCHER      2 sources indépendantes, selon B3
2. EXTRAIRE      fenêtre, période optimale, périodicité, intervalle
3. COMPARER      concordantes ?  → verifiee
                 divergentes ou source unique ?  → indicative
4. ÉCRIRE        avec source_url et source_libelle, toujours
5. TESTER        B-T1 à B-T14 sur le lot complet, sur les cinq profils (B8)
6. RELIRE        un œil humain sur les seules règles verifiee
```

L'étape 6 est courte **parce que** l'étape 3 a fait le tri : on ne relit que ce
qui prétend être sûr.

---

## 5. Ce qui reste hors de portée

Trois limites à assumer, plutôt qu'à découvrir :

- **Le microclimat.** Le décalage climatique par jardin (RG-25) est une
  approximation globale. Un versant nord ou un fond de vallée gélif décale
  encore, et aucune bibliothèque ne le saura. La correction manuelle par plante
  (RG-09) reste le dernier mot. Ce que les butoirs (RG-44) garantissent, ce n'est
  pas la justesse du décalage — c'est qu'il ne franchisse jamais un interdit.
- **La variété.** La bibliothèque descend à l'espèce, pas à la variété. Deux
  pommiers peuvent différer de plusieurs semaines. C'est un choix assumé du
  modèle (`variete` est un texte libre).
- **L'année en cours.** Une saison froide décale tout. Seule la météo réelle
  (lot 5) pourra le corriger.

Ces limites justifient d'autant plus RG-08 : **la correction d'une règle par
l'utilisateur est un cas nominal**, pas un aveu d'échec de la bibliothèque.

---

## 6. Décisions

| # | Décision |
|---|---|
| B1 | Sourcer au moment d'écrire, jamais après |
| B2 | `verifiee` exige deux sources indépendantes concordantes |
| B3 | Hiérarchie des sources ; ni sites marchands, ni contenus sans auteur |
| B4 | L'IA cherche et cite ; elle ne produit jamais de mémoire |
| B5 | Quinze espèces vérifiées valent mieux que deux cents indicatives |
| B6 | Le descriptif est libre, seul l'opérationnel est contraint |
| B7 | Une version de bibliothèque qui échoue aux tests n'est pas publiée |
| B8 | Toute règle opérationnelle se teste sur les **cinq profils climatiques**, pas sur le seul climat de référence |
