# Document technique — Chapitre 1 : hors-ligne et synchronisation

Version 0.2 — 24/08/2026
Suite de `cahier-des-charges-fonctionnel.md` (v0.11) et `modele-de-donnees.md` (v0.6).

**Stack retenue :** Laravel + Inertia + React/TypeScript, PostgreSQL, serveur
Linux ordinaire. Le modèle de données porte les décisions **D1 à D10** ; ce
chapitre porte les décisions **H1 à H11**.

**Portée du hors-ligne, posée par RG-01 et le §8.2 du CDC :** file d'attente
d'écritures et cache de la liste du jour. Pas de réplique locale complète, pas de
moteur de règles côté client.

---

## 1. Ce que le hors-ligne doit permettre

Le hors-ligne n'est pas un objectif en soi : il sert deux parcours, et deux
seulement.

| Parcours | Ce qu'il faut sans réseau |
|---|---|
| **P1 — Enregistrer** (< 15 s, 3 gestes) | Choisir un type d'intervention, choisir une ou plusieurs plantes, valider. Photo facultative |
| **P2 — Solder une échéance** | Voir la liste du jour, taper « fait » sur une ligne |

S'y ajoute un cas prévu par RG-15 : **créer une plante depuis le terrain**, avec
capture de position. Il est gratuit puisque les identifiants sont des UUID
générés côté client (D2), donc il est inclus.

### Ce qui reste en ligne, délibérément

| Fonction | Pourquoi elle n'a pas besoin d'être hors ligne |
|---|---|
| Modifier une règle | Geste de bureau, au calme (§2.2 du CDC) |
| Reporter, suspendre une échéance | Confort, jamais urgent sur le terrain |
| Consulter l'historique complet | Volume important, besoin rare dehors |
| Inventaire, import en lot, étiquettes | Parcours P0 et P3, bureau |
| Tableau de bord bureau | Idem |

> **H1** — Le mode terrain est un **sous-ensemble délibérément pauvre** de
> l'application. Toute fonction qu'on y ajoute doit être justifiée par un geste
> réellement fait dehors, les mains sales. Chaque ajout élargit la surface à
> synchroniser, donc la surface à déboguer sans pouvoir reproduire.

---

## 2. Le principe directeur : hors ligne, on n'insère que des faits

C'est la décision qui rend tout le reste simple, et elle mérite d'être posée
avant toute autre.

> **H2** — **Toute opération réalisable hors ligne est une insertion.** Aucune
> modification, aucune suppression. La file d'attente ne contient que des
> créations : intervention, photo, plante.

Ce n'est pas une contrainte arbitraire, c'est la nature du domaine :

- **Une intervention est un fait**, pas une mise à jour. « J'ai taillé le
  pommier samedi » ne remplace rien, ça s'ajoute.
- **Solder une échéance, c'est créer une intervention.** L'échéance n'est pas
  modifiée par le terrain : elle est recalculée par le moteur à partir des
  faits (RG-40).
- **On n'efface jamais** (RG-02) : une plante est archivée, pas supprimée.

Conséquences directes, et elles sont énormes :

1. **Deux insertions ne se contredisent jamais.** Il n'y a pas de « qui gagne » :
   les deux faits coexistent. La classe de conflits la plus pénible d'une
   synchronisation — deux modifications concurrentes du même champ — n'existe
   pas ici.
2. **La cible d'une insertion ne peut pas disparaître**, puisque rien n'est
   jamais supprimé. Le cas « j'enregistre sur une plante qui n'existe plus » est
   impossible par construction.
3. **Le rejeu ne demande aucune fusion**, seulement de l'idempotence.

Tout ce qui suit découle de là.

---

## 3. Le paquet terrain

Ce qui est mis en cache localement pour que la vue terrain fonctionne sans
réseau.

### Contenu

```
paquet_terrain {
  version               // horodatage de génération, pour l'invalidation
  jardin { id, nom, profil_climatique }
  zones[]               // id, nom, ordre_tournee
  plantes[]             // id, code_court, nom, zone_id, repere,
                        // position { lat, lon }, miniature_url, espece_libelle
  echeances[]           // cle = (regle_id, due_le)  ← H11, jamais un id de base
                        // plante_id, type_intervention_id, libelle,
                        // etat, fenetre_fin, explication (RG-06)
  types_intervention[]  // id, libelle, famille, demande_quantite
}
```

Les échéances embarquées sont **celles des 30 prochains jours**, dans les états
`ouverte`, `optimale` et `urgente` — pas les échéances futures lointaines, qui
n'ont aucun usage dehors.

> **H11 — une échéance du paquet est désignée par `(regle_id, due_le)`, jamais
> par un identifiant de base.** Ces trois états-là ne sont **jamais matérialisés**
> (D3, S10) : les lignes affichées sur le téléphone n'existent dans aucune table,
> elles n'ont donc pas d'identifiant à porter.
>
> Le couple est déterministe — le moteur est pur (A3), deux calculs à la même
> date rendent la même clé — donc il survit à un rafraîchissement du paquet. Un
> identifiant tiré au hasard à la génération changerait à chaque rafraîchissement
> et le client ne saurait plus quelle ligne il vient de solder : il en
> réafficherait une déjà faite, ou en masquerait une autre.
>
> Rien de tout cela ne remonte au serveur : solder, c'est créer une intervention
> (H2). La clé ne sert qu'à l'affichage local et à l'effacement optimiste.

### Volume

| Élément | Estimation à 500 plantes |
|---|---|
| Plantes (JSON) | ~110 Ko |
| Échéances (JSON) | ~30 Ko |
| Zones, types | < 5 Ko |
| **Miniatures** | **~8 Mo** (500 × 16 Ko) |

> **H3** — Les **miniatures sont le seul poste lourd** du paquet. Elles sont
> générées côté serveur à 160 px de côté, en WebP, et servies avec une URL
> stable et immuable (empreinte du contenu dans le nom). Le service worker les
> met en cache HTTP classique, séparément des données.

Un chargement complet à 8 Mo est acceptable en Wi-Fi à la maison, pas en 4G au
fond du terrain. D'où la règle suivante.

> **H4** — Le paquet se rafraîchit **quand le réseau est bon et l'application
> ouverte**, jamais au moment où l'utilisateur en a besoin. Les données JSON
> sont rechargées à chaque ouverture de la vue terrain si le réseau répond en
> moins de 2 s ; les miniatures sont chargées **en arrière-plan**, plante par
> plante, et leur absence ne bloque jamais l'affichage — une plante sans
> miniature affiche son code court.

### Stockage

| Donnée | Où | Pourquoi |
|---|---|---|
| Paquet JSON | IndexedDB | Interrogeable, taille confortable, survit à la fermeture |
| Miniatures | Cache API (service worker) | C'est du HTTP, autant utiliser le cache HTTP |
| File d'attente | IndexedDB | Doit survivre à un crash et à un redémarrage du téléphone |
| Photos en attente | IndexedDB (Blob) | Trop lourdes pour autre chose |

Jamais `localStorage` : synchrone, limité à ~5 Mo, et il bloque le fil
principal.

---

## 4. La file d'attente

### Structure d'une mutation

```
mutation {
  id            // UUID v4 généré côté client — clé d'idempotence (H5)
  type          // 'intervention' | 'plante' | 'photo'
  payload       // le corps à envoyer
  cree_le       // horodatage local
  etat          // 'en_attente' | 'en_cours' | 'echouee' | 'confirmee'
  tentatives    // compteur
  derniere_erreur
}
```

### Idempotence

> **H5** — Chaque mutation porte un **UUID généré côté client**, qui devient
> l'identifiant de l'enregistrement créé. Le serveur traite une mutation dont
> l'identifiant existe déjà comme un **succès**, sans rien créer.

C'est le point le plus important de tout le chapitre. Le scénario qu'il traite :
la requête part, le serveur l'enregistre, la réponse se perd (tunnel, 4G qui
tombe). Le client, n'ayant pas reçu de confirmation, rejoue. Sans clé
d'idempotence, on obtient **deux tailles du même pommier le même jour** — une
erreur invisible, qui pollue l'historique et fausse le calcul de la prochaine
échéance.

C'est aussi la raison pour laquelle D2 (UUID) n'était pas un détail.

Côté serveur, cela se traduit par une contrainte d'unicité sur la clé primaire
et un `INSERT ... ON CONFLICT DO NOTHING`, suivi d'une lecture pour renvoyer
l'état réel.

### Ordre et blocage

> **H6** — La file est **strictement FIFO** et s'arrête à la première erreur
> non récupérable. Elle n'est jamais réordonnée.

Une intervention peut référencer une plante créée juste avant, elle aussi dans
la file. Rejouer dans le désordre casserait la référence. L'ordre garantit que
la plante existe quand son intervention arrive.

| Type d'erreur | Traitement |
|---|---|
| Réseau, 5xx, délai dépassé | Réessai avec attente croissante (2 s, 8 s, 30 s, 2 min, plafonné à 5 min) |
| 401 — session expirée | **La file est conservée intacte.** Reconnexion demandée, rejeu ensuite |
| 409 — déjà existant | Traité comme un succès (H5) |
| 422 — payload invalide | Mutation marquée `echouee`, **la file s'arrête**, l'utilisateur est prévenu |
| **426 — format obsolète** | Mettre l'application à jour, **puis rejouer la file**. Jamais un échec : voir E8 (chapitre 5) |

> Chaque mutation porte un **numéro de version de format** (E5). Ce champ doit
> exister dès la première ligne de code : les mutations déjà en file sur un
> téléphone ne pourront jamais l'acquérir rétroactivement.

> **H7** — Une session expirée ne fait **jamais** perdre la file. C'est le
> scénario réel le plus probable : le téléphone reste des heures en poche, la
> session tombe, l'utilisateur revient. Perdre trois saisies à ce moment-là
> suffirait à faire abandonner l'outil.

### Déclencheurs de rejeu

Trois, parce qu'aucun n'est fiable seul :

1. **À l'ouverture de la vue terrain** — le seul qui fonctionne partout.
2. **Sur l'événement `online`** — utile quand l'application est ouverte au
   moment où le réseau revient.
3. **Background Sync API** — quand le navigateur la propose.

> **H8** — **Ne jamais compter sur Background Sync.** Elle n'existe pas sur
> Safari iOS, et une PWA installée sur iPhone ne s'exécute pas en arrière-plan.
> Le rejeu à l'ouverture est le mécanisme de référence ; le reste est un
> agrément qui fait remonter les données plus tôt.

C'est une limite de plateforme, pas un choix : sur iPhone, des saisies faites
au fond du terrain remontent quand l'utilisateur rouvre l'application, pas
avant. L'interface doit donc rendre cet état **visible**, sans quoi il croira
avoir synchronisé.

---

## 5. Les conflits réels

Grâce à H2, il n'en reste que trois, et aucun n'est un vrai conflit de données.

### C1 — L'échéance a déjà été soldée ailleurs

Le téléphone rejoue une intervention correspondant à une échéance qu'un autre
appareil a déjà soldée.

**Traitement : on enregistre quand même.** L'intervention a réellement eu lieu ;
la refuser reviendrait à effacer un fait. Le moteur recalcule à partir de
l'historique complet et produit la bonne prochaine échéance.

L'utilisateur voit, dans l'historique, deux interventions le même jour — ce qui
est *vrai* si deux personnes ont taillé le même arbre, et rattrapable d'un geste
sinon. C'est préférable à une perte silencieuse.

### C2 — La plante a été archivée entre-temps

**Traitement : on enregistre quand même**, sur la plante archivée. RG-02
garantit que l'historique d'une plante archivée reste consultable ; une
intervention faite avant l'archivage y a toute sa place.

### C3 — La règle a changé entre-temps

Sans effet. L'intervention est un fait indépendant de la règle ; le moteur
recalcule les échéances à partir de la règle en vigueur au moment du calcul.
C'est précisément ce que D3 (calcul à la volée) rend possible.

> **H9** — **Une intervention n'est jamais refusée pour une raison métier.**
> Seul un payload structurellement invalide (422) est rejeté. Le serveur peut
> *signaler* une anomalie — échéance déjà soldée, plante archivée — mais il
> enregistre.

Ce principe est la traduction technique de RG-05 : la date fait foi, la saisie
rétroactive est le cas nominal.

---

## 6. Les photos

Traitées à part parce qu'elles sont lourdes et qu'elles ne doivent jamais
bloquer un fait.

### Chaîne de traitement

```
prise de vue
   ↓  compression côté client — 1600 px max, JPEG q=0.8 → ~250 Ko
   ↓  stockage IndexedDB (Blob) + UUID
   ↓  mutation 'intervention' envoyée AVEC la référence de photo
   ↓  mutation 'photo' envoyée SÉPARÉMENT, après
   ↓  purge locale après confirmation
```

> **H10** — L'intervention et sa photo sont **deux mutations distinctes**, et
> l'intervention part la première. Une photo qui échoue ne doit jamais empêcher
> l'enregistrement du fait qu'elle illustre — RG-38 le dit déjà : la photo n'est
> jamais obligatoire.

Le serveur accepte donc une intervention qui référence une photo pas encore
arrivée. La fiche affiche la photo dès qu'elle remonte.

### Garde-fous

- **Compression obligatoire avant stockage** : sans elle, dix photos d'iPhone
  saturent le quota IndexedDB.
- **Plafond local** : au-delà de 30 photos en attente, l'application prévient et
  refuse d'en accepter d'autres tant que rien n'est remonté. Mieux vaut un
  refus explicite qu'un échec de quota impossible à diagnostiquer dehors.
- **Le quota du jardin** (§8.4 et §8.6 du CDC) est vérifié côté serveur à la
  réception, pas côté client : le client ne connaît pas l'état réel du stockage.
  Au MVP il **protège le disque** sans être affiché ; son signalement à
  l'utilisateur est du lot Ouverture.

---

## 7. La couture Inertia / API terrain

C'est le point signalé au moment du choix de stack : Inertia répond en HTML/JSON
piloté par le serveur, donc il ne fonctionne pas hors ligne. La vue terrain est
l'exception, et elle est délimitée.

```
┌─ Application Inertia (bureau + mobile en ligne) ─────────────┐
│  inventaire · règles · historique · tableau de bord · P0 P3  │
│  routage, autorisations et rendu côté serveur                │
└──────────────────────────────────────────────────────────────┘

┌─ Vue terrain — composant React autonome ─────────────────────┐
│  monté par une page Inertia, mais parle en JSON              │
│  GET  /terrain/paquet          → le paquet (§3)              │
│  POST /terrain/mutations       → rejeu, une mutation à la fois│
│  POST /terrain/photos/{uuid}   → envoi binaire               │
└──────────────────────────────────────────────────────────────┘
```

### Points d'attention

- **Authentification par cookie de session**, même origine. Pas de jeton à
  gérer, pas de rafraîchissement. En contrepartie, H7 devient obligatoire : la
  session expire, la file doit survivre.
- **Ces routes sont des routes applicatives, pas une API publique.** Elles ne
  sont ni versionnées, ni documentées pour des tiers. Le jour où une vraie API
  est nécessaire, elle sera construite à part.
- **Le cloisonnement s'applique identiquement** : ces routes passent par les
  mêmes global scopes (D1). Aucune exception, aucun raccourci « c'est juste
  pour le terrain ».
- **Le moteur ne tourne jamais côté client.** Le paquet contient des échéances
  déjà calculées. Quand une intervention est enregistrée hors ligne, la ligne
  disparaît de la liste du jour localement — c'est un affichage optimiste, pas
  un recalcul. La vérité revient avec le paquet suivant.

---

## 8. Ce que voit l'utilisateur

L'état de synchronisation ne doit jamais être deviné.

| État | Affichage |
|---|---|
| Tout est remonté | Rien. Le silence est le bon signal |
| N saisies en attente | Bandeau discret et permanent : « 3 saisies en attente » |
| Envoi en cours | Le compteur décroît |
| Session expirée | « Reconnexion nécessaire pour envoyer 3 saisies » + bouton |
| Mutation en échec définitif | Ligne détaillée, avec la saisie concernée et une action « réessayer » ou « supprimer » |
| Hors ligne | Indicateur visible dans l'en-tête de la vue terrain |

Trois règles d'interface :

1. **Une saisie hors ligne est confirmée immédiatement**, comme si elle était
   partie. C'est ce qui préserve le parcours à trois gestes.
2. **Chaque ligne saisie hors ligne est marquée** dans l'historique local tant
   qu'elle n'est pas confirmée.
3. **Aucune perte silencieuse.** Une mutation en échec définitif reste visible
   et actionnable indéfiniment. Elle n'est jamais purgée automatiquement.

---

## 9. Tests

Le hors-ligne ne se teste pas à la main de façon fiable : il faut des cas
automatisés, et ils sont peu nombreux.

**La méthode générale — quoi tester, à quel niveau, comment écrire un test — est
dans `technique-02-tests.md`.** Les cas ci-dessous en sont l'application au
hors-ligne : ce sont des tests de niveau N2 (persistance) et N4 (navigateur).

| # | Cas | Attendu |
|---|---|---|
| T1 | Saisir hors ligne, revenir en ligne, ouvrir la vue terrain | L'intervention est en base, une seule fois |
| T2 | **Rejouer deux fois la même mutation** | Une seule intervention. C'est le test d'idempotence (H5) |
| T3 | Créer une plante puis une intervention dessus, hors ligne, rejouer | Les deux passent, dans l'ordre |
| T4 | Session expirée pendant le rejeu | La file est intacte après reconnexion (H7) |
| T5 | Solder une échéance déjà soldée ailleurs | L'intervention est enregistrée, une anomalie est signalée (C1) |
| T6 | Payload invalide au milieu de la file | La file s'arrête, les suivantes restent en attente |
| T7 | Photo échouée, intervention réussie | L'intervention existe sans sa photo (H10) |
| T8 | Quota local de photos atteint | Refus explicite, pas d'échec silencieux |
| T9 | Rafraîchir le paquet après avoir soldé une ligne hors ligne | La ligne soldée ne réapparaît pas, et aucune autre ne disparaît : la clé `(regle_id, due_le)` est stable (H11) |

T2 est le test le plus important du chapitre. S'il n'y en avait qu'un, ce
serait celui-là.

---

## 10. Décisions de ce chapitre

| # | Décision | Enjeu |
|---|---|---|
| H1 | Le mode terrain est un sous-ensemble pauvre et délibéré | Surface à synchroniser |
| H2 | **Hors ligne, on n'insère que des faits** | Élimine la classe de conflits la plus coûteuse |
| H3 | Miniatures 160 px WebP, URL immuable, cache HTTP | Seul poste lourd du paquet |
| H4 | Le paquet se rafraîchit quand le réseau est bon, jamais au besoin | Utilisabilité en 4G faible |
| H5 | **UUID client comme clé d'idempotence** | Empêche les doublons invisibles |
| H6 | File FIFO stricte, arrêt à la première erreur bloquante | Intégrité référentielle au rejeu |
| H7 | Une session expirée ne fait jamais perdre la file | Scénario réel le plus probable |
| H8 | Ne jamais compter sur Background Sync | Limite plateforme, pas un choix |
| H9 | Une intervention n'est jamais refusée pour raison métier | Traduction technique de RG-05 |
| H10 | L'intervention et sa photo sont deux mutations distinctes | La photo ne bloque jamais le fait |
| H11 | Une échéance du paquet est désignée par `(regle_id, due_le)` | Les échéances affichées n'existent dans aucune table (D3, S10) |

## 11. Points ouverts

| # | Question | Quand la trancher |
|---|---|---|
| O1 | Faut-il purger les mutations confirmées, et après combien de temps ? | À l'implémentation — proposition : 7 jours, pour permettre un diagnostic |
| O2 | Que faire des photos en attente si l'utilisateur se déconnecte volontairement ? | Avant le lot Ouverture — elles contiennent des données du jardin |
| O3 | Le paquet doit-il embarquer les plantes archivées ? | À l'usage — utile pour saisir une intervention passée, inutile au quotidien |
| O4 | Rejeu par lot plutôt qu'une mutation à la fois, si la file est longue | Optimisation, seulement si le besoin se manifeste |
