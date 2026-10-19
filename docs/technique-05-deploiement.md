# Document technique — Chapitre 5 : déploiement et exploitation

Version 0.2 — 24/08/2026
Suite de `technique-04-schema.md` (v0.2). Décisions **E1 à E10**.

**Ce chapitre ne traite pas** de comment pousser du PHP sur un serveur, ni de
HTTPS, ni de sauvegarder une base — ce sont des sujets connus et maîtrisés. Il
traite des **contraintes d'exploitation que cette architecture a créées**, et
qui ne sont écrites nulle part ailleurs.

Il y en a quatre — service worker, worker de file, mise à jour d'un client hors
ligne, migrations — et **une seule ne se rattrape pas après coup** : le
versionnement du format de mutation (§3, E5). Les trois autres se corrigent le
jour où on les rencontre.

---

## 1. Le service worker impose HTTPS, y compris pour tester

Un service worker ne s'enregistre pas hors contexte sécurisé. Sans lui : pas de
cache, pas de file d'attente, **pas de hors-ligne** — donc rien de ce que décrit
le chapitre 1.

| Contexte | Le service worker fonctionne-t-il ? |
|---|---|
| `https://…` en production | oui |
| `http://localhost` en développement | **oui** — les navigateurs traitent `localhost` comme un contexte sécurisé |
| `http://192.168.1.x` depuis un téléphone sur le réseau local | **non** |

C'est la troisième ligne qui pose problème, et elle est centrale : **le mode
terrain doit être testé sur un vrai téléphone**, dehors, avec le réseau qui
tombe. Un émulateur de navigateur ne reproduit ni le GPS réel, ni la 4G qui
faiblit, ni la prise de photo, ni les gants.

> **E1** — L'environnement de développement doit servir en **HTTPS accessible
> depuis un téléphone**, pas seulement depuis `localhost`. Deux voies : un
> certificat local dont l'autorité est installée sur le téléphone, ou un tunnel
> qui expose le poste en HTTPS public.
>
> **Toujours nécessaire en V1, sans hors-ligne :** la géolocalisation et
> l'accès à la caméra pour lire un QR demandent eux aussi un contexte sécurisé.
>
> *Non vérifié à ce jour :* `ddev` sait générer des certificats locaux et propose
> une commande de partage par tunnel. Laquelle des deux voies est la moins
> pénible reste à tester sur ce poste avant de s'y engager.

---

## 2. Un worker doit tourner en permanence

Le chapitre 3 a introduit des jobs (A19 : génération des miniatures) sans dire
comment ils s'exécutent. C'est une dépendance d'exploitation nouvelle.

```
php artisan queue:work --tries=3 --timeout=120
```

supervisé par systemd ou supervisord, avec redémarrage automatique.

**Trois pièges, dans l'ordre où on les rencontre :**

> **E2** — Après chaque déploiement, `php artisan queue:restart`. Les workers
> gardent le code en mémoire : sans redémarrage, ils exécutent l'**ancienne
> version** indéfiniment. C'est le piège Laravel le plus classique, et il produit
> des symptômes incompréhensibles — un correctif déployé qui ne s'applique pas.

> **E3** — Le job porte son `jardin_id` et repositionne `JardinCourant` (A20).
> Un worker n'a ni session ni requête HTTP. Un job qui l'oublie écrit dans le
> vide, ou lit ce qu'il ne devrait pas voir.

> **E4** — **Une panne de worker est silencieuse.** Si le processus meurt, les
> photos continuent d'être acceptées, les interventions s'enregistrent, rien ne
> proteste — mais aucune miniature n'est produite, et le paquet terrain se
> dégrade lentement. La supervision minimale est une requête :
>
> ```sql
> SELECT count(*) FROM photos
> WHERE derivees_pretes = false AND televersee_le < now() - interval '1 hour';
> ```
>
> Au-delà de zéro, quelque chose ne tourne plus.

---

## 3. La mise à jour d'une application qui vit hors ligne

**C'est le point le plus subtil du chapitre, et le seul qui ne se rattrape pas
après coup.**

Quelqu'un qui n'ouvre l'application qu'au jardin peut rester deux mois sur une
version ancienne — avec, dans sa file d'attente, des mutations **au format de
cette version**. Si le format a changé entre-temps, elles sont rejetées à la
première remontée. Et H6 dit que la file **s'arrête à la première erreur
bloquante** : une seule mutation obsolète gèle tout ce qui la suit.

> **Suspendu pour la V1 — 19/10/2026** (CDC v0.13). Sans file d'attente sur
> le téléphone, il n'y a pas de mutation qui vieillisse : E5 à E8 et la mise à
> jour du service worker reviennent avec le hors-ligne. E9 aussi : en V1, une
> saisie faite pendant la maintenance tombe dans le cas de RG-50 (non envoyée,
> renvoyée d'un geste). **E5 reste la première chose à écrire le jour de la
> reprise**, avant toute mutation.

### Ce qu'il faut décider maintenant

> **E5** — Chaque mutation porte un **numéro de version de format**, dès la
> première ligne de code écrite. L'ajouter plus tard est impossible : les
> mutations déjà en file sur un téléphone n'en auront pas.

> **E6** — Le format de mutation évolue par **ajout de champs facultatifs
> uniquement**. Jamais de suppression, jamais de changement de sens d'un champ
> existant. C'est la seule discipline qui garantit qu'une mutation vieille de
> deux mois reste lisible.

> **E7** — Le serveur accepte **au minimum les six derniers mois de formats**.
> Une saison entière peut s'écouler entre deux ouvertures de l'application sur
> un téléphone secondaire.

**E6 et E7 ne font pas double emploi, et il faut dire pourquoi.** Si E6 était
toujours tenue, aucun format ne deviendrait jamais illisible et le 426 ci-dessous
serait du code mort. Mais E6 est une **discipline**, pas une garantie : elle
tiendra jusqu'au jour où une correction de fond l'exigera — un champ dont le sens
s'avère faux, une unité mal choisie. E5 et E8 sont le filet pour ce jour-là.

C'est aussi la raison pour laquelle E7 se formule en durée et non en « toutes les
versions » : la compatibilité est promise sur six mois, pas indéfiniment. Au-delà,
le client met à jour — ce qu'il peut faire, puisqu'il a repris le réseau.

**Conséquence à ne pas manquer : le 426 doit être testé alors qu'il ne se produit
jamais.** Un chemin d'erreur qui ne s'exerce pas est un chemin d'erreur cassé. Le
test appartient au niveau N3 (chapitre 2, §3).

### Un troisième cas d'erreur, qui manquait au chapitre 1

Le chapitre 1 distinguait les erreurs récupérables (réseau, 5xx) des erreurs
définitives (422, payload invalide, la file s'arrête). Il en manque une :

| Cas | Code | Comportement du client |
|---|---|---|
| Réseau, 5xx | — | Réessai avec attente croissante |
| Session expirée | 401 | File conservée, reconnexion demandée (H7) |
| Payload invalide — **bug** | 422 | Mutation en échec, file arrêtée, utilisateur prévenu |
| **Format obsolète — pas un bug** | **426** | **Mettre à jour l'application, puis rejouer la file** |

> **E8** — Un format obsolète ne doit **jamais** être traité comme un payload
> invalide. Le premier se résout en mettant l'application à jour ; le second est
> un défaut. Les confondre ferait perdre des saisies légitimes à des
> utilisateurs qui n'ont rien fait de mal — le pire résultat possible pour cet
> outil.

### La mise à jour du service worker lui-même

Ne pas forcer l'activation immédiate d'une nouvelle version pendant qu'une
session est ouverte : l'application tournerait avec l'ancien JavaScript et le
nouveau service worker. La nouvelle version s'active **à la prochaine
ouverture**, et un bandeau discret signale qu'elle est disponible.

La file, elle, est en IndexedDB : elle survit à toute mise à jour du service
worker, ce qui est précisément ce qu'on veut.

---

## 4. Les migrations en production

Un seul serveur, un trafic faible, et des clients terrain qui sont de toute
façon désynchronisés : **une courte indisponibilité est acceptable**. Pas besoin
de migrations compatibles en deux temps.

```
mode maintenance → migrate → assets → queue:restart → sortie de maintenance
```

> **E9** — Les clients hors ligne **ne voient pas le mode maintenance** — ils
> continuent de saisir localement et rejoueront après. C'est un effet
> secondaire heureux du chapitre 1 : le terrain est insensible aux
> indisponibilités du serveur. À condition, évidemment, qu'E5 à E8 soient
> respectés.

Le retour arrière suit S13 : on n'annule pas une migration en production, on en
ajoute une corrective. Le `down()` sert au développement, pas à l'exploitation.

---

## 5. Sauvegardes

Deux natures très différentes, souvent traitées comme une seule.

| | Base de données | Photos |
|---|---|---|
| Volume | quelques dizaines de Mo | plusieurs Go, croissant |
| Méthode | `pg_dump` quotidien | copie incrémentale |
| Reconstituable ? | non | non |
| Ce qui casse en premier | rien | l'espace disque |

> **E10** — La restauration est **testée**, pas supposée. Une fois par
> trimestre : restaurer sur une machine vierge, ouvrir un jardin, vérifier que
> le tableau de bord affiche ses échéances et qu'une photo s'affiche. RG-11 du
> CDC est explicite — l'historique d'un jardin prend de la valeur avec les
> années et **ne se reconstitue pas**. Une sauvegarde jamais restaurée n'est
> pas une sauvegarde, c'est une intention.

Le test doit inclure les photos : une base restaurée dont les fichiers manquent
donne une application qui s'ouvre normalement et n'affiche que des images
cassées — la panne la plus décourageante à découvrir six mois trop tard.

---

## 6. La seule alerte qui vaut la peine

Pas d'APM, pas de service tiers, pas de tableau de bord (§13 du chapitre 3,
« Erreurs et observabilité »).
Deux surveillances suffisent, et elles couvrent ce qui casse réellement :

| Quoi | Pourquoi | Seuil |
|---|---|---|
| **Espace disque** | Les photos le remplissent en silence, et le service s'arrête net | 80 % |
| **Miniatures en retard** | Détecte une panne de worker, invisible autrement (E4) | > 0 depuis 1 h |

Le reste — erreurs applicatives, lenteurs — se lit dans les journaux quand un
problème est signalé. Un fichier de log qu'on sait lire vaut mieux qu'un
tableau de bord qu'on ne regarde jamais.

---

## 7. Décisions de ce chapitre

| # | Décision | Enjeu |
|---|---|---|
| E1 | L'environnement de dev sert en HTTPS accessible depuis un téléphone | Sans quoi le mode terrain n'est pas testable en vrai |
| E2 | `queue:restart` après chaque déploiement | Les workers exécutent sinon l'ancien code |
| E3 | Un job porte son `jardin_id` | Un worker n'a ni session ni jardin courant |
| E4 | Surveiller les miniatures en retard | Une panne de worker est silencieuse |
| E5 | Chaque mutation porte un numéro de version de format | **Impossible à ajouter après coup** |
| E6 | Le format n'évolue que par ajout de champs facultatifs | Seule discipline qui garde lisible une mutation de deux mois |
| E7 | Le serveur accepte six mois de formats antérieurs | Une saison peut s'écouler entre deux ouvertures |
| E8 | Format obsolète (426) ≠ payload invalide (422) | Confondre les deux fait perdre des saisies légitimes |
| E9 | Les clients hors ligne sont insensibles au mode maintenance | Effet heureux du chapitre 1 |
| E10 | La restauration est testée chaque trimestre, photos comprises | Une sauvegarde jamais restaurée est une intention |

## 8. Points ouverts

| # | Question | Quand |
|---|---|---|
| O13 | Certificat local installé sur le téléphone, ou tunnel ? | À tester avant le premier écran terrain |
| O14 | Où tournent les sauvegardes photo, et sur quel support hors serveur ? | Avant la première saison de données réelles |
| O15 | Faut-il une préproduction, ou le jardin pilote suffit-il ? | **Tranché en partie le 19/10/2026** (chapitre 6, §9) : une recette sur données fictives dès le MVP, parce que Léon relit des comportements et non plus chaque ligne. Une préproduction sur données réelles reste ouverte, avant le lot Ouverture |
