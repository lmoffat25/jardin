# Document technique — Chapitre 2 : stratégie de test

Version 0.2 — 24/08/2026
Suite de `technique-01-hors-ligne.md` (v0.2). Décisions **TS-1 à TS-9**.

> **Hors-ligne suspendu pour la V1 — 19/10/2026** (CDC v0.13). Les tests qui
> exercent la file d'attente, le paquet terrain, le service worker et le
> format 426 attendent la reprise du chapitre 1. Le test n°10 (« saisir hors
> ligne, revenir en ligne ») est remplacé en V1 par le critère 2 : envoi
> coupé par Playwright (`context.setOffline(true)` au moment de valider),
> saisie toujours affichée comme non envoyée, renvoi, **une seule**
> intervention en base. Le test n°3 (idempotence, H5) reste.

**Ce chapitre vient avant l'architecture applicative, délibérément.** Savoir
comment on testera le moteur de règles change la façon dont on l'écrit : un
moteur couplé à la base de données ne se teste qu'en montant une base, et
personne n'écrit deux cents tests qui prennent chacun une seconde.

> **Complément du 19/10/2026.** Le produit est écrit par des agents IA (CDC
> §1.5). Le chapitre 6, §6, ajoute à cette stratégie ce que ce mode de
> réalisation impose : tests de mutation du moteur, balayages calendaires, un
> test par critère d'acceptation, tests unitaires du front, et tests de la
> carte (V1). Rien de ce chapitre-ci n'est abrogé.

> **Note sur les exemples de code.** Le projet n'existe pas encore : les
> extraits ci-dessous sont des **esquisses destinées à montrer la forme**, pas
> du code exécuté. Ils sont écrits en Pest, syntaxe Laravel 12.

---

## 1. Le principe : ton intuition est juste, elle mérite d'être précisée

> « Il faut faire un test qui teste l'objectif, pas le code. »

C'est exactement le bon principe, et il porte un nom dans la littérature :
**tester le comportement, pas l'implémentation**. Sa formulation la plus utile
vient de Ian Cooper :

> Le déclencheur d'un nouveau test, c'est **un nouveau comportement** — pas la
> création d'une nouvelle méthode, ni d'une nouvelle classe.

La différence, sur un cas de ce projet :

```php
// ❌ Teste le code : une méthode, son nom, sa signature
it('appelle calculerProchaineDate avec le bon intervalle', function () {
    $moteur = Mockery::mock(Moteur::class);
    $moteur->shouldReceive('calculerProchaineDate')->once()->with(7);
    // ...
});

// ✅ Teste l'objectif : ce que l'utilisateur observe
it('propose la tonte 7 jours après la dernière, au printemps', function () {
    // ...
    expect($echeance->dueLe)->toEqual('2027-04-22');
});
```

Le premier casse dès qu'on renomme la méthode ou qu'on change la façon de
calculer — alors que **le comportement n'a pas bougé**. Le second continue de
passer tant que la tonte tombe au bon jour, quelle que soit la façon dont c'est
écrit à l'intérieur. C'est ce qui permet de refactorer sans peur : c'est
d'ailleurs à ça que servent les tests, bien plus qu'à attraper des bugs.

Le corollaire pratique, formulé par Testing Library :

> *The more your tests resemble the way your software is used, the more
> confidence they can give you.*
> Plus vos tests ressemblent à la façon dont le logiciel est utilisé, plus ils
> inspirent confiance.

> **TS-1** — Un test décrit **un comportement observable**, et son nom est la
> phrase qui le décrit. S'il faut lire le corps du test pour comprendre ce
> qu'il vérifie, le nom est mauvais.

### La limite de ce principe

Poussé à l'extrême, « tester l'objectif » mènerait à ne faire que des tests de
bout en bout dans un navigateur. Ce serait une erreur symétrique : ces tests
sont **lents** (des secondes chacun), **fragiles** (un bouton déplacé les
casse) et surtout ils ne disent pas *où* est le problème — juste que quelque
chose ne va pas.

D'où le dosage de la section suivante. « Tester l'objectif » ne veut pas dire
« tester au niveau le plus haut » : ça veut dire **tester au niveau le plus bas
où le comportement est encore observable et compréhensible**.

---

## 2. Le dosage : quatre niveaux, des proportions très inégales

La pyramide des tests, dans la version de Ham Vocke : *beaucoup* de tests
petits et rapides, *quelques* tests plus grossiers, *très peu* de tests de bout
en bout. L'anti-modèle qu'elle combat est le « cornet de glace » : une majorité
de tests de haut niveau, lents et instables.

Appliquée à ce projet :

| Niveau | Cible | Base de données | Durée | Volume attendu |
|---|---|---|---|---|
| **N1 — Moteur** | Le calcul des échéances, en PHP pur | non | < 1 ms | **~150 tests** |
| **N2 — Persistance** | Cloisonnement, contraintes, idempotence | oui | ~50 ms | ~30 tests |
| **N3 — Routes** | Réponses HTTP, autorisations, API terrain | oui | ~100 ms | ~30 tests |
| **N4 — Navigateur** | Parcours terrain hors ligne | oui | ~5 s | **6 à 8 tests** |

> **TS-2** — Le **niveau N1 porte l'essentiel de la couverture**, parce que
> l'essentiel de la difficulté du produit y est concentrée : fenêtres à cheval
> sur l'année, décalage climatique, intervalles saisonniers, échéances
> manquées. Ce sont des calculs purs, testables en millisecondes.

C'est la raison d'être de la séparation moteur / ORM (D3, D7) : elle n'est pas
de la propreté architecturale gratuite, c'est **ce qui rend 150 tests
supportables**. À 50 ms chacun, la suite prendrait 8 secondes et personne ne la
lancerait à chaque sauvegarde ; à 1 ms, elle prend 150 ms.

---

## 3. Ce qu'on met dans chaque niveau

### N1 — Le moteur de règles

Le moteur prend **des données en entrée, rend des échéances en sortie**. Aucune
base, aucun framework, aucun réseau.

```php
it('ouvre la fenêtre de taille du pommier le 1er décembre', function () {
    $regle = Regle::fenetre(
        debut: '01/12', fin: '31/03',
        optimalDebut: '01/02', optimalFin: '15/03',
    );

    $echeance = Moteur::prochaine(
        regle: $regle,
        historique: [],
        aujourdhui: '2026-12-01',
        decalageClimatique: 0,
    );

    expect($echeance->etat)->toBe(Etat::Ouverte);
});
```

Ce qui se teste ici, et **uniquement ici** :

| Comportement | Pourquoi c'est délicat |
|---|---|
| Fenêtre à cheval sur le 31 décembre | Le piège n°1 du produit (§5.2 du modèle) |
| États successifs d'une échéance | `à venir` → `ouverte` → `optimale` → `urgente` → `manquée` |
| Intervalle saisonnier | 7 j au printemps, 0 en hiver |
| Décalage climatique | Appliqué aux bornes de calendrier — fenêtres **et** bornes de saison — jamais à la valeur d'un intervalle (RG-25) |
| **Butoirs de fenêtre** | Le décalage ne franchit jamais un butoir, dans les deux sens (RG-44). C'est le test qui manquait : la fenêtre de haie débordait sur avril en montagne et sur juillet dans le Midi |
| **Hors-saison R1** | Une tonte de novembre ne reste pas due tout l'hiver : elle est reportée à l'ouverture de la saison (RG-40, critère 15) |
| **États sans trou ni chevauchement** | Chaque jour d'une fenêtre tombe dans exactement un état — y compris entre la fin de l'optimale et le début de l'urgence |
| Périodicité pluriannuelle | Une année sur trois |
| Fenêtres multiples | Un traitement en deux passages (RG-36) |
| Échéance manquée | Fenêtre close sans intervention (RG-07) |
| Année bissextile | 29 février dans une fenêtre |

### N2 — La persistance

Ce qui ne peut pas se tester sans base :

```php
it('ne rend jamais les plantes d\'un autre jardin', function () {
    $mien = Jardin::factory()->create();
    $autre = Jardin::factory()->has(Plante::factory()->count(3))->create();

    actingAsProprietaire($mien);

    expect(Plante::all())->toHaveCount(0);   // le scope global s'applique
});
```

| Comportement | Décision couverte |
|---|---|
| Aucune donnée d'un autre jardin n'est visible | **D1 — le plus important du projet** |
| Le scope s'applique même en requête directe | D1 |
| Rejouer deux fois la même mutation ne crée qu'une ligne | **H5 — idempotence** |
| Un code court n'est jamais réattribué après archivage | RG-13 |
| Une plante archivée garde son historique | RG-02 |
| Corriger une règle crée une version et n'écrase pas la précédente | **D10** — l'unicité ne porte que sur les règles actives |
| Une règle `verifiee` sans `source_url` est refusée par la base | S9, B1 |

### N3 — Les routes

| Comportement | Pourquoi |
|---|---|
| L'API terrain rend le paquet du bon jardin | D1 + H3 |
| Une session expirée rend 401, pas 500 | H7 — la file doit survivre proprement |
| Un payload invalide rend 422 avec un message exploitable | H6 |
| Un format de mutation périmé rend **426**, pas 422 | E8 — les confondre fait perdre des saisies légitimes |
| Une intervention sur une échéance déjà soldée est **acceptée** | H9, C1 |

### N4 — Le navigateur

Réservé à ce qui ne s'observe qu'en vrai. Six à huit tests, pas plus.

| Parcours | Pourquoi il faut un navigateur |
|---|---|
| Saisir hors ligne, revenir en ligne, vérifier | Service worker, IndexedDB, file d'attente |
| Rejeu après session expirée | Le cycle complet de H7 |
| Photo mise en file, intervention enregistrée sans elle | H10 |
| P1 en trois gestes | Le critère d'acceptation n°2, chronométré |

> **TS-3** — Un test de niveau N4 ne doit **jamais** être écrit pour vérifier
> une règle métier. Si un comportement peut être vérifié en N1, il l'est en N1.
> Le navigateur ne sert qu'à ce qui est *intrinsèquement* du navigateur.

---

## 4. Le piège n°1 de ce projet : le temps

Un test qui dit « la taille du pommier est due » dépend de la date du jour. S'il
n'est pas figé, **il passe en janvier et échoue en juin** — et il échouera un
matin où tu n'auras rien changé, ce qui est la meilleure façon de perdre
confiance dans une suite de tests.

> **TS-4** — **Aucun test ne lit l'heure réelle.** Le moteur reçoit la date du
> jour en paramètre (jamais `now()` en interne), et les tests des niveaux
> supérieurs figent l'horloge.

C'est aussi pourquoi le moteur prend `aujourdhui` en argument dans l'exemple du
§3 : ce n'est pas une coquetterie, c'est ce qui le rend testable.

Aux niveaux N2 à N4, Laravel fournit ce qu'il faut :

```php
$this->travelTo('2027-02-14');   // fixe « maintenant »
$this->travel(14)->days();       // avance de 14 jours
$this->freezeTime(function () { /* ... */ });
```

Attention : `time()` — la fonction native de PHP — **ignore** ces mécanismes.
Toujours passer par `now()` ou `Carbon::now()`.

### Le corollaire : des dates fixes, jamais relatives

```php
// ❌ Le test change de sens tous les jours
$plante->plantee_le = now()->subYears(3);

// ✅ La date est un fait du scénario
$this->travelTo('2027-02-14');
$plante->plantee_le = '2024-03-01';
```

---

## 5. Anatomie d'un test

Trois temps, toujours dans cet ordre. Les noms varient (*Arrange / Act /
Assert*, *Given / When / Then*), la structure est la même.

```php
it('marque comme manquée une taille non faite au 1er avril', function () {
    // 1. LE CONTEXTE — ce qui est vrai avant
    $regle = Regle::fenetre(debut: '01/12', fin: '31/03');
    $historique = [];

    // 2. L'ACTION — une seule, celle qu'on teste
    $echeance = Moteur::prochaine($regle, $historique, aujourdhui: '2027-04-01');

    // 3. LE CONSTAT — ce qu'on observe, du point de vue de l'utilisateur
    expect($echeance->etat)->toBe(Etat::Manquee);
    expect($echeance->suivante->fenetreDebut)->toEqual('2027-12-01');
});
```

> **TS-5** — **Une action par test.** Si le test contient deux appels qui
> changent l'état, il vérifie deux comportements et il faut le couper en deux.
> Un test qui échoue doit désigner un seul coupable.

### Le nommage

Le nom d'un test est une **phrase de comportement**, lisible par quelqu'un qui
ne connaît pas le code :

```
✅ 'décale la fenêtre de taille de 15 jours pour un jardin du Midi'
✅ 'refuse la seconde tentative d'une mutation déjà enregistrée'
✅ 'ne rend jamais les plantes d\'un autre jardin'

❌ 'test moteur 3'
❌ 'testCalculerProchaineDate'
❌ 'ça marche'
```

Bon repère : la liste des noms de tests, lue seule, doit se lire comme la
**spécification du produit**. Si ce n'est pas le cas, ce sont des tests de code
et pas de comportement.

---

## 6. Par où commencer, concrètement

Les **15 critères d'acceptation du §10 du CDC sont déjà une liste de tests**.
Ils ont été écrits comme des assertions vérifiables, c'est exactement leur
usage.

Ordre proposé, du plus rentable au moins :

| # | Test | Niveau | Pourquoi celui-là d'abord |
|---|---|---|---|
| 1 | La fenêtre 01/12 → 31/03 est ouverte le 15 janvier | N1 | Le piège le plus courant, sur le cas le plus fréquent |
| 2 | La même fenêtre est fermée le 15 juin | N1 | Le symétrique — c'est là que la comparaison naïve casse |
| 3 | Rejouer deux fois une mutation ne crée qu'une intervention | N2 | **H5** — le doublon invisible |
| 4 | Aucune donnée d'un autre jardin n'est visible | N2 | **D1** — la faute irrattrapable |
| 5 | La tonte est à 7 jours en avril, 10 en octobre, jamais en janvier | N1 | Couvre l'intervalle saisonnier en un test |
| 6 | Deux profils climatiques donnent des fenêtres décalées de 22 jours | N1 | RG-25, RG-26 |
| 7 | Une taille non faite au 31 mars devient manquée | N1 | RG-07 — l'information la plus utile du produit |
| 8 | Aucune fenêtre de haie ne recouvre le 1ᵉʳ avril, **pour les cinq profils** | N1 | RG-44 — le bug le plus coûteux trouvé à la relecture : deux règles justes, fausses ensemble |
| 9 | La tonte de novembre n'est plus due en janvier | N1 | RG-40, critère 15 — une échéance qui reste en retard tout l'hiver décrédibilise la liste du jour |
| 10 | Saisir hors ligne, revenir en ligne, l'intervention est en base | N4 | Le parcours qui justifie tout le chapitre 1 |

> **TS-6** — Écrire ces dix tests **avant** d'écrire le moteur. Pas par
> dogmatisme TDD : parce qu'ils décrivent le comportement attendu, et qu'écrire
> le moteur en les regardant évite de coder un mécanisme qui « paraît juste »
> mais tombe à côté d'une fenêtre à cheval sur l'année.

---

## 7. Ce qu'il ne faut pas tester

Autant de temps gagné, et autant de tests qui ne casseront pas à chaque
refactorisation.

| À ne pas tester | Pourquoi |
|---|---|
| Les accesseurs, les setters, les constructeurs triviaux | Aucun comportement |
| Le framework | Laravel sait persister un modèle. Ce n'est pas ton travail de le vérifier |
| Les bibliothèques externes | Idem |
| Le détail interne d'une méthode privée | Elle change ; le comportement observable, non |
| Le HTML exact d'un écran | Fragile, sans valeur. Tester ce que l'écran *permet*, pas comment il est écrit |

> **TS-7** — **Les mocks sont un signal d'alerte.** Un test qui a besoin de
> simuler trois objets pour fonctionner teste de la structure, pas du
> comportement. Dans ce projet, le moteur n'a besoin d'aucun mock : il prend des
> données et rend des données. Si tu te retrouves à mocker pour tester le
> moteur, c'est que le moteur s'est couplé à quelque chose — et c'est le
> moteur qu'il faut corriger, pas le test.

---

## 8. Outils

| Besoin | Outil | Note |
|---|---|---|
| Tests PHP | **Pest** | Syntaxe plus lisible que PHPUnit, qu'il utilise en dessous. Les deux sont fournis avec Laravel |
| Données de test | **Factories** Laravel | `Plante::factory()->pommier()->create()` — des états nommés qui rendent les tests lisibles |
| Base de test | `RefreshDatabase` | Base repartie de zéro entre les tests. Avec PostgreSQL, préférer les transactions |
| Contrôle du temps | `travelTo()`, `freezeTime()` | Voir §4 |
| Tests navigateur | **Playwright** | Contrôle le mode hors ligne du navigateur (`context.setOffline(true)`), ce qui est indispensable pour N4 |

Sur ce dernier point : Laravel Dusk existe et s'intègre mieux, mais **Playwright
sait simuler la perte de réseau**, ce que le chapitre 1 rend obligatoire à
tester. C'est le critère qui tranche.

> **TS-8** — La suite N1 doit tourner en **moins d'une seconde**, et être
> lançable seule. Si elle dépasse, elle ne sera plus lancée à chaque
> modification, et elle perd l'essentiel de son intérêt.

> **TS-9** — La couverture n'est **pas un objectif**. Un pourcentage élevé
> obtenu en testant des accesseurs ne dit rien. Le bon indicateur : *est-ce que
> les dix comportements du §6 sont vérifiés ?*

---

## 9. Ressources

**Le principe — tester le comportement, pas l'implémentation**

- [Distillation de « TDD, Where Did It All Go Wrong » d'Ian Cooper](https://herbertograca.com/2018/08/27/distillation-of-tdd-where-did-it-all-go-wrong/) — résumé écrit de la conférence NDC Oslo 2013, la référence sur le sujet. À lire en premier, c'est court.
- [Compte rendu de la même conférence](https://robdmoore.id.au/blog/2015/01/26/review-of-ian-cooper-tdd-where-did-it-all-go-wrong) — un autre angle sur les mêmes idées.
- [Guiding Principles — Testing Library](https://testing-library.com/docs/guiding-principles) — une page, une phrase à retenir.

**Le dosage**

- [The Practical Test Pyramid — Ham Vocke, Thoughtworks](https://martinfowler.com/articles/practical-test-pyramid.html) — long mais complet : quoi mettre à chaque niveau et pourquoi.
- [Mocks Aren't Stubs — Martin Fowler](https://martinfowler.com/articles/mocksArentStubs.html) — utile le jour où la question des mocks se posera vraiment.

**Laravel, concrètement**

- [Testing: Getting Started — Laravel 12](https://laravel.com/docs/12.x/testing) — le point de départ.
- [Mocking — Laravel 12](https://laravel.com/docs/12.x/mocking) — contient la section sur le contrôle du temps (`travelTo`, `freezeTime`).
- [Pest PHP](https://pestphp.com/) — la documentation du framework de test.
- [spatie/pest-plugin-test-time](https://github.com/spatie/pest-plugin-test-time) — une surcouche à `Carbon::setTestNow()` si le contrôle du temps devient central.

---

## 10. Décisions de ce chapitre

| # | Décision |
|---|---|
| TS-1 | Un test décrit un comportement observable ; son nom est cette phrase |
| TS-2 | N1 (moteur, sans base) porte l'essentiel de la couverture |
| TS-3 | Aucun test navigateur pour une règle métier |
| TS-4 | Aucun test ne lit l'heure réelle ; le moteur reçoit la date en paramètre |
| TS-5 | Une action par test |
| TS-6 | Les dix tests du §6 sont écrits avant le moteur |
| TS-7 | Un besoin de mock pour tester le moteur signale un couplage à corriger |
| TS-8 | La suite N1 tourne en moins d'une seconde |
| TS-9 | La couverture n'est pas un objectif |
