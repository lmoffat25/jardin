# Cahier des charges fonctionnel — Service de gestion de jardin

Version 0.14 — 20/10/2026 — Léon Moffat
Statut : proposition de conception, à valider avant travaux techniques.

**Changement de la 0.13.** L'annexe A est sourcée (`docs/plan/sourcing-annexe-a.md`).
La période de nidification passe à 16/03 → 15/08 (elle datait de 2015), avec les
butoirs, le critère 4 et les tests qui en dépendent. Cinq questions sont
ouvertes au §11 (Q19 à Q23).

**Changement de la 0.12.** Le produit est réalisé par des agents IA, sous le
pilotage de Léon, et non plus développé à la main (§1.5). Les reports motivés
par le seul coût de développement ont été repassés un par un (§12). Le plan du
terrain entre dans un jalon **V1** (§6). Le projet porte désormais sa propre
conception de l'environnement de code, des tests et de la livraison continue
(§8.8, chapitre technique 6).

**Changement de la 0.13.** Le hors-ligne est **suspendu pour la V1** (RG-01,
remplacée en V1 par RG-50) : c'était la principale source de complexité du
projet. Le produit est conçu pour être **commercialisé**, et la mesure de la
complexité est posée en §1.5 : ni surcomplication, ni simplification de façade.
La conception des écrans a son propre document (`conception-ecrans.md`).

**Comment lire ce document.** Il est long parce qu'il porte les raisons, pas
seulement les décisions. Pour en prendre l'état rapidement :

1. **§12 — Journal des décisions** : tout ce qui est tranché et pourquoi, en une
   page. Si vous ne lisez qu'une section, c'est celle-là.
2. **§3 — Concepts métier** : le vocabulaire, qui est normatif.
3. **§4.1 et §4.2 — Le moteur** : pourquoi un intervalle fixe ne suffit pas.
4. **§6 — Lotissement** : ce qui entre dans le MVP et ce qui attend.

Le reste — parcours, exigences transverses, modèle conceptuel, annexes — se lit
au moment de l'implémenter. Les règles de gestion `RG-nn` portent un
identifiant stable, jamais renuméroté ni réattribué.

---

## 1. Contexte et problème à résoudre

### 1.1 Le besoin

Un terrain d'agrément comporte des végétaux nombreux et hétérogènes (arbres fruitiers,
arbres d'ornement, arbustes et haies, planches de potager, pelouse, petits
fruits). Chacun demande des interventions récurrentes dont la périodicité
n'est ni intuitive, ni uniforme, ni mémorisable de tête à cette échelle.

Deux pertes concrètes aujourd'hui :

1. **Perte de mémoire.** Rien ne dit quand tel arbre a été taillé pour la
   dernière fois, ni si le traitement d'automne a été passé sur tous les
   fruitiers ou seulement sur ceux du haut du terrain.
2. **Perte de fenêtre.** Beaucoup d'interventions ne se rattrapent pas : une
   taille de pommier non faite avant le débourrement est reportée d'un an ; un
   traitement à la chute des feuilles passé trois semaines trop tard n'a plus
   d'effet.

L'outil doit donc faire deux choses, et bien :

- **Tracer** ce qui a été fait, sur quoi, quand, avec quoi.
- **Annoncer** ce qui doit être fait, quand, et dire ce qu'il en coûte de ne
  pas le faire maintenant.

### 1.2 Un produit, pas un outil personnel

Le besoin n'est pas propre à un terrain : il vaut pour un jardin de ville de
30 plantes comme pour un grand terrain qui en compte plusieurs centaines.
**L'outil est donc conçu comme un service multi-jardins**, utilisable par des
personnes qui ne se connaissent pas, chacune sur son propre terrain.

Cette décision, prise le 23/08/2026, a trois conséquences structurantes qui
irriguent tout le reste du document :

1. **Le jardin est la racine de toutes les données** (§3.1). Chaque plante,
   zone, intervention et règle appartient à un jardin et à un seul.
   L'isolation entre jardins est une exigence de sécurité, pas une commodité.
2. **Les règles ne peuvent plus être universelles** (§5.4). Un même geste se
   décale de 3 semaines entre la Bretagne et le Midi, de 4 semaines en
   montagne. Une bibliothèque de référence qui ignore la région donne des
   échéances fausses à la majorité de ses utilisateurs.
3. **L'utilisateur arrive avec un terrain déjà planté**, jamais avec une page
   blanche. La mise en route — saisir 40, 150 ou 400 plantes — est le moment
   où l'outil se gagne ou se perd (§7, P0).

**Le terrain de Léon est le jardin pilote** : cas de référence pour le
dimensionnement (§8.7) et premier utilisateur réel. Il n'est pas le seul cas
que le modèle doit servir.

### 1.3 Ce que l'outil n'est pas

Hors périmètre, explicitement, pour éviter la dérive :

- Ce n'est pas un outil de conseil agronomique généraliste ni un moteur de
  diagnostic de maladies.
- Ce n'est pas un outil de gestion d'exploitation agricole (pas de parcellaire
  PAC, pas de comptabilité, pas de main-d'œuvre).
- Ce n'est pas une application communautaire ou sociale : pas de fil
  d'actualité, pas de partage public, pas d'échange entre jardiniers. Le
  multi-jardins signifie *plusieurs utilisateurs isolés*, pas *une communauté*.
- Ce n'est pas un outil de gestion d'espaces verts professionnels (pas de
  devis, pas de planning d'équipes, pas de facturation de prestations).
- Ce n'est pas une encyclopédie botanique : la bibliothèque de référence peut
  être riche (§5.3), mais elle sert la saisie et le calcul des échéances, pas
  la consultation pour elle-même. Aucun écran n'est consacré à la lecture du
  catalogue.

### 1.4 Nature du produit

**Application web**, accessible au navigateur depuis l'ordinateur et depuis le
téléphone, sans installation depuis un magasin d'applications. Le contexte
terrain (§2.2) demande qu'elle s'ajoute à l'écran d'accueil du téléphone, ce
que les technologies web permettent sans application native. Le fonctionnement
hors ligne est suspendu pour la V1 (RG-01, §8.2).

Contrainte d'hébergement, actée : le service doit tourner sur un **serveur
Linux ordinaire**, sans service managé propriétaire ni base de données
commerciale. Ce n'est pas un choix de stack — il n'est pas encore fait — mais
une contrainte qui écarte d'emblée les fonctions supposant une infrastructure
lourde ou une dépendance externe facturée. Elle est reprise en §8.6.

### 1.5 Mode de réalisation

Le produit est **écrit par des agents IA**. Léon le pilote : il fixe le
périmètre, valide les décisions et relit ce qui est livré. Décision du
19/10/2026. Elle remplace l'hypothèse d'origine, qui était un développement à
la main par une seule personne.

Ce changement déplace le coût du projet. Il ne le supprime pas :

- **Écrire du code ne coûte presque plus rien.** Une fonction n'est plus
  reportée parce qu'elle serait longue ou difficile à programmer. Les reports
  qui n'avaient que cette raison ont été levés (§12).
- **Vérifier devient le poste principal.** Un agent produit vite un code qui a
  l'air juste. Sans tests qui échouent quand le comportement est faux, rien ne
  distingue ce code d'un code correct. Chaque fonction ajoutée au périmètre
  ajoute donc des tests à écrire et des comportements à relire.
- **Ce qui reste coûteux, c'est la complexité.** Un code plus compliqué que
  son besoin coûte à chaque vérification, à chaque évolution et à chaque agent
  qui doit le comprendre avant d'y toucher.
- **Les raisons qui n'étaient pas techniques restent entières.** Un agent ne
  source pas une périodicité agronomique à la place d'une source, ne demande pas
  moins de saisie à l'utilisateur, et ne supprime pas une dépendance externe.

> **RG-46** — Une fonction n'entre dans un jalon que si son comportement est
> **vérifiable automatiquement** : critère d'acceptation (§10) traduit en test,
> ou contrôle exécuté en intégration continue. Une fonction qu'on ne sait
> vérifier qu'à l'œil attend que ce test soit écrit. C'est la contrepartie d'un
> code qu'on n'écrit plus soi-même.

**La mesure de la complexité.** Le produit est destiné à être **commercialisé** :
il se conçoit comme un logiciel professionnel, pas comme un outil personnel.
Un mécanisme entre dans la conception s'il répond à l'une de ces deux
questions :

1. **Sert-il un besoin du produit maintenant**, ou dans un lot déjà prévu au §6 ?
2. **Serait-il coûteux ou impossible à ajouter plus tard ?** Le cloisonnement
   entre jardins, le versionnement des règles, les identifiants, les tests et
   la chaîne de livraison sont dans ce cas : ils se posent dès le premier jour.

Ce qui ne répond à aucune des deux attend son besoin, même si c'est une bonne
pratique ailleurs. Et ce qui répond à l'une des deux reste, même si c'est
long : simplifier un mécanisme nécessaire ne rend pas le produit plus simple,
seulement plus fragile.

---

## 2. Utilisateurs et contextes d'usage

### 2.1 Profils

| Profil | Qui | Ce qu'il fait |
|---|---|---|
| **Propriétaire** | Le titulaire du compte | Crée son jardin, son inventaire, ses règles ; invite des membres ; seul à pouvoir supprimer le jardin |
| **Membre** | Un compte invité sur le jardin d'un autre (lot 1 bis) | Consulte les échéances et enregistre des interventions ; ne modifie ni les règles ni l'inventaire |
| **Curateur** | L'éditeur du service | Maintient la bibliothèque de référence (§5.2), commune à tous les jardins. N'est pas un rôle de jardin : il n'accède à aucune donnée d'utilisateur |

Le terme **membre** est le seul employé pour un compte invité : c'est celui que
portent l'adhésion et son rôle (RG-20).

### 2.2 Contextes d'usage

Deux contextes d'usage très différents, qui portent des exigences opposées et
ne doivent pas être traités par les mêmes écrans.

#### Contexte « bureau » — piloter

Depuis l'ordinateur, au calme. Sessions longues, peu fréquentes.

- Créer et corriger l'inventaire des zones et des plantes.
- Régler les règles de récurrence, ajuster les fenêtres.
- Consulter l'historique, comparer les saisons, préparer un week-end de
  travaux.
- Saisir en lot ce qui a été fait et non enregistré sur le moment.

Exigences : densité d'information élevée, tableaux, filtres, édition en masse,
clavier.

#### Contexte « terrain » — enregistrer

Depuis le téléphone, dehors, gants aux mains, parfois hors couverture réseau
au fond du terrain. Sessions très courtes, très fréquentes.

- « Je viens de tailler le pommier du fond » → 3 gestes maximum.
- Prendre une photo et l'attacher à la plante.
- Voir la liste du jour : quoi faire, où, dans quel ordre géographique.
- Noter une observation libre (« cochenilles sur le prunier »).

Exigences : cible tactile large, saisie par défaut intelligente (date = maintenant,
plante = la dernière consultée ou la plus proche), **aucune saisie perdue quand
le réseau manque** (RG-50), aucun formulaire à plus de 4 champs obligatoires.

> **RG-01** — Toute action réalisable sur le terrain doit être réalisable hors
> ligne et se synchroniser au retour du réseau, sans intervention de
> l'utilisateur et sans perte.
>
> *Suspendue pour la V1 le 19/10/2026 (§12).* La conception qui la met en
> œuvre (chapitre technique 1) est conservée pour une reprise après la V1, si
> l'usage montre que la protection de RG-50 ne suffit pas.

> **RG-50** — En V1, une saisie terrain qui ne part pas faute de réseau **n'est
> jamais perdue en silence** : elle reste affichée comme non envoyée, se renvoie
> d'un geste, et son renvoi ne crée jamais de doublon. L'utilisateur sait
> toujours si ce qu'il vient de saisir est enregistré.

---

## 3. Concepts métier

Le vocabulaire ci-dessous est normatif : il est repris tel quel dans le code,
l'interface et les échanges.

Les **règles de gestion** (`RG-nn`) portent un identifiant stable, indépendant
de leur ordre d'apparition : un numéro n'est jamais réattribué ni renuméroté,
pour que les échanges et les tickets puissent s'y référer durablement.

### 3.1 Jardin

Le **jardin** est la racine de toutes les données : un terrain, un lieu, un
climat, un propriétaire. Tout ce que décrit ce document — zones, plantes,
interventions, règles, échéances, photos — appartient à un jardin et à un
seul.

Un jardin porte :

| Champ | Statut | Rôle |
|---|---|---|
| Nom | obligatoire | « La maison », « Chez mes parents » |
| **Profil climatique** | obligatoire, choisi dans une liste | Décalage saisonnier appliqué aux fenêtres des règles (§5.4) |
| Localisation précise | facultative | Commune ou coordonnées — sert à la météo (lot 5) et au géocodage automatique du profil (lot ultérieur) |
| Altitude | facultative | Correction supplémentaire du décalage |
| Surface | facultative | Information, et dimensionnement de l'onboarding |
| Propriétaire | obligatoire | Le compte titulaire |
| Membres | facultatif | Comptes invités (§2.1) |

> **RG-20** — Un utilisateur accède à un jardin par une **adhésion**, qui porte
> son **rôle** : `propriétaire` ou `membre` (§2.1). Le propriétaire est unique et
> seul à pouvoir supprimer le jardin. L'adhésion existe dès la première
> migration, même tant qu'un seul compte existe : l'ajouter après coup imposerait
> de reprendre toute l'autorisation.

> **RG-21** — **Le filtrage par jardin est imposé par défaut**, jamais laissé à
> la discipline de chaque requête. Aucune donnée d'un jardin n'est atteignable
> depuis un autre — sous aucun écran, aucun filtre, aucune URL devinée. La seule
> exception est la bibliothèque de référence (§5.2), commune et en lecture seule.
>
> Une fuite entre jardins n'est pas un défaut qu'on corrige, c'est un incident
> qu'on déclare, et elle se produit par omission dans *une* requête sur trois
> cents. Elle se vérifie donc par des tests automatisés dédiés (§8.4), jamais par
> relecture.

> **RG-43** — Le **jardin courant est visible en permanence à l'écran**. Dès
> qu'un compte peut atteindre plusieurs jardins, une saisie faite dans le mauvais
> jardin est indétectable après coup : rien dans la donnée ne dit qu'elle s'est
> trompée de terrain.

> **RG-22** — Le jardin est **obligatoire et créé avant toute autre donnée**.
> Il n'existe pas de donnée orpheline. Techniquement, l'identifiant de jardin est
> porté par toutes les tables métier dès la première migration, même tant qu'un
> seul jardin existe : l'ajouter après coup impose une migration de données à
> risque et une reprise de toutes les requêtes.

> **RG-23** — Le **profil climatique** est saisi à la création et n'est pas
> facultatif : sans lui, les règles de la bibliothèque ne peuvent pas être
> calées et l'outil produit des échéances fausses dès le premier jour (§5.4).
> Au MVP il est **choisi par l'utilisateur dans une liste courte**, pas déduit
> d'un référentiel géographique. La localisation précise, elle, reste
> facultative : elle ne sert qu'à la météo (lot 5).

### 3.2 Trois niveaux, deux entités

```
Jardin
 └── Zone          classement — « verger haut », « potager », « devant la maison »
      └── Plante   ← la seule entité sur laquelle on intervient
```

> **RG-16** — **La plante est l'entité centrale du domaine**, et la seule qui
> reçoive des interventions. C'est elle qui porte l'identité, l'histoire, les
> règles d'entretien et la valeur de l'outil : « ce pommier a été taillé 4 fois
> en 6 ans, traité 2 fois par an, et a produit 30 kg l'an dernier ».

> **RG-34** — La **zone est un classement, pas un objet d'entretien**. Elle ne
> porte ni intervention, ni règle, ni échéance. Elle sert à filtrer, grouper et
> ordonner la tournée. *(Abroge RG-17 et RG-33.)*

**Conséquence assumée : la pelouse est une plante.** Un gazon est un groupe
végétal traité comme une unité — exactement la définition d'une plante (RG-12).
Il en va de même d'un massif, d'un tronçon de haie, d'un rang de framboisiers.
Tondre est donc une intervention sur une plante, comme tailler.

Ce que ce choix laisse de côté, et qu'il faudra reprendre au lot 3 : les
interventions qui portent sur le **sol** plutôt que sur le végétal — amender
une planche, décompacter, bâcher. Elles arrivent avec le potager, et c'est à ce
moment-là qu'on décidera si une zone peut recevoir une intervention. Le trancher
maintenant serait payer aujourd'hui pour un besoin de dans deux ans.

### 3.3 Zone

Regroupement géographique et opérationnel : « verger haut », « potager »,
« devant la maison », « bas du terrain ». Une zone sert à :

- filtrer et regrouper les échéances (« ce matin je fais le verger haut ») ;
- appliquer une intervention à toutes les plantes d'une zone en une saisie ;
- ordonner la tournée terrain.

Elle porte un nom, un ordre de tournée, et appartient à un jardin.

**Zones plates au MVP** : pas de sous-zones. Le besoin réel de hiérarchie
apparaît avec le potager (un potager qui contient ses planches), donc au lot 3.
Une hiérarchie coûte des requêtes récursives partout où l'on agrège ; elle
attendra d'être nécessaire.

### 3.4 Plante

Une plante est **un individu ou un groupe végétal traité comme une unité**.

| Catégorie | Exemples | Cycle |
|---|---|---|
| Arbre fruitier | pommier Reinette, cerisier du fond | pérenne |
| Arbre d'ornement | chêne, bouleau | pérenne |
| Arbuste | rosier, hortensia, forsythia | pérenne |
| Haie (tronçon) | charmille côté route | pérenne |
| Rang / touffe de petits fruits | rang de framboisiers, groseillier | pérenne |
| Vigne, fruitier palissé | treille, espalier | pérenne |
| **Pelouse, prairie** | pelouse devant, prairie du bas | pérenne |
| **Massif** | massif d'entrée | pérenne |
| Culture annuelle | tomates de la planche 3, rang de carottes | temporaire — lot 3 |

> **RG-18** — Une **culture annuelle est une plante à durée de vie limitée**,
> avec une date de mise en place et une date de fin. Ce n'est pas une entité
> séparée. Conséquence : l'historique des plantes d'une zone *est* son
> historique de cultures, et suffira à calculer la rotation (lot 3) sans modèle
> supplémentaire.

Une plante porte : un nom, un code court, une espèce/variété (facultative), une
date de plantation ou un âge estimé, **sa zone**, un **état** (`active` ou
`archivée`) assorti d'un **motif d'archivage** libre (arrachée, morte,
remplacée, culture terminée), une localisation, une **photo de couverture**, des
photos, des notes.

> **RG-35** — Une plante a une **photo de couverture**, choisie parmi ses
> photos. C'est elle qui l'identifie dans les listes et le sélecteur terrain —
> reconnaître un arbre à sa photo est plus rapide que lire son nom, surtout
> devant lui.

> **RG-02** — Une plante n'est jamais supprimée, elle est **archivée** (arrachée,
> morte, remplacée, culture terminée). Son historique d'interventions reste
> consultable, sinon la traçabilité perd son sens dès la première année.

#### Granularité : jusqu'où découper ?

> **RG-12** — Une plante est **la plus petite entité dont on veut un historique
> distinct**. Si deux végétaux sont toujours traités ensemble, le même jour, de
> la même façon, ils forment une seule plante.

Applications de cette règle, actées :

| Cas | Découpage | Motif |
|---|---|---|
| Rang de framboisiers | **1 plante** | Taillé, paillé et récolté d'un bloc ; l'historique pied par pied n'apporte rien |
| Haie | **1 plante par tronçon homogène** | Un tronçon = une espèce et un régime de taille ; deux espèces ou deux fréquences ⇒ deux plantes |
| Arbre fruitier | **1 plante par arbre** | Taille, traitement, récolte et santé divergent d'un arbre à l'autre |
| Pelouse | **1 plante par surface tondue en une fois** | Correspond à la tonte réelle ; deux pelouses tondues à des rythmes différents en font deux |
| Massif d'ornement | **1 plante**, sauf végétal remarquable isolé | Entretenu globalement |

Le découpage n'est pas figé : une plante doit pouvoir être **scindée** plus tard
(un framboisier devenu remarquable, un tronçon de haie replanté en autre
espèce) sans perdre l'historique, qui reste attaché à la plante d'origine et
référencé par la nouvelle.

### 3.5 Ce qui n'est pas modélisé

**Les équipements** — cuve, composteur, serre, goutte-à-goutte, clôture — sont
**hors du modèle**. L'outil trace le vivant. Un composteur ne se taille pas, ne
se récolte pas, et son entretien ne relève pas des mécanismes de ce produit
(fenêtres saisonnières, calage climatique, bibliothèque d'espèces). Les faire
entrer coûterait une abstraction — le supertype « objet d'entretien » —
au bénéfice d'un seul cas d'usage marginal.

*Décision du 23/08/2026 ; à rouvrir seulement si le besoin se manifeste à
l'usage.*

### 3.6 Localisation d'une plante

Trois besoins distincts sont souvent confondus sous le mot « localiser ». Ils
n'appellent pas la même réponse.

| # | Besoin | Formulation | Réponse |
|---|---|---|---|
| B1 | **Identifier** | « Je suis devant cet arbre, ouvre sa fiche » | Nom + tri par proximité, ou scan d'étiquette |
| B2 | **Retrouver** | « L'outil me dit de traiter le prunier n°3 : où est-il ? » | Repère textuel + étiquette physique, plan plus tard |
| B3 | **Organiser** | « Que dois-je faire dans ce coin du terrain ? » | Zone au MVP, carte en V1 (lot 4) |

**Ce que le GPS d'un téléphone sait faire — et ne sait pas faire.** Sa précision
utile est de l'ordre de 3 à 10 m en terrain dégagé, et se dégrade nettement
sous couvert d'arbres. Il ne distingue donc pas deux pommiers plantés à 5 m
l'un de l'autre : il ne peut pas servir à **identifier** une plante (B1). En
revanche, il réduit une liste de 300 plantes à 4 ou 5 candidates, ce qui, combiné
au nom, résout B1 en pratique et sans effort de saisie.

D'où le découpage retenu : **le nom identifie, la coordonnée situe.**

> **RG-13** — Une plante est identifiée par son **nom**, unique au sein de sa zone,
> et par un **code court** stable attribué automatiquement (`FRU-014`,
> `HAI-003`, `PLA-002`). Aucune fonction de l'outil ne repose sur la coordonnée
> pour identifier une plante : la coordonnée est un attribut imprécis, facultative
> et corrigeable.

> **RG-14** — La géométrie d'une plante est un **point, une ligne ou un
> polygone**, choisi selon sa nature. Un rang de framboisiers, une haie ou une
> planche de potager ne sont pas des points ; les modéliser comme tels
> imposerait de refaire le modèle au lot 4.

> **RG-15** — **Capture opportuniste dès le MVP, exploitation plus tard.** La
> position est relevée automatiquement, avec sa précision et sa source
> (`gps` / `manuel` / `plan`), à la création d'une plante sur le terrain et à
> chaque intervention saisie sur place. Elle n'est pas affichée au MVP. Sans
> cette capture précoce, le lot 4 imposera de reparcourir tout le terrain,
> plante par plante, téléphone en main.

**Étiquetage physique.** Le code court est imprimable sur une étiquette
résistante posée sur la plante. C'est la seule réponse fiable à B2 dans un
verger d'arbres semblables — un plan ne dit pas lequel des quatre pommiers du
rang est le n°3. Le passage à une étiquette scannable (QR ou NFC) qui ouvre
directement la fiche est une évolution ultérieure, mais **le code court doit
exister dès le MVP** pour que les étiquettes posées la première année restent
valables.

**Champs de localisation :**

| Champ | Statut | Rôle |
|---|---|---|
| Zone | obligatoire | Regroupement, filtrage, tournée (B3). Plate au MVP — la sous-zone arrive avec le potager (§3.3, lot 3) |
| Nom | obligatoire | Identité humaine (B1) |
| Code court | automatique | Identité physique, étiquette (B2) |
| Repère | facultatif, texte libre | « 3e du rang, côté route », « derrière le cabanon » (B2) |
| Géométrie | facultative | Point/ligne/polygone + précision + source (B1 par proximité, B3 en V1) |

### 3.7 Type d'intervention

Le catalogue des gestes possibles. Chaque type porte sa **famille** (taille,
traitement, sol, eau, entretien, cycle végétal, récolte), qui détermine les
champs qu'il réclame à la saisie — une quantité pour une récolte (lot 1), un
produit et une dose pour un traitement (lot 2).

| Famille | Types d'intervention |
|---|---|
| Taille | taille de formation, taille d'entretien/fructification, taille en vert, rabattage, taille de haie, éclaircissage des fruits |
| Traitement | traitement cuivre, huile blanche, purin, anti-limaces, badigeon, chaulage |
| Sol & fertilisation | amendement, compost, paillage, désherbage, binage |
| Eau | arrosage |
| Entretien courant | tonte, débroussaillage, ramassage des feuilles |
| Cycle végétal | semis, plantation, repiquage, tuteurage, palissage, protection hivernale |
| Récolte | récolte, avec quantité (lot 1) |

Tous s'appliquent à une plante : c'est la seule entité qui reçoit des
interventions (RG-16).

> **RG-03** — Le catalogue des types d'intervention est **extensible par
> l'utilisateur**. Aucune liste fermée en dur dans le code.

### 3.8 Intervention

Le fait enregistré : « le 12/02/2026, taille d'entretien sur le pommier du
fond, par Léon, 40 min, note : "trois branches mortes retirées" ».

Champs : plante(s) concernée(s), type, date effective, durée (facultative),
produit et dose utilisés (si traitement), quantité (si récolte), note libre,
**photos**, auteur.

> **RG-38** — Une intervention peut porter une ou plusieurs **photos**, prises
> au moment de la saisie. Elles servent à **montrer que c'est fait** et dans
> quel état : un avant/après de taille, une branche malade retirée, la hauteur
> de coupe. C'est la seule trace qui ne dépende pas de la mémoire, et la seule
> qui permette de comparer deux saisons. La photo n'est jamais obligatoire —
> l'imposer casserait le parcours terrain à trois gestes (P1).

> **RG-04** — Une intervention peut porter sur **plusieurs plantes à la fois**
> (« traité les 6 fruitiers du verger haut »). Elle se décompose alors en
> autant d'enregistrements individuels, pour que chaque plante garde un
> historique complet et exploitable par le moteur d'échéances.

> **RG-05** — La date d'une intervention est **toujours modifiable et peut être
> rétroactive**. C'est le cas nominal, pas l'exception : on saisit le lundi ce
> qu'on a fait le samedi.

### 3.9 Règle, échéance, intervention — les trois temps

C'est le mécanisme central du produit, et il tient en trois mots qui décrivent
**trois temps différents de la même chose**.

| | Entité | Temps | Exemple |
|---|---|---|---|
| 1 | **Règle** | permanent | « Ce pommier se taille chaque année, entre décembre et mars » |
| 2 | **Échéance** | à venir | « Tailler le pommier du fond, avant le 31 mars 2027 » |
| 3 | **Intervention** | passé | « Taillé le 12 février 2027, 40 min » |

**La règle est une loi, pas un ordre.** Elle ne dit pas *quand* il faut agir,
elle dit *selon quelle logique* la prochaine fois se calcule : « tous les
7 jours », « chaque hiver entre décembre et mars », « tous les trois ans ». Une
règle ne se coche pas et ne se termine jamais — elle est vraie tant que la
plante est là. Elle se règle une fois, à la création de la plante, et
l'utilisateur n'y revient qu'en cas d'erreur.

**L'échéance est l'occurrence concrète**, calculée par le moteur à partir de la
règle et de l'historique. C'est elle qui apparaît dans le tableau de bord et
dans la liste du jour. C'est elle qu'on coche. C'est bien « ce que je dois
faire, et avant quand ».

**L'intervention est le fait accompli**, celui qu'on enregistre depuis le
terrain. Elle solde l'échéance, et son enregistrement fait recalculer la
suivante par la règle.

Le cycle complet, sur un exemple :

```
RÈGLE      « pommier du fond · taille · chaque année · fenêtre 01/12 → 31/03 »
   │
   ▼  le moteur calcule
ÉCHÉANCE   « tailler le pommier du fond · fenêtre ouverte · avant le 31/03 »
   │
   ▼  Léon appuie sur « fait »
INTERVENTION  « taille · 12/02/2027 · 40 min »
   │
   ▼  le moteur recalcule à partir de la règle
ÉCHÉANCE   « tailler le pommier du fond · fenêtre déc. 2027 → mars 2028 »
```

Sans règle, il n'y a pas d'échéance : l'outil devient un simple journal de ce
qui a été fait. C'est la règle qui transforme la traçabilité en anticipation —
donc elle qui fait la différence entre cet outil et un carnet.

#### D'où vient une règle ?

> **RG-39** — Une règle est **définie par la bibliothèque, possédée par
> l'utilisateur**. Elle est écrite par le curateur pour une espèce, **copiée**
> dans le jardin à la création de la plante, et devient dès lors la règle de
> cette plante. L'utilisateur peut la modifier ; sa version fait foi (RG-09).

La copie n'est pas un détail d'implémentation, c'est ce qui rend le système
tenable :

- sans elle, corriger une règle dans la bibliothèque déplacerait
  silencieusement les échéances de tous les jardins (RG-24) ;
- sans elle, l'utilisateur ne pourrait pas dire « moi, je tonds tous les
  10 jours ».

En pratique, **il n'y touchera pas dans la grande majorité des cas** — c'est
tout l'intérêt d'une bibliothèque. Mais RG-08 rappelle que la correction est un
cas nominal : nos règles seront fausses par endroits, et se calibrent sur trois
saisons.

Une plante dont l'espèce est absente de la bibliothèque n'est pas bloquée :
l'utilisateur crée ses règles de toutes pièces.

#### Une règle, une échéance

> **RG-40** — Une règle active produit **exactement une échéance à venir** : la
> prochaine. Jamais zéro — la règle ne servirait à rien. Jamais plusieurs — on
> ne saurait pas laquelle solder.
>
> **Y compris hors saison.** Une tonte en janvier n'est pas « aucune échéance » :
> c'est une échéance datée de l'ouverture de la saison suivante, visible en
> planning et non en tâche du jour. Une règle qui cesse de produire une échéance
> ne redémarre jamais — rien ne la réveillerait.

Une plante porte en général **plusieurs règles** (taille, traitement,
arrosage), donc plusieurs échéances simultanées, une par règle.

Une échéance porte une **fenêtre**, pas seulement une date limite. C'est ce qui
permet de dire « c'est le bon moment » et pas seulement « c'est bientôt trop
tard ». Son état évolue seul avec le calendrier :

```
règle : « taille du pommier · fenêtre 01/12 → 31/03 · optimale 01/02 → 15/03 »

  septembre   à venir     hors fenêtre, visible en planning
  1er déc.    ouverte     proposée
  1er févr.   optimale    mise en avant
  mi-mars     urgente     alerte
  1er avril   manquée     consignée, la suivante est proposée pour l'hiver d'après
```

## 4. Le moteur d'échéances

### 4.1 Pourquoi un simple intervalle ne suffit pas

Question posée en amont : « un arbre taillé doit être taillé dans 12 mois, je
suppose ? » — **non**, et c'est précisément ce qui rend l'outil utile plutôt
que trivial.

- Un pommier se taille **en dormance, de décembre à mars**, hors gel. Si la
  taille a eu lieu le 20 décembre, la suivante n'est pas due le 20 décembre
  suivant : elle est due **quelque part dans la fenêtre déc–mars**, idéalement
  février–mars.
- Un cerisier ou un prunier se taille **après la récolte, en août–septembre**.
  Taillé en hiver, il gomme et s'expose au chancre. Même geste, même famille
  d'arbres, fenêtre opposée.
- La **périodicité** elle-même varie : un jeune arbre en taille de formation se
  taille chaque année pendant 3 à 5 ans ; un arbre adulte en forme libre peut
  n'être repris que tous les 2 à 3 ans, et une reprise sévère se **répartit sur
  3 ans** pour ne pas l'affaiblir.
- Une tonte, elle, obéit bien à un intervalle — mais un intervalle **qui varie
  selon la saison** : ~7 jours au printemps, 7 à 10 jours à l'automne, nul en
  plein été sec.
- Un traitement cuivre se pose 2 à 3 fois par an, dans des fenêtres liées à des
  **stades du végétal** (chute des feuilles, avant débourrement, après
  floraison), avec un intervalle minimal de 12 à 15 jours entre deux
  applications.
- Certaines interventions sont **interdites** sur une période : ne pas tailler
  les haies du 16 mars au 15 août (nidification).

Un modèle à intervalle unique produirait donc des échéances fausses la moitié
du temps, et des échéances fausses font abandonner l'outil en une saison.

### 4.2 Les types de règles

Cinq types couvrent l'ensemble des besoins. **Deux suffisent au MVP** — et
c'est un choix, pas une approximation : les trois autres se ramènent à ces deux
là dans presque tous les cas courants.

#### Au MVP

**R1 — Intervalle glissant**
Prochaine échéance = dernière occurrence + N jours.
_Exemples : tonte tous les 7 jours, arrosage tous les 3 jours._
Paramètre : **variation saisonnière** de N (table mois → intervalle),
indispensable pour la tonte — 7 j au printemps, 14 j en été sec, 0 en hiver.

**R2 — Fenêtre calendaire**
L'intervention est due à l'intérieur d'une fenêtre annuelle (début → fin,
éventuellement à cheval sur le changement d'année), avec une **période
optimale** plus étroite à l'intérieur.
_Exemple : taille du pommier, fenêtre 01/12 → 31/03, optimale 01/02 → 15/03._
Paramètres : **périodicité en années** (1 = tous les ans, 3 = une année sur
trois) et **plusieurs fenêtres possibles** dans l'année.

> **RG-36** — Une règle R2 peut porter **plusieurs fenêtres**. C'est ce qui
> permet de couvrir sans R3 les traitements en deux passages (chute des
> feuilles, puis fin d'hiver) : deux fenêtres, une seule règle.

#### Plus tard, et pourquoi ils peuvent attendre

**R3 — Fenêtre + cadence interne** *(lot 2)*
K occurrences dans une même fenêtre, avec un intervalle minimal entre deux
(12 à 15 jours entre deux applications de cuivre).
*Peut attendre :* tant qu'on ne traite qu'une fois par fenêtre, R2 à fenêtres
multiples suffit. R3 ne devient nécessaire qu'avec le suivi des produits.

**R4 — Déclenchée par événement** *(lot 1)*
L'échéance naît d'un événement observé, pas du calendrier : « tailler le
cerisier dans les 30 jours suivant la récolte ».
*Peut attendre :* la même chose s'écrit en R2 avec une fenêtre 01/08 → 30/09,
ce que fait déjà l'annexe A. R4 est plus juste — il suit l'année réelle — mais
il suppose que l'utilisateur note les stades du végétal, ce qui est un
engagement de saisie qu'on ne peut pas demander la première année.

**R5 — Contrainte d'exclusion** *(lot 3 et lot 5)*
Interdit ou déconseille une échéance produite par une autre règle : pas de
taille de haie du 16/03 au 15/08 (nidification), pas de traitement avant la
pluie, pas de solanacée avant trois ans.
*Peut attendre :* voir ci-dessous.

#### Pourquoi la contrainte n'est pas nécessaire au MVP

La contrainte a l'air indispensable — l'interdiction de tailler les haies en
période de nidification est réelle et sérieuse. Mais **une fenêtre bien définie
fait déjà le travail** : au lieu d'une fenêtre « toute l'année » corrigée par
une interdiction de mi-mars à mi-août, on écrit directement une fenêtre
« 16/08 → 15/03 ». Le résultat affiché est le même, sans second mécanisme.

La contrainte ne devient réellement nécessaire que dans trois cas, et aucun
n'est au MVP :

| Cas | Pourquoi la fenêtre ne suffit plus | Lot |
|---|---|---|
| Elle dépend d'un état extérieur | « Pas de traitement si pluie sous 48 h » — la météo n'est pas dans le calendrier | 5 |
| Elle dépend de l'historique d'une autre plante | « Pas de solanacée ici avant 3 ans » — la rotation regarde les cultures passées | 3 |
| Elle est transversale à beaucoup de règles | « Aucune taille de haie en nidification, sur toutes mes haies » — écrire la fenêtre 12 fois est une source d'oubli | confort |

> **RG-37** — Au MVP, une interdiction saisonnière s'exprime **dans la fenêtre
> de la règle**, pas par un mécanisme séparé. La bibliothèque livre donc des
> fenêtres qui excluent déjà les périodes interdites — c'est au curateur de le
> faire une fois, pas à l'utilisateur de le refaire à chaque haie.

#### Le butoir : ce que le décalage climatique n'a pas le droit de franchir

RG-37 ne suffit pas seule, et c'est un piège qui ne se voit qu'en combinant deux
règles justes. Une fenêtre de haie écrite `16/08 → 15/03` exclut bien la
nidification — **dans le climat de référence**. Mais RG-25 décale les fenêtres
selon le jardin : à +21 jours (montagne) elle devient `06/09 → 05/04`, et l'outil
propose une taille de haie le 1er avril. À −15 jours (Midi) elle devient
`01/08 → 28/02`, et il la propose le 5 août. Dans les deux cas, le critère
d'acceptation n°4 est faux et l'interdiction est franchie.

Le décalage climatique est une approximation ; une interdiction n'en est pas
une. Il faut donc une borne que l'approximation ne franchit pas.

> **RG-44** — Une fenêtre peut porter des **butoirs** : une borne plancher et une
> borne plafond, en jour/mois, **que le décalage climatique ne franchit jamais**.
> Ils sont appliqués après la projection et après le décalage.
>
> Un butoir **rogne** une fenêtre, il ne la déplace pas. Rogner conserve
> l'interdiction ; déplacer réintroduirait une contrainte R5 par la porte de
> derrière.
>
> Deux usages, un seul mécanisme :
>
> | Usage | Exemple | Nature |
> |---|---|---|
> | **Interdiction** | pas de taille de haie du 16/03 au 15/08 | écologique (légale pour les exploitations agricoles, recommandée pour un jardin, voir Q19) — aucun climat n'y échappe |
> | **Plausibilité agronomique** | on ne plante pas en décembre, même à Perpignan | borne ce qu'un décalage de 21 jours rendrait absurde |
>
> **Le rognage se teste, il ne se suppose pas.** Si, pour l'un des cinq profils
> climatiques, le rognage laisse une fenêtre de moins de 14 jours ou en fait
> sortir la période optimale, c'est une erreur de données de la bibliothèque, pas
> un cas limite à absorber : la version n'est pas publiée (B7 du protocole de
> bibliothèque).

### 4.3 États d'une échéance

| État | Signification | Traitement |
|---|---|---|
| `à venir` | hors fenêtre, connue | visible en planning, pas en tâche du jour |
| `ouverte` | dans la fenêtre, pas encore optimale | proposée |
| `optimale` | dans la période idéale | mise en avant |
| `urgente` | fin de fenêtre à moins de X jours | alerte |
| `soldée` | une intervention l'a réalisée | fermée, conservée dans l'historique |
| `manquée` | fenêtre close sans réalisation | consignée, reportée à l'occurrence suivante avec mention |
| `bloquée` | une contrainte R5 s'y oppose | affichée avec le motif, non proposée — **lot 3** |
| `suspendue` | l'utilisateur l'a écartée pour cette saison | motif obligatoire |

> **RG-06** — Toute échéance porte une **explication en une phrase** : de quelle
> règle elle vient, sur quelle fenêtre ou quel intervalle, quel décalage
> climatique lui a été appliqué (RG-26), et si la règle qui la fonde n'est
> qu'`indicative` (RG-31). « Taille du pommier, fenêtre déc.–mars décalée de
> 15 jours pour votre climat. »
>
> Une échéance sans explication est indéboguable — pour l'utilisateur qui doute
> d'une périodicité comme pour le support qui doit la vérifier. C'est aussi ce
> qui rend une règle contestable, donc corrigeable (RG-08).

> **RG-07** — Une échéance manquée n'est **jamais effacée silencieusement**.
> Elle est archivée comme manquée et visible dans l'historique de la plante. C'est
> l'information la plus utile de l'outil : elle explique l'état du végétal
> l'année suivante.

### 4.4 Actions possibles sur une échéance

- **Faire** → crée l'intervention, ferme l'échéance, recalcule la suivante.
- **Reporter** → décale de N jours à l'intérieur de la fenêtre.
- **Sauter cette occurrence** → motif obligatoire, l'échéance suivante est
  calculée comme si celle-ci avait été faite ou non selon le type de règle.
- **Modifier la règle** → depuis l'échéance, pour corriger une périodicité qui
  s'avère fausse à l'usage.

> **RG-08** — La correction d'une règle depuis le terrain est un cas nominal.
> L'utilisateur n'est pas jardinier au départ : les règles se calibrent à
> l'usage, sur trois saisons. L'outil doit rendre cette correction facile et
> tracée (historique des versions de règle).

---

## 5. Bibliothèque de référence

### 5.1 Rôle

**La valeur de l'outil dépend entièrement de la qualité des règles initiales.**
On ne peut pas demander à un utilisateur — qui n'est pas nécessairement
jardinier — de saisir de zéro la fenêtre de taille du prunier. C'est ce qui
sépare un outil utile d'un carnet de notes avec des rappels.

L'outil embarque donc une **bibliothèque de référence** : pour un ensemble
d'espèces courantes, un jeu de règles pré-remplies (taille, traitements,
fertilisation) qui s'appliquent automatiquement à la création d'une plante.

- L'utilisateur choisit une espèce → les règles types sont proposées, **déjà
  calées sur le climat de son jardin** (§5.4).
- Il peut les accepter, les modifier pour cette plante, ou les refuser.
- Une règle modifiée sur une plante ne remonte pas dans la bibliothèque, sauf
  action explicite (« appliquer à tous mes pruniers »).
- Chaque règle de la bibliothèque porte sa **source** (d'où vient cette
  périodicité), pour pouvoir la contester plus tard.

> **RG-09** — Une règle de la bibliothèque est une **proposition**, jamais une
> contrainte. La plante fait foi.

> **RG-51** — Une espèce peut porter des **variantes de conduite** quand sa
> façon d'être conduite change ses règles (framboisier : remontant ou non
> remontant). Une règle de la bibliothèque vaut pour toute l'espèce ou pour une
> seule variante. À la création d'une plante d'une espèce à variantes,
> l'utilisateur choisit la variante, et seules les règles communes et celles de
> cette variante lui sont proposées. Changer de variante plus tard, c'est
> corriger les règles de la plante (RG-08) : rien ne bascule en silence.

**Contenu de départ, sourcé et prêt** (annexe A, sourçage du 19/10/2026) :
pommier, poirier, prunier, cerisier, vigne, groseillier à grappes, cassissier,
haie persistante, haie caduque, rosier, arbuste à floraison printanière,
arbuste à floraison estivale, pelouse.

Le **framboisier** y entre aussi, en une espèce à deux variantes de conduite,
remontant et non remontant (RG-51, Q21).

**Sourcé, en attente d'une décision** : pêcher (incompatible avec B-T1 tel
qu'il est écrit, Q20).

**Hors de la liste de départ** : les traitements communs aux fruitiers (cuivre,
huile blanche), non sourcés et écrits en R3 alors que le MVP n'a que R1 et R2
(Q23). L'utilisateur peut toujours créer ses propres règles de traitement. Le
groseillier à maquereau est écarté lui aussi : une seule source retenue.

Cette seconde liste n'est pas une omission, c'est l'application de B4 et B5 du
protocole de bibliothèque : une périodicité non consultée est une invention
plausible, et cinq espèces sourcées valent mieux que neuf supposées. Le test
B-T10 vérifie que toute espèce annoncée dans la liste de départ porte au moins
une règle — il échouerait aujourd'hui sur ces cinq-là. Le reste s'ajoute à
l'usage.

### 5.2 Gouvernance : partagée, pas collaborative

La bibliothèque est **commune à tous les jardins**, maintenue par le curateur
du service (§2.1), en lecture seule pour les utilisateurs. C'est la seule
donnée qui traverse la frontière entre jardins (RG-21).

> **RG-24** — La bibliothèque est **versionnée**. Corriger une règle type ne
> modifie jamais les règles déjà copiées dans un jardin : la correction est
> **proposée** à l'utilisateur (« la fenêtre de taille du cerisier a été
> corrigée, l'appliquer à vos 3 cerisiers ? »), jamais appliquée d'office.
> Sans cela, une correction de la bibliothèque déplacerait silencieusement les
> échéances de tous les jardins.

Une espèce absente de la bibliothèque ne bloque rien : l'utilisateur crée une
plante « libre » et définit ses propres règles. Les demandes d'ajout
remontent au curateur, mais **aucun utilisateur n'écrit dans la bibliothèque
partagée** — une règle fausse contaminerait tous les jardins.

### 5.3 Deux natures de données, deux exigences

La bibliothèque a vocation à être **largement remplie**, y compris au-delà de
ce que l'outil exploite aujourd'hui : un catalogue riche améliore
immédiatement la saisie (autocomplétion, reconnaissance de la variété tapée par
l'utilisateur) même quand aucune règle n'y est attachée, et il évite d'avoir à
repasser sur les données plus tard.

Mais toutes les données de la bibliothèque n'ont pas le même effet, et elles
n'appellent donc pas la même exigence :

| Nature | Exemples | Effet si la valeur est fausse | Exigence |
|---|---|---|---|
| **Descriptive** | nom latin, famille botanique, cycle, exposition, rusticité, hauteur adulte, période de floraison | Aucun — la donnée est affichée, pas calculée | Remplir largement |
| **Opérationnelle** | fenêtre de taille, périodicité, cadence de traitement, délai de retour en rotation | **L'outil produit des échéances fausses sans que rien ne proteste** | Sourcée ou signalée |

> **RG-30** — Les données **descriptives** peuvent être ajoutées massivement,
> sans restriction. Elles n'entrent dans aucun calcul.

> **RG-31** — Toute règle **opérationnelle** porte un **niveau de confiance** :
> `vérifiée` (sourcée, source consultable) ou `indicative`. Une règle
> indicative est utilisable, mais elle est signalée comme telle au moment où
> l'utilisateur l'applique, et l'explication de l'échéance (RG-06) le rappelle.
>
> C'est ce qui permet de charger beaucoup de règles sans mentir. Sans ce
> marqueur, une périodicité inventée est indiscernable d'une périodicité
> vérifiée, et l'utilisateur ne découvre l'erreur qu'à la saison suivante,
> quand elle n'est plus rattrapable.

Le remplissage massif est donc encouragé — à condition que ce qui n'a pas été
vérifié soit **déclaré non vérifié**, pas déguisé en certitude.

### 5.4 Calage climatique — la contrainte qui rend la bibliothèque possible

Une bibliothèque nationale unique donnerait des échéances fausses à la majorité
des utilisateurs : **un même geste se décale d'environ 3 semaines entre la
Bretagne et le Midi, et jusqu'à 4 semaines en montagne.** Une fenêtre de taille
« 01/12 → 31/03 » n'a pas le même sens à Perpignan et à Briançon.

La France métropolitaine couvre les zones de rusticité 5 à 10, réparties en
cinq régimes climatiques (montagnard, continental, océanique, méditerranéen, et
les nuances de transition).

**Mécanisme retenu — un décalage, pas des bibliothèques parallèles :**

- Chaque jardin porte un **profil climatique** (§3.1), qui se résume à un
  **décalage saisonnier en jours**.
- Les règles de la bibliothèque sont écrites pour un **climat de référence**
  unique. Le moteur applique le décalage du jardin au moment du calcul de
  l'échéance.

#### Ce qui entre dans le MVP, et ce qui n'y entre pas

La complexité n'est pas dans le décalage, elle est dans sa **déduction
automatique** — laquelle suppose un référentiel de communes, un géocodage et
une table de correspondance climatique, c'est-à-dire une dépendance externe que
la contrainte d'hébergement (§8.6) écarte de toute façon au démarrage.

Les deux moitiés se séparent proprement :

| | MVP | Plus tard |
|---|---|---|
| Champ `décalage_jours` sur le jardin | ✅ | |
| Application du décalage aux fenêtres par le moteur | ✅ | |
| Mention du décalage dans l'explication de l'échéance | ✅ | |
| **Choix du profil dans une liste de 5 entrées** | ✅ | |
| Déduction automatique depuis la commune | | ✅ |
| Correction par altitude | | ✅ |
| Ajustement sur la météo réelle | | ✅ (lot 5) |

Liste proposée au MVP — une question, un menu déroulant, aucune dépendance :

| Profil | Décalage | Correspond à |
|---|---|---|
| Midi, littoral méditerranéen | −15 j | Zones 9–10 |
| Sud-Ouest, vallée du Rhône | −7 j | Zone 8–9 |
| **Plaine tempérée (référence)** | **0 j** | Bassin parisien, centre, zone 8 |
| Nord, Est, océanique frais | +7 j | Zones 7–8 |
| Montagne, plus de 600 m | +21 j | Zones 5–7 |

> **RG-29** — Ce qui doit exister dès le MVP, c'est le **champ et son
> application par le moteur**, pas son remplissage automatique. Un moteur qui
> ne sait pas décaler oblige, le jour où on l'ajoute, à retoucher tout le
> calcul, l'explication des échéances et les tests. Un menu déroulant remplacé
> plus tard par un géocodage ne coûte rien : la donnée produite est la même.

> **RG-25** — Le décalage climatique s'applique aux **bornes de calendrier** —
> les fenêtres des règles R2 et R3, et les **bornes de saison** d'un intervalle
> saisonnier R1 — et **jamais à la valeur d'un intervalle**. Une tonte reste à
> 7 jours partout ; c'est la saison de tonte qui commence plus tôt dans le Midi
> et finit plus tard, pas l'intervalle qui change.
>
> Sans la seconde moitié de cette règle, la justification serait fausse : les
> bornes de saison vivraient dans le climat de référence et la tonte démarrerait
> en mars à Perpignan comme à Briançon.

> **RG-45** — Le décalage appliqué aux fenêtres est **borné par les butoirs**
> (RG-44). C'est la seule limite au mécanisme de RG-25, et elle est nécessaire :
> sans elle, un profil climatique suffit à faire franchir une interdiction
> saisonnière.

> **RG-26** — Le décalage est **visible et corrigeable par l'utilisateur**, et
> l'explication de l'échéance (RG-06) le mentionne : « taille du pommier,
> fenêtre déc–mars décalée de 15 jours pour votre climat ». Un décalage
> invisible est indéboguable pour l'utilisateur comme pour le support.

**Limite assumée :** un décalage global par jardin ne capte ni les microclimats
(versant nord, fond de vallée gélif, mur exposé au sud), ni les variations
d'une année à l'autre. C'est une approximation, et elle est meilleure que
l'absence de décalage. L'ajustement fin passe par la correction manuelle des
règles d'une plante (RG-09) et, plus tard, par la météo réelle (lot 5).

---

## 6. Lotissement fonctionnel

Les lots décrivent **ce qui va ensemble**. Les jalons disent **ce qui est livré
ensemble** :

| Jalon | Contenu | Condition de livraison |
|---|---|---|
| **V1** — première version livrée | **Lot 0 + lot 4 (plan du terrain)** | Critères §10.1 et §10.2, sur le jardin pilote |
| Ensuite | Lot O dès qu'un deuxième utilisateur existe, puis lots 1 bis, 1, 2, 3, 5 selon le besoin | Critères à écrire avec le lot |

**Il n'y a pas de MVP livré à part.** Dans ce document, « MVP » désigne le
**périmètre du lot 0**, pas un jalon. Décision du 19/10/2026 : avec une
réalisation par agents (§1.5), le lot 0 seul ne justifie pas une livraison
distincte.

**Pourquoi la carte ne se contente plus d'attendre les relevés GPS.** La 0.12
avait d'abord placé la carte après le lot 0, au motif qu'elle afficherait un
terrain vide tant qu'une saison de saisies n'aurait pas rempli les positions.
Ce motif supposait que la carte arrive *après* l'inventaire. Livrée avec lui,
elle sert à le faire : on place chaque plante sur le plan au moment de la
créer (lot 4, « placement à la création »). Le relevé GPS (RG-15) reste capturé
et sert au tri par proximité.

**Ce qui reste ordonné, à l'intérieur du jalon.** Le moteur d'échéances, le
journal et la vue terrain sont construits **avant** la carte. C'est là que
sont la difficulté et la valeur (§4), et c'est ce qui permet de faire tourner le
cœur de l'outil sur le jardin pilote pendant la fenêtre de taille d'hiver si la
carte n'est pas terminée. L'ordre détaillé est au chapitre technique 7.

### Lot 0 — MVP : journal et échéances

Objectif : utilisable et utile dès la première saison, sans rien d'autre.

Le produit est conçu multi-jardins (§1.2), mais le MVP **n'est pas encore
ouvert au public** : il pose les fondations qui ne se greffent pas après coup,
et laisse le reste au lot « Ouverture ».

> **RG-41** — Distinguer **les fondations du cloisonnement**, qui sont dans le
> MVP parce qu'elles ne se rattrapent pas, et **les fonctions de compte**, qui
> s'ajoutent sans rien casser le jour où un deuxième utilisateur existe.
> Construire les secondes avant d'avoir un seul utilisateur, c'est retarder la
> première échéance affichée sur le terrain pilote.

**Socle — les fondations seulement**

- **Identifiant de jardin sur toutes les données** (RG-22) et **filtrage imposé
  par défaut** (RG-21) dès la première migration. C'est la partie
  irrattrapable, et elle est quasi gratuite.
- **Un compte propriétaire**, créé en base à l'installation. Connexion simple.
- **Création du jardin** : nom obligatoire, **profil climatique choisi dans
  une liste de 5 entrées** (RG-23, §5.4). Pas de géocodage, pas de référentiel
  de communes.
- **Export intégral** des données (§8.1) — utile pour soi bien avant d'être une
  obligation légale.

**Métier**

- **Inventaire** : créer, éditer, archiver zones et plantes, avec code court
  automatique et repère textuel. Import initial en lot (formulaire répété ou
  collage d'une liste).
- **Capture de position** silencieuse (RG-15) : relevé GPS enregistré à la
  création d'une plante et aux interventions saisies sur le terrain. Stockée,
  non exploitée dans l'interface.
- **Tri par proximité** dans le sélecteur de plantes de la vue terrain, dès que
  des positions existent — le seul usage de la géométrie au MVP, et celui qui
  rapporte le plus pour le moins d'effort.
- **Étiquettes** : export imprimable des codes courts, pour étiqueter le
  terrain dès la première saison.
- **Catalogue** des types d'intervention (fourni + extensible).
- **Journal** : enregistrer une intervention (une ou plusieurs plantes), date
  rétroactive, note libre, photo.
- **Règles** : **R1 et R2 seulement** (§4.2) — intervalle saisonnable et
  fenêtre calendaire à fenêtres multiples. Elles couvrent tonte, taille,
  traitements en deux passages et haies. Bibliothèque de référence pour les
  espèces de départ.
- **Photos** : photo de couverture par plante (RG-35), photos d'intervention
  pour montrer que c'est fait et dans quel état (RG-38).
- **Tableau de bord** : « à faire aujourd'hui / cette semaine / ce mois »,
  groupé par zone, avec l'explication de chaque échéance.
- **Historique** par plante et par zone, filtrable, avec les échéances manquées.
- **Vue terrain** mobile : liste du jour, action « fait » en un geste, saisie
  protégée quand le réseau manque (RG-50).
- **Mise en route guidée** : le parcours P0, qui amène un nouvel utilisateur de
  l'inscription à un inventaire exploitable sans le décourager.

**Explicitement hors MVP**

- **Les règles R3, R4 et R5**, et avec R5 le mécanisme de contrainte : une
  fenêtre bien écrite suffit au MVP (§4.2, RG-37).
- **Les observations phénologiques** : elles n'existent que pour alimenter R4.
  Sans R4, elles n'ont pas d'usage — et elles demandent à l'utilisateur un
  engagement de saisie qu'on ne peut pas réclamer la première année.
- **Les équipements** : hors du modèle entièrement (§3.5).
- **Les sous-zones** : zones plates au MVP (§3.3).
- **L'inscription publique**, le mot de passe oublié, la suppression de compte
  et le parcours RGPD : lot « Ouverture ». Aucun n'exige de reprendre le
  modèle, tous exigent du travail d'interface (RG-41).

- **Le potager** (lot 3) : cultures annuelles, calendrier de semis, rotation.
  C'est le domaine le plus complexe du produit — un cycle de vie court, une
  saisonnalité fine, des contraintes croisées entre planches — et le seul qui
  se comporte différemment de tout le reste. Le modèle de données du MVP le
  prépare (une culture est une plante temporaire rattachée à une zone,
  RG-18), mais **aucun écran, aucune règle et aucun concept de rotation
  n'entrent dans le MVP**. Une planche de potager y est une zone comme une
  autre, sur laquelle on enregistre des interventions.
- Le partage d'un jardin, les récoltes chiffrées, les produits de traitement,
  la météo (lots 1 à 3 et 5). Le plan du terrain (lot 4) est livré en V1.
- La déduction automatique du profil climatique et la correction par altitude
  (rattachées au lot O depuis la 0.12).

> **RG-32** — Sortir le potager du MVP ne sort pas l'entretien des surfaces.
> Pelouse et massifs sont des **plantes** dès le premier jour (§3.4), avec
> leurs règles de tonte et de désherbage. Seules les cultures annuelles, la
> rotation et les interventions portant sur le sol attendent le lot 3.

Critères d'acceptation du MVP en §10.

### Lot O — Ouverture à d'autres utilisateurs

À déclencher quand un deuxième utilisateur existe réellement, pas avant. Le
modèle du MVP le porte déjà entièrement : ce lot n'est que de l'interface et du
parcours.

- Inscription publique, connexion, mot de passe oublié, courriel de
  vérification.
- **Suppression du compte** : effacement réel des données et des photos, export
  préalable (§8.4).
- Mentions légales, politique de confidentialité, information sur la
  localisation du jardin (RG-28).
- Quota de photos appliqué et signalé à l'utilisateur.
- **Déduction du profil climatique depuis la commune**, avec correction par
  altitude, proposée à la création du jardin et toujours modifiable (RG-26). Le
  calcul s'appuie sur un référentiel de communes **importé sur le serveur**,
  jamais interrogé à distance : l'échéance reste calculable sans réseau (§8.6).
  La liste de 5 profils du MVP reste le repli. Ce lot est le bon moment parce
  qu'un utilisateur qui découvre l'outil ne sait pas à quel profil il
  appartient, alors que le jardin pilote le sait.

> **RG-42** — Ce lot devient **obligatoire dès qu'un tiers a un compte**, sans
> exception : les obligations RGPD ne dépendent pas du nombre d'utilisateurs.
> Tant que le seul compte est celui du propriétaire du serveur, elles ne
> s'appliquent pas.

### Lot 1 bis — Partage d'un jardin

Petit lot, à placer selon le besoin réel : inviter un membre par courriel,
rôle Membre en écriture limitée (§2.1), bascule entre plusieurs jardins,
retrait d'un membre. Le modèle du MVP le porte déjà (RG-20).

### Lot 1 — Récoltes et observations

- Quantification des récoltes par plante, par unité (kg, nombre, cageots).
- Bilan de récolte par plante, par espèce, par année ; comparaison inter-annuelle.
- Journal d'**observations phénologiques** (débourrement, floraison, véraison,
  chute des feuilles) → débloque les règles **R4**.
- Constats sanitaires avec photo, suivi dans le temps d'un problème.

### Lot 2 — Traitements et intrants

- Fiches produits (nom, matière active, dose, délai avant récolte, stock).
- Enregistrement du produit et de la dose sur l'intervention de traitement.
- Règles **R3** (fenêtres multiples + intervalle minimal).
- Alerte **délai avant récolte** : bloque ou signale une récolte trop proche
  d'un traitement.
- Suivi de stock des produits, semences, plants.

### Lot 3 — Potager

- **Cultures annuelles** : une planche accueille des plantes temporaires
  (espèce, variété, date de mise en place, date de fin) — pas une entité
  distincte, mais des plantes au sens de RG-18. Le modèle du MVP les porte
  déjà ; ce lot n'ajoute que les écrans et le calendrier.
- Calendrier de semis/plantation/récolte par espèce.
- **Rotation** : la famille botanique se déduit de l'historique des plantes de
  la zone, sans saisie supplémentaire ; contrainte **R5** signalant un
  retour trop rapide de la même famille (délai paramétrable, 3 à 4 ans par
  défaut).
- Plan de culture de la saison à venir.

### Lot 4 — Plan du terrain *(jalon V1)*

Ce lot **exploite** les positions capturées depuis le MVP (RG-15) ; il ne les
collecte pas. C'est ce qui le rend réalisable sans campagne de relevé.

Il répond au besoin B3 (§3.6) : *organiser*, c'est-à-dire voir où sont les
choses et ce qu'il y a à faire à un endroit. Il ne change rien à B1 : le nom et
le code court continuent d'identifier une plante (RG-13), la carte la situe.

**Le fond de plan — deux sources, une seule obligatoire**

- **Image importée et calée** : plan dessiné, photo aérienne, capture d'écran
  d'un cadastre. L'utilisateur la cale en désignant deux ou trois points connus.
  Elle est stockée sur le serveur comme une photo et ne dépend de rien d'autre.
  C'est le fond **toujours disponible**.
- **Photographie aérienne publique** : orthophotographies de l'IGN, servies par
  la Géoplateforme en accès libre et sans clé. Vérifié le 19/10/2026 : une
  tuile de la couche `ORTHOIMAGERY.ORTHOPHOTOS` est servie par
  `https://data.geopf.fr/wmts` sans identifiant. Ce fond est un agrément : s'il
  ne répond pas, la carte s'affiche sur l'image calée ou sur un fond neutre, et
  rien d'autre n'est affecté.

> **RG-47** — **Aucune fonction ne dépend du fond de carte.** Positions,
> géométries, échéances et tournée existent et s'affichent sans fond, sur un
> fond neutre à l'échelle. Le fond aide à se repérer ; il n'est jamais la
> donnée. C'est l'application de §8.6 à la carte : un service tiers
> indisponible dégrade l'affichage, jamais le calcul ni la saisie.

**Les fonctions**

- **Carte du jardin** : plantes en points, haies et rangs en lignes, pelouses,
  massifs et zones en polygones, sur le fond choisi.
- **Placement à la création** : à la saisie de l'inventaire, au bureau, une
  plante se pose sur la carte d'un geste (source `plan`). La création en lot
  (§8.7) le permet aussi : on crée douze pommiers, puis on les pose un par un.
  C'est ce qui rend la carte utile dès le premier jour, sans attendre de
  relevés.
- **Correction des positions** par glisser-déposer. Une position corrigée sur
  la carte prend la source `plan` et remplace le relevé GPS pour l'affichage et
  la proximité (RG-48).
- **Tracé des géométries** linéaires et surfaciques : haies, rangs, planches,
  pelouses, et contour des zones.
- **Vue cartographique des échéances** : « qu'y a-t-il à faire ici ? ». Les
  plantes dues sont signalées sur la carte, avec le même état et la même
  explication que le tableau de bord (RG-06). Un appui sur une plante ouvre sa
  fiche et permet l'action « fait ».
- **Carte sur le terrain** : position du téléphone sur la carte, et plantes dues
  autour de soi. Elle demande le réseau, comme le reste de la V1 ; la mise en
  cache du fond sur le téléphone suivra le hors-ligne (RG-01).
- **Ordre de tournée** : les échéances du jour ordonnées pour un parcours court.
  L'ordre proposé est une suggestion modifiable, jamais une contrainte. À
  500 plantes, une heuristique simple (plus proche voisin depuis la position de
  départ) suffit. Un calcul d'itinéraire optimal n'apporte rien à cette échelle.
- **Étiquettes scannables (QR)** : le QR code encode l'adresse de la fiche par
  son code court. Il complète l'étiquette en clair du MVP, sans la remplacer :
  une étiquette posée la première année reste valable (§3.6).

**Ce qui reste hors V1**

- **Étiquettes NFC** : la lecture NFC depuis le navigateur n'existe que sur une
  partie des téléphones. Le QR couvre le même besoin partout.
- **Recherches spatiales en base** (« les interventions faites à moins de 20 m
  d'ici sur cinq ans ») : rien dans le périmètre ne les demande. À 500 plantes,
  le calcul se fait en mémoire (modèle de données, D5).

> **RG-48** — **Une position a une source, et la source la plus fiable gagne** :
> `plan` (pointée sur la carte) l'emporte sur `manuel` (saisie), qui l'emporte
> sur `gps` (relevé). Un nouveau relevé GPS n'écrase jamais une position
> corrigée sur la carte. Il est conservé avec sa précision (RG-15), mais il
> n'est pas affiché. Sans cette règle, chaque passage sur le terrain défait la
> correction faite au bureau.

### Lot 5 — Météo et automatisations

- Intégration d'une source météo (prévisions + historique local).
- Décalage automatique des échéances : pas de traitement avant pluie, pas de
  taille par gel, tonte décalée en sécheresse.
- Cumul de pluie → besoin d'arrosage.
- Notifications (courriel ou push) : rappel des urgences, bilan hebdomadaire de
  ce qui s'ouvre dans les 7 jours.

> **RG-10** — Un décalage automatique est **toujours visible et réversible**,
> avec son motif. Aucune échéance ne bouge sans que l'utilisateur puisse le
> constater et l'annuler.

---

## 7. Parcours utilisateurs clés

**P0 — Mettre en route un jardin (bureau, le premier jour)**
Le parcours le plus décisif du produit : personne n'arrive avec une page
blanche, tout le monde arrive avec un terrain déjà planté. Un utilisateur qui
doit saisir 150 plantes dans un formulaire une par une abandonne avant la
première échéance.

Inscription → nom du jardin → **profil climatique choisi dans une liste de cinq
entrées** (RG-23 : au MVP il est choisi, jamais déduit d'une commune) →
**création rapide de l'inventaire** :

- déclarer les zones d'abord (5 à 10 lignes, rapide) ;
- puis les plantes, par saisie accélérée : une ligne = un nom + une espèce, la
  bibliothèque faisant le reste ;
- duplication (« 12 pommiers ») et collage d'une liste depuis un tableur ;
- possibilité de **s'arrêter et de reprendre** : l'inventaire n'a pas besoin
  d'être complet pour que l'outil serve. Une plante saisie donne déjà une
  échéance.

> **RG-27** — L'outil doit être **utile avec un inventaire incomplet**. Aucune
> fonction n'exige que le terrain soit intégralement saisi. C'est ce qui permet
> d'étaler la mise en route sur des semaines au lieu de la subir en une fois.

**P1 — Enregistrer sur le terrain (mobile, < 15 s)**
Ouvrir → « Je viens de faire » → choisir le type (les 5 derniers en tête) →
choisir la ou les plantes (proposition par proximité/zone récente) → valider.
Date = maintenant. Tout le reste facultatif.

**P2 — Solder une échéance (mobile)**
Ouvrir → liste du jour → taper « fait » sur la ligne. Une seule confirmation.

**P3 — Préparer un week-end de travaux (bureau)**
Filtrer les échéances ouvertes sur les 15 prochains jours → grouper par zone →
imprimer ou envoyer la liste sur le téléphone.

**P4 — Créer une plante (bureau)**
Nom → type → espèce → la bibliothèque propose les règles → ajuster → zone →
photo.

**P5 — Rattraper une saisie oubliée (bureau)**
« Ajouter une intervention passée » → date au 12/02 → 6 plantes sélectionnées →
type taille → valider. Les échéances de ces 6 plantes se recalculent.

**P6 — Comprendre l'état d'une plante (bureau ou mobile)**
Fiche plante → frise chronologique des interventions, échéances à venir,
échéances manquées, photos dans le temps, récoltes par année.

**P7 — Corriger une règle qui sonne faux**
Depuis l'échéance ou la fiche plante → modifier la périodicité ou la fenêtre →
l'échéance se recalcule immédiatement, la modification est datée.

---

## 8. Exigences transverses

### 8.1 Données

- **Export intégral** dans un format ouvert et relisible sans l'outil (JSON et
  CSV), à tout moment, sans manipulation technique.
- **Sauvegarde** automatique quotidienne, restaurable.
- Aucune donnée n'est perdue par une action d'interface : archivage plutôt que
  suppression, corbeille avec restauration pour ce qui est réellement supprimé.

> **RG-11** — L'historique du jardin est un actif qui prend de la valeur avec
> les années et ne se reconstitue pas. La stratégie de sauvegarde et
> d'exportabilité est une exigence fonctionnelle, pas un détail technique.

### 8.2 Terrain / hors ligne

**En V1 :**

- Application web qui s'ajoute à l'écran d'accueil du téléphone, sans magasin
  d'applications.
- Le terrain demande le réseau. Une saisie qui ne part pas reste affichée et se
  renvoie d'un geste, sans doublon (RG-50).

**Suspendu, à reprendre après la V1 si l'usage le demande (RG-01) :**

- Fonctionnement sans réseau pour la liste du jour et l'enregistrement
  d'interventions.
- Synchronisation automatique au retour du réseau, avec état visible
  (« 3 saisies en attente »).
- Résolution de conflit : en usage mono-utilisateur, la dernière écriture
  gagne ; les saisies hors ligne ne sont jamais écrasées silencieusement.

### 8.3 Photos

- Prise de vue depuis le téléphone, attachée à une plante ou à une intervention.
- Compression avant envoi (contrainte réseau au fond du terrain).
- Consultation chronologique par plante (voir un arbre évoluer sur 5 ans).

### 8.4 Comptes, isolation et données personnelles

Dès qu'il héberge les données de personnes qui ne sont pas son éditeur, le
service porte des obligations qui n'existaient pas pour un outil personnel.
Elles constituent le **lot Ouverture** (§6) ; seule l'isolation est au MVP.

- **Authentification** : compte par courriel et mot de passe, réinitialisation
  *(lot Ouverture)*.
- **Isolation** : RG-21 et RG-22. À vérifier par des tests automatisés dédiés,
  pas seulement par relecture — c'est la faute la plus grave possible du
  produit.
- **Auteur** : chaque intervention porte son auteur, dès le MVP.
- **Données personnelles collectées** : courriel, et localisation du jardin.
  Rien d'autre n'est nécessaire — ne pas collecter au-delà.
- **Droits RGPD** *(lot Ouverture)* : export intégral (§8.1) et **suppression
  effective** du compte, du jardin et des photos associées, sur demande de
  l'utilisateur et sans intervention manuelle de l'éditeur.
- **Photos** : c'est le poste de stockage dominant du service. Compression
  systématique, quota par jardin, et purge réelle à la suppression.

> **RG-28** — La localisation d'un jardin est une donnée personnelle
> sensible : elle désigne le domicile de l'utilisateur. Elle n'est jamais
> exposée hors de son jardin, ni utilisée à d'autres fins que le calage
> climatique et la météo (lot 5).

### 8.5 Accessibilité et confort

- Interface en français.
- Lisible en plein soleil (contraste élevé, thème clair par défaut).
- Utilisable avec des gants : cibles tactiles ≥ 48 px sur la vue terrain.

### 8.6 Hébergement et sobriété technique

Contrainte actée (§1.4) : le service tourne sur un **serveur Linux ordinaire**.
Ce n'est pas un choix de stack, c'est une borne fonctionnelle — elle interdit
de concevoir des fonctions qui supposeraient une infrastructure lourde ou un
service externe facturé.

Conséquences directes sur le périmètre :

- **Pas de dépendance externe obligatoire** au fonctionnement nominal. Le
  géocodage (§5.4), la météo (lot 5) et le fond de carte aérien (lot 4,
  RG-47) sont des agréments, jamais des prérequis : l'outil doit calculer toutes
  ses échéances hors ligne du reste du monde.
- **Photos stockées sur disque**, compressées à l'envoi, avec un quota par
  jardin. C'est le seul poste dont le volume croît sans limite naturelle, et
  celui qui remplira le disque en premier.
- **Base de données relationnelle standard**, sans extension exotique. Les
  volumes (§8.7) sont modestes ; la difficulté est ergonomique, pas technique.
- **Sauvegarde par copie de fichiers**, restaurable à la main. Une procédure de
  restauration qui exige un service tiers n'est pas une sauvegarde.

### 8.7 Dimensionnement

Le volume du catalogue est proportionnel à la surface du terrain, et l'écart
entre les deux extrêmes est d'un facteur 10. Il faut concevoir pour le haut de
la fourchette, sinon l'outil est confortable en démonstration et inutilisable
sur le terrain réel.

| Contexte | Plantes | Règles par plante | Échéances actives |
|---|---|---|---|
| Petit jardin de ville | ~30 | 1 à 2 | quelques dizaines |
| Jardin moyen | ~100 | 1 à 2 | ~150 |
| **Grand terrain (jardin pilote, cas de référence)** | **plusieurs centaines** | **1 à 3** | **plusieurs centaines** |

Le service étant multi-jardins, ces volumes se multiplient par le nombre de
jardins hébergés — sans incidence sur l'ergonomie, qui reste bornée au jardin
actif, mais avec une incidence directe sur le stockage des photos (§8.4 et §8.6).

Cible de conception : **500 plantes, 1 500 règles (500 × 3, le haut de la
fourchette du tableau ci-dessus), 10 000 interventions historiques après
10 ans.** Ce sont de petits volumes pour une base de données,
mais de très gros volumes pour une interface.

Conséquences fonctionnelles, toutes obligatoires au MVP :

- **Import et création en lot** : saisir 300 plantes une par une dans un
  formulaire tue le projet avant la première échéance. Il faut la duplication
  (« créer 12 pommiers d'un coup »), l'import depuis une liste collée, et
  l'édition en masse.
- **Aucune liste brute** : ni le tableau de bord, ni le sélecteur terrain, ni
  l'inventaire ne présentent une liste non filtrée de plusieurs centaines de
  lignes. Recherche, filtres par zone et par état, regroupements.
- **Sélecteur terrain hiérarchisé** : proximité, puis zone courante, puis
  recherche par nom ou code court. Jamais un défilement de 300 entrées.
- **Actions groupées** : « traiter tous les fruitiers du verger haut » doit être
  une saisie, pas six.
- **Agrégation au tableau de bord** : « 14 tailles dues au verger haut »
  dépliable, plutôt que 14 lignes noyant le reste.

> **RG-19** — Toute vue susceptible d'afficher des plantes doit rester
> utilisable à **500 plantes**. C'est un critère d'acceptation, pas une
> optimisation ultérieure : l'ergonomie qui marche à 30 ne se rattrape pas à
> 300 sans refonte des écrans.

### 8.8 Réalisation, vérification et livraison

Le produit étant écrit par des agents (§1.5), la manière de le construire est
une exigence au même titre que ses fonctions. Le **chapitre technique 6**
(`technique-06-environnement-et-livraison.md`) en porte la conception
détaillée. Ce paragraphe fixe ce qui ne se négocie pas.

- **Le dépôt est la consigne.** Conventions, règles de travail, commandes et
  décisions vivent dans le dépôt, versionnées, lisibles par tout agent qui
  l'ouvre. Aucune règle ne dépend de la mémoire d'une conversation.
- **Une commande unique vérifie tout**, la même sur le poste et en intégration
  continue. Un agent ne peut pas déclarer un travail terminé sans l'avoir lancée
  avec succès.
- **Tout changement passe par une demande de fusion** dont l'intégration
  continue est verte. Personne, agent ou humain, ne pousse directement sur la
  branche principale.
- **Les critères d'acceptation sont des tests.** Chaque critère du §10 a un test
  automatisé qui porte son numéro. Un critère sans test n'est pas tenu (RG-46).
- **Les tests sont eux-mêmes contrôlés.** Un agent peut écrire un test qui passe
  sans rien vérifier. Le moteur d'échéances est donc soumis à des tests de
  mutation : on altère le code exprès, et un test doit échouer à chaque fois.
- **La mise en production est un acte de Léon.** L'intégration continue prépare
  et vérifie ; la production ne reçoit une version que sur son déclenchement
  explicite.
- **Sobriété de l'intégration continue.** Les minutes de calcul sont un coût
  réel. La vérification se fait d'abord sur le poste, et l'intégration continue
  ne relance que ce qui est concerné par le changement.

> **RG-49** — **Modifier un test pour le faire passer est une décision**, pas une
> correction. Un agent qui change l'attendu d'un test existant le signale dans
> la demande de fusion, avec la règle de gestion qui le justifie. Sans cette
> règle, le moyen le plus court de rendre une suite verte est de lui faire dire
> autre chose.

---

## 9. Modèle de données conceptuel

Pour dimensionner la conception technique. Entités et cardinalités.

```
Utilisateur ──1..n──> Jardin           (propriétaire)
Utilisateur ──n..n──> Jardin           (membre, via Adhesion + rôle)

Jardin ──n..1──> ProfilClimatique      (décalage en jours ; table de référence à 5 lignes)
Jardin ──1..n──> ToutObjetMetier       (RG-22 : identifiant de jardin porté partout)

Jardin ──1..n──> Zone                  (plates au MVP ; sous-zones au lot 3)
Zone ──1..n──> Plante

Plante ──n..0..1──> Espece         (référence bibliothèque)
Plante ──0..1──> Plante            (plante d'origine, en cas de scission — RG-12)
Plante ──0..1──> Geometrie         (point | ligne | polygone + précision + source)
Plante ──0..1──> Photo             (photo de couverture — RG-35)
Plante ──0..n──> Regle             (zéro tant que l'utilisateur n'en a accepté aucune)
Plante ──0..n──> Intervention      (zéro à la création)
Plante ──0..n──> Photo

TypeIntervention ──1..n──> Intervention
TypeIntervention ──1..n──> RegleReference

Espece ──1..n──> RegleReference    (bibliothèque)
RegleReference ──copiée vers──> Regle

Regle ──1..n──> Fenetre                 (R2 — plusieurs possibles, RG-36 ; butoirs RG-44)
Regle ──0..n──> IntervalleSaisonnier    (R1 modulé)
Regle ──0..n──> Echeance                (calculées ; matérialisées seulement à la demande)
Echeance ──0..1──> Intervention         (celle qui l'a soldée)

Intervention ──0..n──> Photo
Intervention ──0..1──> Recolte (quantité, unité)  (lot 1)
Intervention ──n..1──> Utilisateur      (auteur)
```

Points d'attention structurants pour la technique :

1. **Les échéances sont-elles calculées à la volée ou stockées ?** Il faut
   pouvoir consulter l'historique des échéances manquées et suspendues
   (RG-07) : elles doivent donc être **matérialisées** au moins dès qu'elles
   changent d'état ou reçoivent une action. Modèle recommandé : calcul à la
   volée pour les échéances futures, matérialisation dès la première action ou
   au passage en `manquée`.

2. **Le moteur de règles doit être isolé et testable** hors interface : il
   prend (règles, historique, date du jour, profil climatique) et rend une liste
   d'échéances. C'est le composant qui portera le plus de bugs subtils et le
   seul qui mérite une couverture de tests sérieuse dès le MVP.

3. **Versionnement des règles** : modifier une règle ne doit pas réécrire
   l'histoire des échéances passées.

4. **Fenêtres à cheval sur l'année** (01/12 → 31/03) : source d'erreurs
   classique, à traiter explicitement dans le modèle et les tests.

5. **Fuseau et dates** : tout est en date locale sans heure pour les
   interventions et les fenêtres ; l'heure n'a pas de sens ici et introduit des
   décalages d'un jour.

5 bis. **Identité d'une échéance non matérialisée** : la plupart des échéances
   n'existent jamais en base (point 1). Elles doivent pourtant être désignables —
   par la vue terrain, qui les affiche et les solde. Leur identité est
   le couple **(règle, date due)**, déterministe et reproductible d'un calcul à
   l'autre, jamais un identifiant tiré au hasard à la génération.

6. **Géométrie** : le type retenu doit accepter point, ligne et polygone dès la
   première migration (RG-14), même si seul le point est alimenté au MVP.
   Stocker un couple `lat/lon` nu obligerait à tout reprendre en V1. La
   précision et la source du relevé sont des colonnes à part entière : une
   position à 12 m ne se corrige pas comme une position pointée sur un plan.

7. **Code court** : identifiant lisible, stable à vie, jamais réattribué même
   après archivage d'une plante — des étiquettes physiques posées sur le terrain
   en dépendent. Unique **par jardin**, pas globalement : deux jardins peuvent
   avoir chacun leur `FRU-014`.

8. **Cloisonnement** : l'identifiant de jardin est porté par toutes les tables
   métier dès la première migration (RG-22) et le filtrage est **imposé par
   défaut**, pas laissé à la discipline de chaque requête. C'est la décision
   d'architecture la plus coûteuse à rattraper et la plus grave à manquer.

9. **Calage climatique** : le décalage s'applique **au calcul de l'échéance**,
   pas au stockage de la règle. Les règles restent écrites dans le climat de
   référence ; sinon corriger le profil climatique d'un jardin obligerait à
   réécrire toutes ses règles.

---

## 10. Critères d'acceptation

Chaque critère est vérifié par un test automatisé qui porte son numéro (§8.8).
Les numéros sont stables, comme ceux des règles de gestion.

### 10.1 Lot 0 — journal et échéances

La V1 n'est livrée que si les assertions suivantes sont vraies, en plus de
celles du §10.2 :

1. Je peux saisir 150 plantes réparties en 6 zones en moins d'une heure, en
   m'appuyant sur la duplication et l'import en lot.
2. Depuis le téléphone, j'enregistre « taille du pommier du fond » en moins de
   15 secondes et 3 gestes. Si le réseau manque à l'envoi, la saisie reste
   affichée comme non envoyée, et son renvoi crée une seule intervention (RG-50).
3. Le tableau de bord affiche, à toute date, la liste des interventions dues,
   groupées par zone, chacune avec une phrase d'explication de son échéance.
4. Une taille de haie n'est jamais proposée entre le 16 mars et le 15 août,
   **et ce pour chacun des cinq profils climatiques**. La bibliothèque livre des
   fenêtres comprises dans 16/08 → 15/03 qui exclut d'emblée la nidification, et ses butoirs
   (RG-44) empêchent le décalage climatique de la faire déborder de part ou
   d'autre. C'est le décalage, pas la fenêtre, qui fait échouer ce critère quand
   les butoirs manquent.
5. La tonte proposée en avril l'est à ~7 jours, celle proposée en octobre à
   ~10 jours, sans que j'aie eu à créer deux règles.
6. Une échéance de taille de pommier non soldée au 31 mars apparaît en
   `manquée` dans l'historique de la plante, et la suivante est proposée pour
   l'hiver d'après.
7. Je saisis le lundi une intervention datée du samedi et toutes les échéances
   dépendantes se recalculent.
8. J'exporte l'intégralité de mes données en un clic et le fichier est
   compréhensible sans l'outil.
9. Une plante créée depuis le terrain porte une position enregistrée sans que
   j'aie fait un geste de plus, et le sélecteur de plantes de la vue terrain
   propose en tête celles qui sont autour de moi.
10. J'imprime les étiquettes des codes courts de toutes les plantes d'une zone.
11. Le tableau de bord et le sélecteur terrain restent utilisables avec
    500 plantes au catalogue : aucune liste de plus d'un écran sans recherche
    ni filtre.
12. Je crée un second jardin en base : aucune donnée du premier n'y est
    visible, sous aucun écran, aucun filtre et aucune URL devinée. Le test
    vaut sans inscription publique — c'est le cloisonnement qui est vérifié,
    pas le parcours de compte.
13. Deux jardins déclarés l'un en « Nord, Est, océanique frais », l'autre en
    « Midi », créés avec les mêmes espèces, produisent des fenêtres de taille
    décalées de 22 jours, et chaque échéance mentionne le décalage appliqué.
14. Chaque plante affiche une photo de couverture dans les listes, et je peux
    joindre une photo à une intervention depuis le terrain sans quitter le
    parcours à trois gestes.
15. La tonte n'est jamais proposée en janvier, quelle que soit la date de la
    dernière tonte enregistrée à l'automne : hors saison, la prochaine échéance
    est reportée à l'ouverture de la saison suivante, pas laissée en retard tout
    l'hiver.

### 10.2 Lot 4 — plan du terrain

La V1 n'est livrée que si les critères 1 à 15 sont vrais et que les suivants le
sont aussi :

16. J'importe un plan dessiné, je le cale avec trois points, et les plantes déjà
    relevées au GPS apparaissent dessus à l'endroit attendu, à la précision du
    relevé près.
17. Je déplace un pommier sur la carte. Sa position passe en source `plan`. Une
    intervention saisie ensuite à côté de lui, au téléphone, ne le déplace pas
    (RG-48).
18. Je trace une haie en ligne et une pelouse en polygone. Les deux reçoivent
    leurs échéances comme n'importe quelle plante.
19. Sur le terrain, la carte du jardin s'affiche avec son fond, ma position et
    les plantes dues aujourd'hui.
20. Le service d'orthophotographie est coupé : la carte s'affiche sur l'image
    calée ou sur un fond neutre, et aucune échéance, aucune saisie ni aucun
    ordre de tournée n'est affecté (RG-47).
21. Les échéances du jour sont proposées dans un ordre de tournée. Je peux le
    modifier, et l'action « fait » reste à trois gestes.
22. Je scanne le QR code d'une étiquette avec l'appareil photo du téléphone, et
    la fiche de la plante s'ouvre. Ce QR code ne donne accès à rien hors du
    jardin de la personne connectée (RG-21).
23. La carte reste fluide à 500 plantes, dont des lignes et des polygones
    (RG-19).
24. Je crée 12 pommiers en lot, puis je les pose un par un sur la carte au
    bureau : chacun porte une position de source `plan`, sans aucun relevé GPS
    préalable.

---

## 11. Risques et questions ouvertes

| # | Question | Risque | Piste |
|---|---|---|---|
| Q1 | Qualité des règles initiales | Des périodicités fausses au départ décrédibilisent l'outil dès la première saison | Bibliothèque sourcée, faible en nombre d'espèces mais juste ; correction facile (RG-08) |
| Q2 | Charge de saisie | L'outil meurt si enregistrer coûte plus que ce qu'il rapporte | Parcours P1 chronométré comme critère d'acceptation |
| Q3 | Volume de l'inventaire | — | **Tranchée le 23/08/2026** — cible 500 plantes, §8.7 et RG-19 |
| Q4 | Granularité des plantes | — | **Tranchée le 23/08/2026** — RG-12 et son tableau d'application |
| Q5 | Météo | Dépendance à un service externe, fiabilité locale sur un grand terrain | Lot 5, optionnel, jamais bloquant |
| Q6 | Traçabilité réglementaire | Usage strictement privé — pas d'obligation de registre phytosanitaire | Confirmer que le lot 2 reste un confort et non une contrainte légale |
| Q7 | Multi-utilisateur | — | **Tranchée le 23/08/2026** — produit multi-jardins, §1.2 |
| Q8 | Précision GPS sous couvert | Un relevé à 10 m sous les arbres rend le tri par proximité moins tranchant dans un verger dense | Le tri propose N candidats et ne présélectionne jamais seul ; l'étiquette reste la réponse de référence |
| Q9 | Modèle économique | — | **Écartée le 23/08/2026** — pas de modèle économique pour l'instant. Un quota photo technique reste posé pour protéger le disque, sans logique de facturation |
| Q10 | Hébergement | — | **Tranchée le 23/08/2026** — serveur Linux ordinaire, sans service managé ni base commerciale, §1.4 et §8.6 |
| Q11 | Microclimats | Un décalage global par jardin ignore le versant nord et le fond de vallée gélif | Limite assumée (§5.4) ; correction manuelle par plante, météo réelle au lot 5 |
| Q12 | Couverture géographique | La bibliothèque et les décalages sont pensés pour la France métropolitaine | **Ouverte** — hors métropole, l'hémisphère et les saisons inversent le modèle ; à exclure explicitement ou à traiter |
| Q13 | Support et curation | Qui maintient la bibliothèque partagée, à quelle fréquence, sur quelles sources ? | **Ouverte** — la qualité du produit en dépend directement (§5.1) |
| Q14 | Courriel transactionnel | Sans remise fiable, un utilisateur qui perd son mot de passe perd son jardin. Depuis un serveur ordinaire, la remise chez les grands fournisseurs est difficile (SPF, DKIM, DMARC, réputation d'IP) | **Ouverte** — sans objet au MVP (compte unique, pas de courriel), bloquante au lot Ouverture. La solution fiable est un service tiers d'envoi, à assumer face à §8.6 |
| Q15 | Exploitation en service ouvert | Sauvegardes testées, mises à jour de sécurité, surveillance : un outil personnel qui tombe coûte un week-end, un service ouvert coûte des utilisateurs | **Ouverte** — à trancher avant d'ouvrir, pas avant de coder |
| Q16 | Dépôt public ou privé | Un dépôt public reçoit des minutes d'intégration continue gratuites, mais expose le code et l'historique des décisions | **Tranchée le 19/10/2026** — public pendant le développement, privé ensuite. Ce qui a été public le reste (chapitre 6, §2) |
| Q17 | Serveur de production | Le CDC exige un serveur Linux ordinaire, mais ne dit pas lequel. Le déploiement automatique doit savoir où il va | **Ouverte** — à trancher avant le premier déploiement, pas avant de coder |
| Q18 | Conditions d'usage des orthophotos IGN | Licence, attribution à afficher, limite de débit éventuelle : non relues à ce jour, seul l'accès sans clé a été vérifié | **Ouverte** — à lire avant de livrer la V1 ; le fond reste un agrément (RG-47) |
| Q19 | Nature de la nidification pour une haie de jardin | Les sources la donnent comme une recommandation pour un particulier, une obligation pour un exploitant agricole. Le produit l'applique comme un butoir que rien ne franchit (RG-44) : dire « interdit » à un particulier est inexact, dire « déconseillé » affaiblit la règle | **Ouverte** — garder un butoir dur et formuler l'explication en « période de nidification, taille déconseillée », ou laisser l'utilisateur lever le butoir sur sa plante |
| Q20 | Pêcher et B-T1 | B-T1 interdit à tout fruitier à noyau une fenêtre de taille qui recouvre décembre → février ; toutes les sources taillent le pêcher en février–mars. La bibliothèque ne se publie pas tant que les deux coexistent | **Ouverte** — exception nominative au pêcher dans B-T1, ou B-T1 restreint au cerisier et au prunier |
| Q21 | Framboisier remontant et non remontant | Deux tailles différentes ; B-T7 interdit deux règles du même type sur une espèce | **Tranchée le 20/10/2026** — une espèce, deux variantes de conduite choisies à la création de la plante (RG-51) |
| Q22 | Périodicité de la haie caduque | Une haie taillée de jardin se reprend chaque année (non sourcé), une haie bocagère tous les 2 à 3 ans (sourcé) | **Ouverte** — 1 an par défaut, ou 2 ans |
| Q23 | Traitements fruitiers | Écrits en R3 (hors MVP) et non sourcés, alors que le plan les attend dans la bibliothèque du sprint 4b | **Tranchée le 20/10/2026** — retirés de la liste de départ |

---

## 12. Journal des décisions

Décisions actées, pour ne pas les rouvrir sans raison nouvelle.

| Date | Décision | Motif | Trace |
|---|---|---|---|
| 23/08/2026 | Site web responsive, gestion au bureau, saisie sur le terrain au téléphone | Deux contextes d'usage réels et opposés | §2 |
| 23/08/2026 | MVP = journal + échéances, lots suivants décrits dès maintenant | Utile dès la première saison, sans piéger le modèle de données | §6 |
| 23/08/2026 | Cinq types de règles, pas un intervalle unique | Un intervalle fixe donne des échéances fausses pour la taille et les traitements | §4.1 |
| 23/08/2026 | Un rang de framboisiers = 1 plante | Traité d'un bloc ; règle générale posée en RG-12 | §3.3 |
| 23/08/2026 | Le nom identifie, la coordonnée situe ; géométrie point/ligne/polygone ; capture GPS dès le MVP, exploitation au lot 4 | Le GPS téléphone ne discrimine pas deux arbres voisins, mais capturer tard coûte une campagne de relevé | §3.7, RG-13 à RG-15 |
| 23/08/2026 | **La plante est l'entité centrale** ; le terme « sujet » est abandonné | Le mot « sujet » se lisait comme un événement ; la plante porte l'identité, l'histoire et la valeur | §3.2, RG-16 |
| 23/08/2026 | Une culture annuelle est une plante temporaire rattachée à une zone, pas une entité distincte | L'historique des plantes d'une zone donne la rotation sans modèle supplémentaire | RG-18 |
| 23/08/2026 | Dimensionner pour 500 plantes | Le volume suit la surface du terrain ; l'ergonomie qui marche à 30 ne se rattrape pas à 300 | §8.7, RG-19 |
| 23/08/2026 | **Produit multi-jardins**, pas outil personnel ; le terrain de Léon devient le jardin pilote | Le besoin n'est pas propre à un terrain | §1.2, RG-20 à RG-23 |
| 23/08/2026 | Bibliothèque partagée en lecture seule, versionnée, jamais écrite par les utilisateurs | Une règle fausse contaminerait tous les jardins | §5.2, RG-24 |
| 23/08/2026 | Calage climatique par décalage en jours appliqué au calcul, pas par bibliothèques régionales | 3 semaines d'écart Bretagne/Midi, 4 en montagne ; un décalage se corrige, des bibliothèques parallèles se contredisent | §5.4, RG-25, RG-26 |
| 23/08/2026 | ~~Le MVP inclut comptes, isolation et RGPD~~ ; le partage d'un jardin est repoussé au lot 1 bis | Le cloisonnement ne se greffe pas après coup, le partage si. **Première moitié abrogée le jour même** par la décision « socle produit allégé » ci-dessous : seule l'isolation est au MVP | §6, §8.4, RG-41 |
| 23/08/2026 | **Le potager est hors MVP** ; pelouse et massifs y restent, comme plantes | Domaine le plus complexe et le seul au comportement différent ; l'entretien des surfaces, lui, est nécessaire dès le premier jour | §6, RG-32 |
| 23/08/2026 | Application web ; hébergement sur serveur Linux ordinaire, sans service managé ni base commerciale | Borne fonctionnelle : aucune fonction ne peut dépendre d'une infrastructure lourde ou d'un service externe facturé | §1.4, §8.6 |
| 23/08/2026 | **Profil climatique choisi dans une liste de 5 entrées au MVP**, géocodage automatique reporté | La complexité était dans la déduction, pas dans le décalage ; le moteur doit savoir décaler dès le départ, le remplissage peut attendre | §5.4, RG-29 |
| 23/08/2026 | Pas de modèle économique pour l'instant | Décision de Léon ; quota photo conservé comme protection technique | Q9 |
| 23/08/2026 | Bibliothèque largement remplie, avec niveau de confiance sur les règles opérationnelles | Le descriptif est inoffensif et utile à la saisie ; une périodicité inventée produit des échéances fausses sans que rien ne proteste | §5.3, RG-30, RG-31 |
| 23/08/2026 | **Zone et parcelle fusionnées** | Deux objets quasi homonymes pour une seule réalité ; « verger haut » était modélisable des deux façons | ~~RG-33~~, abrogée le jour même |
| 23/08/2026 | **La zone redevient un pur classement** : Jardin > Zone > Plante. La plante est la seule entité qui reçoit des interventions | Une pelouse est un groupe végétal traité comme une unité — donc une plante. Plus de supertype, plus d'abstraction à porter | §3.2, RG-34 (abroge RG-17 et RG-33) |
| 23/08/2026 | **Les équipements sortent du modèle** | L'outil trace le vivant ; les faire entrer coûtait une abstraction pour un cas d'usage marginal | §3.5 |
| 23/08/2026 | **Le MVP se limite à R1 et R2** ; R3, R4, R5 et le mécanisme de contrainte sont reportés | Une fenêtre bien écrite remplace une contrainte saisonnière ; R2 à fenêtres multiples remplace R3 dans les cas courants | §4.2, RG-36, RG-37 |
| 23/08/2026 | **Les observations sortent du MVP** | Elles n'existent que pour alimenter R4, elle-même reportée | §6 |
| 23/08/2026 | **Zones plates au MVP** | La hiérarchie n'est nécessaire qu'avec le potager, et coûte des requêtes récursives partout | §3.3 |
| 23/08/2026 | Photo de couverture par plante, photos d'intervention comme preuve | Reconnaître un arbre à sa photo est plus rapide que lire son nom ; la photo d'intervention est la seule trace qui ne dépend pas de la mémoire | RG-35, RG-38 |
| 23/08/2026 | **Socle produit allégé** : le MVP garde le cloisonnement, pas les fonctions de compte ni le RGPD | Le `jardin_id` ne se greffe pas après coup et est quasi gratuit ; l'inscription et le RGPD s'ajoutent sans rien casser, le jour où un deuxième utilisateur existe | §6 lot O, RG-41, RG-42 |
| 23/08/2026 | Une règle est définie par la bibliothèque, **copiée** dans le jardin, possédée par l'utilisateur ; une règle active produit exactement une échéance à venir | Sans la copie, corriger la bibliothèque déplacerait les échéances de tous les jardins | §3.9, RG-39, RG-40 |
| 24/08/2026 | **RG-20, RG-21 et RG-06 sont écrites.** Elles étaient citées douze fois — dont le cloisonnement, en §5.2, §6, §8.4 et §9 — sans avoir jamais été énoncées | Une règle citée et non définie se lit comme chacun la comprend ; RG-20 était d'ailleurs invoquée avec deux sens différents. RG-43 recueille le second (jardin courant visible) | §2.1, §3.1, §4.3 |
| 24/08/2026 | **Les fenêtres portent des butoirs** que le décalage climatique ne franchit jamais (RG-44, RG-45) | RG-37 et RG-25 étaient justes séparément et fausses ensemble : à +21 j la fenêtre de haie débordait sur avril, à −15 j sur juillet. Une interdiction n'est pas une approximation | §4.2, critère 4 |
| 24/08/2026 | **Le décalage climatique s'applique aussi aux bornes de saison d'un intervalle R1**, jamais à la valeur de l'intervalle | RG-25 se justifiait par « la saison de tonte commence plus tôt dans le Midi » — mécanisme qui n'existait nulle part : les bornes de saison restaient dans le climat de référence | §5.4, RG-25 |
| 24/08/2026 | **Hors saison, une échéance R1 est reportée à l'ouverture de la saison suivante** | L'algorithme lisait l'intervalle du mois de la *dernière* occurrence : une tonte de novembre restait due tout l'hiver, et « intervalle 0 ⇒ aucune échéance » contredisait RG-40 | Critère 15, chap. 3 §7 |
| 24/08/2026 | **La liste des espèces de départ est ramenée à ce qui est sourcé** ; pêcher, vigne, framboisier, groseillier et l'arbuste à floraison printanière restent à sourcer | Cinq des quatorze espèces annoncées n'avaient aucune règle en annexe A. Les inventer aurait violé B4 ; les annoncer sans règle fait échouer B-T10 | §5.1, annexe A |
| 23/08/2026 | **Le supertype `OBJET` n'est pas réintroduit**, ni au MVP ni au lot 3 | Deux clés étrangères nullables et exclusives suffiront quand une zone devra recevoir des interventions ; un supertype ne se justifie qu'avec plusieurs natures partageant beaucoup de champs | Modèle de données, D7 |
| 19/10/2026 | **Le produit est réalisé par des agents IA**, pilotés et relus par Léon | Décision de Léon. Le coût passe de l'écriture à la vérification | §1.5, RG-46 |
| 19/10/2026 | **Revue des reports à la lumière du §1.5.** Levés : le plan du terrain (lot 4, vers la V1) et la déduction du profil climatique (vers le lot O). Maintenus, parce que leur motif n'était pas le coût de développement : R3 et R5 (une fenêtre bien écrite suffit, RG-37), R4 et les observations (charge de saisie la première année), les sous-zones (aucun besoin avant le potager), le potager (son calendrier de semis est à sourcer espèce par espèce, ce qu'aucun agent ne fait à la place d'une source), le lot O lui-même (déclenché par un deuxième utilisateur), la météo (dépendance externe) | Un report qui n'avait pour raison que la difficulté n'a plus lieu d'être ; un report qui avait une raison produit la garde | §6 |
| 19/10/2026 | ~~Jalon V1 = MVP + lot 4, la carte n'entre pas au MVP~~ — **abrogée le jour même** par la ligne suivante | Le motif (carte vide avant une saison de relevés) supposait une carte livrée après l'inventaire | §6 |
| 19/10/2026 | **Pas de MVP livré à part : la première version est la V1 = lot 0 + lot 4.** La carte sert à faire l'inventaire (placement à la création). Le moteur, le journal et la vue terrain sont construits avant la carte | Décision de Léon : avec une réalisation par agents, le lot 0 seul ne justifie pas une livraison. L'ordre interne garde le cœur utilisable pour la taille d'hiver si la carte prend du retard | §6, §10, critère 24 |
| 19/10/2026 | **Dépôt public pendant le développement, privé ensuite** | Décision de Léon. Minutes d'intégration continue gratuites ; tout ce qui a été public le reste, d'où l'interdiction absolue de secrets et de données réelles dans le dépôt | Q16, chapitre 6 §2 |
| 19/10/2026 | **Deux fonds de plan** : image calée (toujours disponible) et orthophotos IGN (agrément). Aucune fonction ne dépend du fond | Le fond IGN est gratuit et sans clé, mais reste un service tiers : sa panne ne doit dégrader que l'affichage | §6 lot 4, RG-47, Q18 |
| 19/10/2026 | **La position la plus fiable gagne** : `plan` > `manuel` > `gps` | Sans cette règle, chaque relevé GPS sur le terrain défait la correction faite au bureau | RG-48 |
| 19/10/2026 | **NFC hors V1**, QR en V1 | La lecture NFC depuis le navigateur n'existe que sur une partie des téléphones ; le QR couvre le même besoin partout | §6 lot 4 |
| 19/10/2026 | **Le projet porte la conception de son environnement de code** : dépôt, outillage, tests, intégration et livraison continues. Mise en production déclenchée par Léon seul | Un agent qui ne trouve pas la règle dans le dépôt l'invente ; une production sans geste humain n'a plus de relecteur | §8.8, RG-49, chapitre 6 |
| 19/10/2026 | **Chaque critère d'acceptation a un test qui porte son numéro** | C'est ce qui rend vérifiable un code qu'on n'a pas écrit | §10, RG-46 |
| 19/10/2026 | **Le produit est conçu pour être commercialisé ; la complexité se mesure à deux questions** : besoin actuel ou prévu, coût d'un ajout tardif | Décision de Léon : un projet professionnel, ni surcompliqué ni simplifié pour la forme. Une revue du dossier sur ce critère ne retire que le hors-ligne ; le reste répond à l'une des deux questions | §1.5 |
| 19/10/2026 | **Hors-ligne suspendu pour la V1** (RG-01) ; RG-50 protège la saisie quand le réseau manque | Proposé par Léon. Il faisait exister à lui seul la file d'attente, le paquet terrain, les formats versionnés, un second bundle et une API dédiée, et le cache des tuiles avec la carte. Il s'ajoutera après la V1 sans rien casser : les identifiants sont déjà générés côté client (D2) | RG-01, RG-50, §8.2, critères 2 et 19, chapitres techniques 1, 3, 5 |
| 19/10/2026 | **Les écrans se conçoivent dans FigCraft, en deux étapes** : wireframes validés par Léon, puis interface. Le fichier est construit pour qu'une marque future s'applique en un seul endroit | Décision de Léon : pas encore de marque, et la refonte de marque ne doit pas obliger à reprendre chaque écran | `conception-ecrans.md` |
| 19/10/2026 | **L'agent travaille sans interruption** ; il n'arrête Léon que pour une question d'expérience utilisateur ou un acte qui l'engage. Fusion automatique sur CI verte, production par Léon seul | Décision de Léon : les décisions techniques sont déléguées et écrites, pas soumises | Chapitre 7 (`docs/plan/`), chapitre 6 C24 révisée, C26 |
| 19/10/2026 | **Plus d'arrêt pour l'expérience utilisateur non plus** (précise la ligne précédente). Une question d'UX se tranche par la recommandation de l'agent et s'inscrit au registre `docs/plan/questions-ux.md` ; les wireframes ne sont plus une porte bloquante, ils se revoient après coup. Seuls arrêts : les actes qui engagent Léon (dépôt public, secret ou compte, dépense, production) | Décision de Léon : « fais, on fera des changements après s'il faut ; si le code est bien écrit, ce ne sera pas trop difficile » | Chapitre 7 §3 (P2, P3), chapitre 6 C26, `conception-ecrans.md` §1 |

| 20/10/2026 | **La période de nidification devient 16/03 → 15/08** ; butoirs de haie 16/08 et 15/03 | Le 01/04 → 31/07 de la v0.13 venait de l'arrêté PAC de 2015, périmé. Le sourçage s'appuie sur six textes concordants. RG-37, RG-44, critère 4, B-T2, B-T12, B-T14 et les jeux témoins du plan sont corrigés en conséquence | Annexe A, RG-44, `docs/plan/sourcing-annexe-a.md` |
| 20/10/2026 | **L'annexe A prend les valeurs du sourçage**, avec leur niveau de confiance | Les valeurs de la v0.13 venaient de sources de vulgarisation. La haie persistante passe à une seule fenêtre (la seconde tombait en nidification), le rosier perd « jusqu'en novembre », la pelouse reçoit des intervalles mensuels. Vigne, groseillier à grappes, cassissier et arbuste à floraison printanière entrent dans la liste de départ ; le groseillier à maquereau en est écarté | Annexe A, §5.1 |
| 20/10/2026 | **Les questions que le sourçage ne tranche pas sont posées à Léon**, pas résolues : nature de la nidification, pêcher, framboisier, haie caduque, traitements | Elles engagent le comportement du produit ou une règle de test, pas seulement une valeur | Q19 à Q23 |
| 20/10/2026 | **Une espèce peut porter des variantes de conduite** ; le framboisier en a deux, remontant et non remontant, choisies à la création de la plante | Décision de Léon (Q21). Deux espèces auraient obligé l'utilisateur à savoir que « framboisier remontant » est une espèce à part ; une variante pose la question au moment où elle compte | RG-51, §5.1, annexe A |
| 20/10/2026 | **Les traitements fruitiers sortent de la liste de départ** | Décision de Léon (Q23). Non sourcés, et écrits en R3 hors MVP. L'utilisateur crée les siens ; les types d'intervention restent au catalogue | §5.1, annexe A |
---

## Annexe A — Périodicités de référence

Base de départ pour la bibliothèque, **sourcée le 19/10/2026** selon B1–B4 du
protocole de bibliothèque. Chaque valeur, ses citations, les désaccords entre
sources et l'arbitrage retenu sont dans `docs/plan/sourcing-annexe-a.md` ; ce
tableau n'en est que le résumé. Une valeur `indicative` repose sur des sources
concordantes mais de rang inférieur, ou sur une transposition ; elle s'affiche
comme telle (§5.1).

| Objet | Intervention | Règle | Paramètres | Confiance |
|---|---|---|---|---|
| Pommier, poirier (à pépins) | Taille d'hiver | R2 | Fenêtre 01/12 → 31/03, optimale 01/02 → 31/03, chaque année | vérifiée |
| Cerisier | Taille | R2 | Après récolte, 01/08 → 30/09, chaque année. Pas de taille d'hiver (gommose, chancre) | vérifiée |
| Prunier | Taille | R2 | Mêmes valeurs que le cerisier | indicative |
| Pêcher | Taille de fructification | R2 | 01/02 → 31/03, optimale 01/03 → 31/03, chaque année. **Contredit B-T1** (Q20) | indicative |
| Vigne | Taille d'hiver | R2 | 01/12 → 31/03, optimale 01/02 → 31/03, chaque année | vérifiée |
| Framboisier, variante non remontant (RG-51) | Taille | R2 | 01/02 → 31/03, chaque année | vérifiée |
| Framboisier, variante remontant (récolte d'automne) | Taille | R2 | 01/11 → 28/02, chaque année | vérifiée |
| Groseillier à grappes | Taille | R2 | 01/01 → 15/03, optimale 01/02 → 28/02, chaque année | vérifiée |
| Cassissier | Taille | R2 | 01/11 → 28/02, chaque année | vérifiée |
| Haie persistante | Taille | R2, **une** fenêtre | 16/08 → 30/09, chaque année. La taille de printemps des sources tombe en nidification : elle est retirée | vérifiée |
| Haie caduque | Taille | R2 | 01/11 → 15/03 ; périodicité à trancher (Q22) | indicative |
| Toutes haies | Taille | **Butoirs** (RG-44) | Butoir de début 16/08, butoir de fin 15/03 : la période de nidification 16/03 → 15/08 borne la fenêtre après décalage climatique, sans mécanisme R5 | vérifiée |
| Arbuste à floraison printanière | Taille | R2 (transposition de la règle R4 « après la fanaison ») | 01/04 → 31/07, optimale 01/05 → 30/06 | indicative |
| Arbuste à floraison estivale | Taille | R2 | 01/02 → 31/03, optimale 01/03 → 31/03, chaque année | vérifiée |
| Rosier (buisson remontant) | Taille | R2 | 01/02 → 31/03, chaque année. Le « jusqu'en novembre » de la v0.13 n'est confirmé par aucune source | vérifiée |
| Pelouse (zone) | Tonte | R1 saisonnier | Janvier–février : aucune ; mars–mai : 7 j ; juin–août : 14 j ; septembre–octobre : 7 j ; novembre–décembre : aucune | indicative |
| Fruitiers | Traitement cuivre | R3 | 2 à 3 passages/an : chute des feuilles (~nov), fin d'hiver avant débourrement (~fév), complément printanier si humide. Minimum 12–15 j entre deux. **Non sourcé, hors de la liste de départ** (Q23) | — |
| Fruitiers | Huile blanche | R3 | 2 passages en fin d'hiver, à ~1 mois d'intervalle. **Non sourcé, hors de la liste de départ** (Q23) | — |
| Planche (zone) | Rotation | R5 | Pas de retour de la même famille botanique avant 3 à 4 ans. Lot 3, hors du sourçage | — |

**Correction de la v0.13.** La période de nidification était écrite
01/04 → 31/07 : c'était celle de l'arrêté PAC de 2015, remplacée depuis la PAC
2023-2027 par **16/03 → 15/08** (fiche Telepac BCAE 8 de 2026, instruction
interministérielle du 17/07/2026, communiqué de la préfecture de l'Yonne). Pour
une haie de jardin, ce n'est pas une interdiction légale mais une recommandation,
doublée de l'interdiction permanente de détruire un nid d'espèce protégée
(Q19).

**Sources de la v0.13** (vulgarisation, consultées le 23/08/2026) : remplacées
par celles de `docs/plan/sourcing-annexe-a.md`, qui dit aussi lesquelles ont été
exclues et pourquoi.
