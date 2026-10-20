# Document technique — Chapitre 3 : architecture applicative et moteur de règles

Version 1.0 — 24/08/2026
Suite de `technique-01-hors-ligne.md` (v0.2) et `technique-02-tests.md` (v0.2).
Décisions **A1 à A25**. Références en §17.

**Stack :** Laravel 12 + Inertia + React/TypeScript, PostgreSQL.

> Comme au chapitre 2, les extraits de code sont des **esquisses destinées à
> montrer la forme**, pas du code exécuté : le projet n'existe pas encore.

---

## 1. Deux niveaux de rangement, pas un

### La norme suivie — et ce qu'elle dit exactement

La structure ne suit pas une norme unique : elle assemble trois sources
convergentes, citées en §17 :

| Source | Ce qu'elle apporte |
|---|---|
| *Package by feature* | Le principe : un arbre de dossiers dit ce que l'application **fait**, pas quelles couches elle empile |
| *Laravel Beyond CRUD* (Spatie) | La déclinaison Laravel : `src/` plutôt que `app/`, actions plutôt que services |
| *Vertical Slice Architecture* (Jimmy Bogard) | Le rangement par **cas d'usage** : tout le code d'une même demande au même endroit |

La dernière est celle qui porte le principe le plus net. Sa règle tient en une
phrase :

> **Minimiser le couplage entre tranches, maximiser le couplage dans une
> tranche.**

Et la pratique établie ne l'applique **pas à plat**. La structure recommandée
combine deux niveaux : un dossier par **capacité métier** (l'équivalent d'un
*bounded context*), puis à l'intérieur un dossier par **cas d'usage**. Sans le
premier niveau, on obtient une prolifération de micro-tranches dans laquelle
plus personne n'a de vue d'ensemble.

> **A1** — Deux niveaux de rangement. **Niveau 1 : le domaine métier** — durable,
> avec ses entités et son vocabulaire. **Niveau 2 : la capacité** — un ensemble
> de cas d'usage qui vont ensemble. Le domaine ne change presque jamais ; les
> capacités s'ajoutent.

### Comment ça s'appelle, exactement

**Un monolithe modulaire, avec des tranches verticales à l'intérieur des
modules.** Ni du vertical slice pur, ni de l'architecture en couches.

La différence avec le vertical slice pur mérite d'être explicite, parce qu'elle
est délibérée : cette école préfère **dupliquer plutôt que partager**, pour que
chaque tranche reste indépendante. Ici, on fait l'inverse sur un point précis —
**le moteur de règles est un noyau partagé par toutes les tranches**, et c'est
voulu : c'est le seul endroit où la logique est réellement commune, complexe et
lourdement testée. La dupliquer serait absurde.

Partout ailleurs, la règle du vertical slice s'applique : peu de couplage entre
capacités, beaucoup à l'intérieur d'une capacité.

### Pourquoi le QR ne peut pas être au niveau 1

C'est le cas limite le plus instructif du découpage, et il mérite d'être traité
en entier.

**Ce qui appartient en propre au QR :** encoder une chaîne en image, lire une
image avec la caméra. Deux fichiers, dont un côté front.

**Ce qui ne lui appartient pas, malgré les apparences :**

| Élément | Pourquoi il préexiste au QR |
|---|---|
| `AttribuerCodeCourt` | Le code court existe dans le CDC **sans QR** (RG-13). Il sert aux étiquettes imprimées en clair dès le MVP ; le QR n'arrive qu'au lot 4 |
| `ResoudreCodeCourt` | Sert aussi à une URL tapée à la main, ou à une recherche par code dans le sélecteur |
| `EtiquettesController` | Imprime des étiquettes, avec ou sans QR dessus |

Un dossier `QRScan/` au premier niveau devrait donc soit **absorber du code qui
lui est antérieur** — et alors supprimer le QR casserait les étiquettes — soit
ne contenir que deux fichiers. Ni l'un ni l'autre n'est satisfaisant.

**Le bon regroupement existe, mais il est plus large que le QR** : c'est
*identifier physiquement une plante sur le terrain*. Code court, étiquettes,
encodage QR, résolution — tout ce qui répond au besoin **B2** du CDC
(« l'outil me dit de traiter le prunier n°3 : où est-il ? »).

> **A14** — Un dossier porte un nom **métier**, jamais une technologie. Test :
> le nom pourrait-il apparaître dans le cahier des charges fonctionnel ?
> `Identification` oui, `QRScan` non. Le jour où le NFC s'ajoute, il entre dans
> le même dossier sans rien renommer — ce qui ne serait pas vrai d'un dossier
> `QRScan/`.

---

### Terrain n'est pas une fonctionnalité, c'est un adaptateur

Il serait tentant de faire du terrain une fonctionnalité parallèle au bureau.
Ce serait faux : **côté métier, il n'y a aucune différence entre les deux.**

Ce que contient réellement `Terrain/` :

| Fichier | Ce qu'il fait | Ce qu'il ne fait pas |
|---|---|---|
| `PaquetController` | Agrège des données de `Jardin/`, `Echeances/`, `Bibliotheque/` et les sérialise | Aucun calcul, aucune règle |
| `MutationsController` | Déballe la file, garantit l'idempotence, appelle `Journal/Actions/EnregistrerIntervention` | N'enregistre rien lui-même |
| `PhotosController` | Reçoit un binaire, appelle `Journal/Actions/AttacherPhoto` | Idem |

C'est un **adaptateur de transport** : protocole JSON, idempotence (H5), rejeu
de file. Rien de métier. Le dossier reste justifié — le paquet agrège plusieurs
modules et ne peut appartenir à aucun — mais sa nature impose une règle.

> **A17** — Une action métier a **un seul point d'implémentation**, quel que
> soit le point d'entrée. Enregistrer une intervention depuis le bureau ou
> depuis le terrain appelle la **même** action. Si une règle se retrouve écrite
> dans `Terrain/`, c'est un bug de rangement : deux chemins divergeraient, et un
> correctif appliqué d'un seul côté produirait des comportements différents
> selon l'appareil — le pire type de bug à diagnostiquer.

**La vraie différence entre bureau et terrain est dans l'interface**, pas dans
le back : ce que l'utilisateur peut atteindre, en combien de gestes, avec quelle
densité d'information. C'est le front qui porte cette distinction (§3), et c'est
là qu'elle doit rester.

---

## 2. Le back

Six modules au MVP, six emplacements réservés pour la suite.

```
src/
  Comptes/                              ← NIVEAU 1 : domaine
    Domaine/            Utilisateur, Jardin, Adhesion, ProfilClimatique, Quota
    Jardins/                            ← niveau 2 : capacité — MVP
      CreerJardin.php, ChoisirProfilClimatique.php, BasculerDeJardin.php
      JardinController.php
    Acces/                              ← lot Ouverture
      ○ Inscription.php, VerifierCourriel.php, MotDePasseOublie.php
      ○ ConnexionController.php, InscriptionController.php
    Membres/                            ← lot 1 bis
      ○ InviterMembre.php, AccepterInvitation.php, RetirerMembre.php
      ○ MembreController.php
    DonneesPersonnelles/                ← lot Ouverture — RGPD
      ○ SupprimerCompte.php, PurgerPhotos.php, ExporterDonnees.php
      ○ ConfidentialiteController.php
    Quotas/                             ← MVP (calcul) + Ouverture (affichage)
      CalculerOccupation.php
      ○ QuotaController.php
    Policies/
    routes.php

  Jardin/
    Domaine/            Zone, Plante, Geometrie
    Plantes/
      CreerPlante.php, ArchiverPlante.php, ScinderPlante.php
      PlanteController.php
    Zones/
      ZoneController.php
    Inventaire/                         ← la mise en route, P0
      ImporterEnLot.php, DupliquerPlantes.php
      ImportController.php
    Identification/                     ← ★ TOUT CE QUI CONCERNE LE QR
      AttribuerCodeCourt.php              MVP — RG-13
      ResoudreCodeCourt.php               MVP — URL tapée à la main, recherche par code
      EtiquettesController.php            MVP — étiquettes imprimées en clair
      ○ EncodeurQR.php                    lot 4 — étiquettes scannables
      ○ EncodeurNFC.php                   lot 4 — même dossier, sans rien renommer
    Localisation/
      PlantesProches.php, EnregistrerPosition.php
    routes.php

  Bibliotheque/
    Domaine/            Espece, FamilleBotanique, TypeIntervention, RegleReference
    Especes/            EspeceController.php, RechercheEspece.php
    ReglesTypes/        AppliquerReglesTypes.php, ProposerCorrection.php
    TypesIntervention/  TypeInterventionController.php
    routes.php

  Echeances/
    Moteur/                             ← LA FRONTIÈRE STRICTE (§6)
      Moteur, Regle, Fenetre, IntervalleSaisonnier, Historique,
      Echeance, Etat, Decalage, Calendrier, Periode, JourMois
    Domaine/            Regle, Fenetre, IntervalleSaisonnier, Echeance (Eloquent)
    Calcul/             CalculEcheances.php (le pont)
    Reglages/           ModifierRegle.php, ValiderFenetres.php, RegleController.php
    Policies/           ReglePolicy.php
    TableauDeBord/      TableauDeBordController.php
    Solde/              SolderEcheance.php, SuspendreEcheance.php
    routes.php

  Journal/
    Domaine/            Intervention, Photo
    Saisie/             EnregistrerIntervention.php, CorrigerIntervention.php
                        InterventionController.php
    Photos/             AttacherPhoto.php, CompresserPhoto.php
    Historique/         HistoriqueController.php
    ○ Recoltes/           lot 1 — quantités, bilans annuels
    routes.php

  Terrain/                              ← ADAPTATEUR, zéro métier (A17)
    Paquet/             ConstructionDuPaquet.php, PaquetController.php
    Mutations/          RejeuDeFile.php, Idempotence.php, MutationsController.php
    Photos/             PhotosController.php
    ○ Scan/               ScanController.php   lot 4 — appelle Jardin/Identification
    routes.php

  Support/
    Jardin/             JardinCourant, JardinScope (§9)
    Http/               middleware, HandleInertiaRequests

  ── domaines métier réservés ──
  ○ Observations/       lot 1 — stades phénologiques, constats sanitaires
  ○ Intrants/           lot 2 — produits, doses, stocks, délais avant récolte
  ○ Rotation/           lot 3 — familles botaniques, délai de retour
  ○ Meteo/              lot 5 — prévisions, décalage automatique

  ── domaines SaaS réservés ──
  ○ Facturation/                        si le produit devient payant (Q9)
      Domaine/          Abonnement, Plan, Facture, MoyenDePaiement
      Abonnements/      Souscrire, ChangerDePlan, Resilier
      Paiements/        WebhookController, RelancerEchec
      Factures/         GenererFacture, FactureController
      routes.php

  ○ Admin/                              back-office de l'éditeur — 3e bundle
      Jardins/          ListerJardins, ConsulterJardin (support)
      Curation/         EditerEspece, EditerRegleReference,
                        PublierVersionBibliotheque   ← RG-24
      Utilisateurs/     ListerUtilisateurs, SuspendreCompte
      Statistiques/     UsageParJardin, VolumeDeStockage
      routes.php

○ = prévu, pas encore écrit
```

### Ce que le SaaS ajoute, et où

Aucun des modules métier — `Jardin`, `Echeances`, `Journal`, `Bibliotheque` —
n'est touché par le passage en service ouvert. C'est le résultat direct des
décisions du CDC : `jardin_id` est déjà partout (RG-22), le filtrage est imposé
par défaut (RG-21), et les adhésions portent déjà leur rôle (RG-20).

| Besoin SaaS | Où | Quand |
|---|---|---|
| Inscription, connexion, mot de passe oublié | `Comptes/Acces/` | lot Ouverture |
| Vérification de courriel | `Comptes/Acces/` | lot Ouverture |
| Suppression de compte, export RGPD | `Comptes/DonneesPersonnelles/` | lot Ouverture |
| Invitation d'un proche sur un jardin | `Comptes/Membres/` | lot 1 bis |
| Quotas de stockage affichés | `Comptes/Quotas/` | lot Ouverture |
| Envoi de courriels transactionnels | `Support/Courriel/` | lot Ouverture |
| Abonnements, factures, paiements | `Facturation/` | si payant (Q9) |
| Back-office : voir un jardin pour le support | `Admin/Jardins/` | à l'ouverture |
| Éditer la bibliothèque partagée | `Admin/Curation/` | **utile dès le MVP** |
| Statistiques d'usage, volume disque | `Admin/Statistiques/` | à l'ouverture |

Deux remarques sur ce tableau :

- **`Admin/Curation/` est utile bien avant l'ouverture.** Éditer la bibliothèque
  d'espèces et de règles types est un travail continu (§5.1 du CDC, Q13). Le
  faire en base à la main dès le MVP est acceptable un temps, mais c'est le
  premier écran d'administration à écrire.
- **L'envoi de courriel est un moyen, pas un métier** (A14) : il va dans
  `Support/Courriel/`, pas dans un module `Notifications/`. Ce qui déclenche un
  courriel appartient au domaine concerné — `Comptes/Acces/` pour une
  réinitialisation, `Echeances/` pour un rappel hebdomadaire au lot 5.

**Le QR est donc bien groupé** : tout son back tient dans
`Jardin/Identification/`, avec le code court et les étiquettes auxquels il est
indissociable. Ce qui reste dehors est ce qui n'est pas du métier — l'accès
caméra (`common/hooks/useScanner`) et l'écran de scan
(`apps/terrain/pages/Scan.tsx`) — plus une route d'entrée dans `Terrain/Scan/`
qui ne fait qu'appeler `Jardin/Identification/ResoudreCodeCourt`.

**La limite honnête : back et front restent séparés.** C'est structurel en
Laravel + Inertia — le PHP vit dans `src/`, le TypeScript dans `resources/js/`.
On ne peut pas y échapper sans se battre contre Vite et contre la résolution des
pages Inertia. Ce qu'on peut faire, et qu'on fait : **aligner les noms**, pour
que `Jardin/Identification/` côté back corresponde à
`modules/jardin/identification/` côté front.

> **Les emplacements réservés sont documentés, pas créés.** Un dossier vide
> n'existe pas dans Git, et un module vide qui traîne six mois devient un piège :
> on ne sait plus s'il est en cours ou abandonné. Cet arbre est la référence ;
> le dossier se crée le jour où on écrit son premier fichier.

**Chaque module déclare ses routes.** Un `RouteServiceProvider` les charge en
parcourant `src/*/routes.php`. Ajouter une fonctionnalité n'oblige donc à
toucher aucun fichier existant — c'est le test le plus concret d'un bon
découpage.

Deux points de forme :

- **`src/` plutôt que `app/`** — pour ne pas heurter les conventions des paquets
  Laravel, qui supposent `App\`. Un `"Src\\": "src/"` dans l'autoloader suffit.
- **Pas de dossier `Actions/` séparé** — les actions vivent directement dans
  leur capacité, à côté du contrôleur qui les appelle. `Jardin/Identification/`
  contient `AttribuerCodeCourt.php` **et** `EtiquettesController.php`. C'est le
  principe même du rangement par tranche : ce qui sert au même besoin est
  côte à côte.

### Ce que Laravel impose vraiment

Réponse courte : **presque rien**. L'autoloader suit PSR-4, donc n'importe
quelle arborescence est possible. Mais l'**auto-découverte** de Laravel, elle,
suppose des emplacements — et s'en écarter demande de la configuration
explicite. C'est un coût réel, mais **payé une seule fois**, au démarrage.

| Ce que Laravel découvre par convention | Ce qu'il faut faire |
|---|---|
| Les modèles | Rien — le chemin n'est pas imposé |
| Les **factories** (`Database\Factories\XFactory`) | Redéfinir `newFactory()` sur les modèles, ou un `Factory::guessFactoryNamesUsing()` global |
| Les **policies** (`App\Policies\XPolicy`) | Enregistrer via `Gate::policy()`, ou un `guessPolicyNamesUsing()` |
| Les **commandes Artisan** (`app/Console/Commands`) | Élargir le chemin scanné dans le service provider |
| Les **migrations** | Rien — on les garde centralisées de toute façon |
| Les **routes** | Un `RouteServiceProvider` qui parcourt `src/*/routes.php` |
| Les **vues** Blade | Sans objet — les vues sont des composants React |

Trois points seulement demandent du travail : factories, policies, commandes.
Une trentaine de lignes dans un service provider, écrites une fois.

C'est le prix de la structure. Il est faible et connu d'avance — mieux vaut le
savoir maintenant que le découvrir au premier test qui ne trouve pas sa
factory.

> **A12** — Les modules reprennent les **cinq blocs du modèle de données**
> (§1 de `modele-de-donnees.md`), plus `Terrain` et `Support`. Le découpage
> existait avant le code, ce qui le rend stable et défendable.

> **A16** — **Chaque module déclare ses propres routes**, chargées par un
> `RouteServiceProvider` qui parcourt `src/*/routes.php`. Ajouter une
> fonctionnalité ne modifie alors aucun fichier existant — c'est le test le plus
> concret d'un bon découpage, et le seul qui se vérifie en regardant un diff.

> **A15** — Laravel standard **à l'intérieur** des modules : Eloquent dans les
> contrôleurs, modèles riches, conventions du framework. Une seule chose est
> isolée strictement, le moteur (§6).

> **A2** — `src/Echeances/Moteur/` **n'importe rien de Laravel**, et c'est
> **vérifié automatiquement** par un test d'architecture, pas par la discipline.
> La pureté du moteur (A3, A5) est ce qui rend 150 tests possibles à 1 ms ; elle
> s'érode au premier `Carbon::now()` ajouté sans y penser, et personne ne s'en
> aperçoit avant que la suite ne prenne huit secondes.

### Ce que ça donne quand ça grossit

| Ajout | On crée | On modifie |
|---|---|---|
| Lot 1 — récoltes, observations | `Observations/` | `Journal/` (quantité) |
| Lot 2 — intrants | `Intrants/` | rien |
| Lot 3 — potager | `Rotation/` | `Jardin/` (cultures) |
| Lot 4 — plan | rien | `Jardin/` (géométrie, déjà prévue) |
| Lot 5 — météo | `Meteo/` | `Echeances/` (le moteur consomme) |
| Lot O — ouverture | rien | `Comptes/` |
| Facturation | `Facturation/` | rien |
| Back-office éditeur | `Admin/` + `apps/admin/` | rien |

Huit ajouts, six nouveaux dossiers, presque aucune modification ailleurs.

---

## 3. Le front

> **V1 sans hors-ligne — 19/10/2026** (CDC v0.13). Il n'y a en V1 **qu'un
> seul bundle Inertia**. Les écrans terrain sont des pages Inertia pensées pour
> le téléphone (`pages/Terrain/…`), avec la variante `terrain` des composants
> (A23, inchangée). `apps/terrain/` avec `stockage/`, `file/`, `paquet/` et le
> service worker, comme le module back `Terrain/` (`Paquet/`, `Mutations/`), ne
> sont pas construits : ils reviendront avec le hors-ligne. A13 et A22 sont
> suspendues ; A21 reste, parce que l'empreinte dans le nom de fichier sert
> aussi le cache HTTP ordinaire.

Le front suit la même logique, avec une différence imposée par la technique :
**bureau et terrain sont deux bundles distincts**.

```
resources/js/
  apps/
    bureau/                       ← bundle 1 — Inertia
      pages/                        miroir des URL : Plantes/Index.tsx, Plantes/Show.tsx
      layouts/
      app.tsx
    terrain/                      ← bundle 2 — autonome, hors ligne
      pages/                        Aujourdhui.tsx, Saisie.tsx, Scan.tsx
      stockage/                     IndexedDB — le seul endroit qui y touche
      file/                         la file d'attente de mutations (chapitre 1)
      paquet/                       chargement et rafraîchissement
      app.tsx
      service-worker.ts

  modules/                        ← mêmes noms que les modules back, aux deux niveaux
    jardin/
      plantes/                      carte de plante, sélecteur
      identification/               badge de code court, aperçu d'étiquette
    echeances/
      tableau-de-bord/              ligne d'échéance, pastille d'état, explication
    journal/
      saisie/                       formulaire, sélecteur multiple
      photos/                       capture, compression, aperçu

  common/                         ← générique, aucun métier
    ui/                             LA bibliothèque de composants maison
    hooks/                          useScanner, useGeoloc
    format/                         dates, unités
  tailwind.config.ts                les jetons de design partagés

  types/
    genere.d.ts                     types produits depuis PHP — jamais écrits à la main

  ── réservé ──
  ○ apps/admin/                   SaaS — back-office éditeur, 3e bundle
```

Le back-office éditeur sera un **troisième bundle**, pour la même raison que le
terrain : il n'a aucune raison d'être chargé par un utilisateur ordinaire.

> **A13** — Le bureau et le terrain sont **deux points d'entrée JavaScript
> distincts**. Ce n'est pas du confort : le bundle terrain est chargé sur un
> téléphone en 4G faible et **mis en cache par le service worker**. Y embarquer
> l'import en lot, les étiquettes et le tableau de bord bureau serait payer un
> poids permanent pour du code jamais utilisé dehors.

Règle de rangement : **est-ce que ça touche à un domaine du produit ?** Si oui,
`modules/`, avec le **même nom que le module back**. Sinon `common/`. Et si ça
ne sert qu'à une surface, ça reste dans `apps/<surface>/`.

Les pages Inertia sont un miroir des URL, pas des modules : c'est la convention
Inertia, et s'en écarter complique le rendu pour rien.

### Tailwind et la bibliothèque de composants

**Tailwind CSS**, avec une bibliothèque de composants **conçue sur mesure** avec
un designer — pas de bibliothèque tierce. Les jetons de design (couleurs,
espacements, tailles de texte) vivent dans `tailwind.config.ts`, partagé par les
deux bundles.

Une conséquence, et elle est importante :

> **A23** — Les contraintes du terrain sont **portées par les composants
> eux-mêmes**, pas appliquées écran par écran. Un bouton expose une variante
> `terrain` dont la cible tactile fait au moins 48 px et dont le contraste tient
> en plein soleil (§8.5 du CDC). Une règle inscrite dans le composant ne peut
> pas être oubliée sur un écran ; une règle appliquée à la main le sera.

C'est ce qui rend la bibliothèque maison préférable à une bibliothèque tierce
ici : les contraintes de ce produit — gants, soleil, trois gestes — ne sont pas
celles d'une application de bureau ordinaire, et les composants doivent les
porter d'origine.

La même bibliothèque sert les deux bundles. Seules les **variantes** diffèrent,
pas les composants : un seul `Bouton`, deux tailles.

---

## 4. Inertia, concrètement

Le chapitre précédent le mentionnait sans le traiter. Voici ce qui compte pour
ce projet.

### Comment les données arrivent aux pages

Un contrôleur rend une page avec ses props ; il n'y a pas d'API, pas de client
de requêtes, pas de cache global côté client.

```php
// src/Jardin/Plantes/PlanteController.php
public function show(Request $request, Plante $plante): Response
{
    return Inertia::render('Plantes/Show', [
        'plante'    => PlanteViewModel::from($plante),
        'echeances' => $this->calculer->pourPlante($plante),
        'historique' => Inertia::lazy(fn () => $plante->interventions()->recent()),
        'peut' => [
            'modifierRegles' => $request->user()->can('update', $plante),
        ],
    ]);
}
```

Quatre choses à retenir :

- **Les `ViewModels` vivent dans la surface**, pas dans le domaine. C'est le
  seul endroit qui décide ce que l'écran voit.
- **`Inertia::lazy()`** pour ce qui n'est pas affiché d'emblée : l'historique
  complet d'une plante ne doit pas être chargé à chaque ouverture de fiche.
- **Les autorisations sont envoyées calculées** (`peut.modifierRegles`), jamais
  réimplémentées en TypeScript. La règle vit dans la policy, le front n'en
  reçoit que le résultat.
- **Pas de SSR.** L'application est privée, il n'y a aucun enjeu de
  référencement. C'est une complexité entière qu'on évite.

### Les listes à 500 plantes

RG-19 impose qu'aucune vue ne présente une liste brute. Côté Inertia, cela se
traduit par **recherche et filtrage côté serveur**, avec rechargement partiel :

```tsx
router.get('/plantes', { recherche, zone }, {
    only: ['plantes'],          // ne recharge que cette prop
    preserveState: true,        // garde le champ de recherche et le focus
    replace: true,              // n'empile pas l'historique du navigateur
});
```

Sans `only`, chaque frappe rechargerait aussi les zones, les compteurs et le
reste. C'est le réflexe à prendre dès la première liste.

### Les formulaires et la validation

La validation reste **côté serveur** (`FormRequest`), et Inertia renvoie les
erreurs dans les props. `useForm` de React les expose directement. Aucune règle
de validation n'est écrite deux fois.

### Les props partagées

Positionnées une fois dans `HandleInertiaRequests` : l'utilisateur, **le jardin
courant** (RG-43 : il doit être visible en permanence à l'écran) et le nombre de
saisies en attente de synchronisation.

---

## 5. Le test : « j'ajoute ça, je le mets où ? »

Une architecture ne vaut que si elle répond sans hésitation. Deux questions,
dans cet ordre : **quel domaine ?** puis **quelle surface ?**

### Cas 1 — Scanner un QR code pour identifier une plante

Le flux complet, de l'impression au scan, avec les fichiers exacts.

```
1. GÉNÉRER le code court            src/Jardin/Identification/AttribuerCodeCourt
   à la création de la plante       → FRU-014, unique par jardin, stable à vie (RG-13)
                                    MVP

2. ENCODER en QR                    src/Jardin/Identification/EncodeurQR
   le QR contient une URL           https://…/p/FRU-014
                                    lot 4

3. IMPRIMER la planche              src/Jardin/Identification/EtiquettesController
   d'étiquettes                     apps/bureau/pages/Etiquettes/Planche.tsx
                                    → bureau : on n'imprime pas sur le terrain
                                    MVP en clair, QR au lot 4

4. SCANNER dehors                   apps/terrain/pages/Scan.tsx        lot 4
   accès caméra, lecture du code    common/hooks/useScanner

5. RÉSOUDRE code → plante
   ├─ hors ligne                    apps/terrain/paquet/  — le paquet contient
   │                                  déjà les codes courts : simple recherche
   │                                  locale, aucun réseau
   └─ en ligne, lien direct         src/Terrain/Scan/ScanController
                                      → src/Jardin/Identification/ResoudreCodeCourt

6. OUVRIR                           terrain → Saisie.tsx pré-remplie
                                    bureau  → redirection vers la fiche plante
```

Trois choses que ce découpage rend évidentes :

- **La résolution d'un code est du métier**
  (`Jardin/Identification/ResoudreCodeCourt`), appelée aussi bien par le lien
  direct que par le contrôleur terrain. A17 s'applique : une seule
  implémentation. Elle existe **dès le MVP** — une URL tapée à la main et la
  recherche par code dans le sélecteur s'en servent déjà, sans aucun QR.
- **Le scan hors ligne ne demande aucun code serveur.** Le paquet terrain
  embarque déjà les codes courts (chapitre 1, §3) : résoudre un code scanné,
  c'est chercher une chaîne dans une liste locale. Le QR fonctionne donc au fond
  du terrain sans réseau, ce qui est précisément là où il sert.
- **L'accès caméra n'est pas du métier** — `common/hooks/useScanner`, le même
  code que dans n'importe quel projet.

**Ce qu'il ne faut surtout pas faire : créer un module `QRCode`.** Ce serait
ranger par technologie. Le QR n'est pas un métier, c'est un **moyen** d'atteindre
un métier qui existe déjà : identifier une plante. Le jour où le NFC s'ajoute
(lot 4), il se range aux mêmes endroits et réutilise `ResoudreCodeCourt` sans y
toucher.

> **A14 (rappel, énoncée au §1)** — Un module correspond à un **ensemble de
> concepts métier avec son vocabulaire**, jamais à une technologie ni à un écran.
> Test : le nom du dossier pourrait-il apparaître dans le cahier des charges
> fonctionnel ? `QRCode`, `Export`, `Notifications`, `PDF` n'y sont pas — ce sont
> des moyens, ils vivent dans `Support/` ou dans `common/`.

### Cas 2 — Toute la partie SaaS

Elle se répartit sur les deux axes, et c'est précisément ce qui montre que les
deux sont nécessaires.

| Morceau | Axe | Où |
|---|---|---|
| Comptes, jardins, adhésions, invitations | module **existant** | `src/Comptes/` — déjà là |
| Inscription, connexion, mot de passe oublié | surface bureau | `src/Comptes/Acces/` |
| Suppression RGPD, export des données | métier + écran | `src/Comptes/DonneesPersonnelles/` |
| **Facturation, abonnements, plans** | **nouveau module** | `src/Facturation/` — vocabulaire propre : abonnement, facture, échec de paiement |
| **Back-office de l'éditeur** (voir les jardins, la bibliothèque, le support) | **nouveau module + nouveau bundle** | `src/Admin/` + `apps/admin/` côté front |
| Quotas et limites | métier | `src/Comptes/` — le quota est une propriété du jardin |

Le point qui compte : **rien de tout cela n'oblige à toucher aux modules
existants.** La facturation est un module de plus ; le back-office est un module
de plus, avec son propre bundle front. Les cinq modules métier ne bougent pas.

### Les lots du CDC, rangés d'avance

| Lot | Domaine | Surface |
|---|---|---|
| **1 — Récoltes, observations** | `Journal/` (quantité) + nouveau module `Observations/` | Bureau + terrain |
| **2 — Traitements, intrants** | nouveau module `Intrants/` | Bureau + terrain |
| **3 — Potager** | `Jardin/` (cultures) + nouveau module `Rotation/` | Bureau |
| **4 — Plan du terrain** | `Jardin/` (géométrie, déjà là) | Bureau (tracer) + terrain (se situer) |
| **5 — Météo** | nouveau module `Meteo/` | Aucune — il alimente le moteur, il n'a pas d'écran propre |
| **O — Ouverture** | `Comptes/` | Bureau |

Cinq des six lots ajoutent **au plus un module**, et aucun n'oblige à toucher
aux autres. C'est le signe que le découpage tient : une nouvelle fonctionnalité
atterrit, elle ne se disperse pas.

---

## 6. Le moteur

### Signature

```php
final class Moteur
{
    /** @return Echeance[] */
    public static function calculer(
        array $regles,            // Regle[]
        Historique $historique,   // les interventions passées, en mémoire
        Decalage $decalage,       // le profil climatique, réduit à un entier
        \DateTimeImmutable $aujourdhui // jamais now() — TS-4
    ): array;
}
```

Quatre propriétés, et elles sont toutes contraignantes :

> **A3** — Le moteur est **pur** : mêmes entrées ⇒ mêmes sorties, toujours.
> Pas d'accès à la base, pas d'horloge, pas d'aléatoire, pas d'entrées-sorties.
> C'est ce qui rend D3 (calcul à la volée) fiable et TS-8 (< 1 s) atteignable.

> **A4** — Le moteur **ignore ce qu'est sa cible**. Il reçoit des règles ; que
> celles-ci appartiennent à une plante est l'affaire de l'appelant. C'est D7,
> et c'est ce qui rendra les interventions sur zone (lot 3) quasi gratuites.

> **A5** — Le moteur **ne connaît pas Eloquent**. Il reçoit des objets de
> valeur immuables, construits par le pont.

> **A6** — Le moteur **rend des échéances, il n'en persiste aucune**. La
> matérialisation (D3) est une décision de l'appelant, prise au moment d'une
> action ou d'un passage en `manquée`.

### Le pont

C'est le seul endroit qui parle les deux langues.

```php
final class CalculEcheances
{
    public function pourJardin(Jardin $jardin): Collection
    {
        $regles = $this->chargerRegles($jardin);          // 1 requête
        $historique = $this->chargerHistorique($jardin);  // 1 requête (§5)

        $echeances = Moteur::calculer(
            regles: $regles->map->versDomaine()->all(),
            historique: $historique,
            decalage: Decalage::jours($jardin->profilClimatique->decalage_jours),
            aujourdhui: $this->horloge->aujourdhui(),     // Src\Support\Horloge, D-42
        );

        return $this->rattacher($echeances, $regles);     // remet les modèles
    }
}
```

Le pont fait trois choses, et rien d'autre : **charger**, **traduire**,
**rattacher**. Aucune logique de calendrier n'a le droit d'y vivre — si une
règle métier se retrouve dans le pont, elle n'est plus testable en N1.

---

## 7. L'algorithme

### R1 — Intervalle glissant

```
intervalle_de(date) = intervalle de la période saisonnière qui contient date
                      (table IntervalleSaisonnier, bornes décalées — voir plus bas)
                      à défaut de table : regle.intervalle_jours

prochaine_R1(derniere, aujourdhui) :

  1. pas de dernière occurrence ?
        → due = aujourd'hui, état « ouverte ». Fin.

  2. i = intervalle_de(derniere)
     due = (i = 0) ? derniere : derniere + i

  3. tant que intervalle_de(due) = 0 :          ← hors saison
        due = premier jour de la période active suivante

  4. due < aujourd'hui ?                        ← en retard
        intervalle_de(aujourd'hui) > 0 ?
           oui → due = aujourd'hui
           non → due = premier jour de la période active suivante
```

**Les étapes 3 et 4 sont la correction du 24/08/2026, et elles ne sont pas
cosmétiques.** L'algorithme précédent lisait l'intervalle du mois de la dernière
occurrence et s'arrêtait là. Deux conséquences, toutes deux fausses :

- Dernière tonte le 15 novembre → intervalle de novembre (10 j) → due le
  25 novembre. Personne ne tond en décembre. **L'échéance reste due, en retard,
  de fin novembre à mars** : R1 n'a pas de fenêtre, donc ni `urgente` ni
  `manquée` ne peuvent l'éteindre. La liste du jour affiche une tonte tout
  l'hiver, et la première chose qu'un utilisateur apprend est à ignorer sa liste
  du jour. C'est le critère d'acceptation n°15, et c'était aussi le test n°5 du
  §6 du chapitre 2 — « jamais en janvier » — que l'algorithme ne pouvait pas
  satisfaire.
- Dernière tonte le 15 décembre → intervalle 0 → « aucune échéance ». La règle
  cesse alors de produire quoi que ce soit et **rien ne la réveille au
  printemps** : c'est RG-40 (« jamais zéro ») violé, et le silence est pire que
  le bruit.

Noter que l'étape 2 lit bien l'intervalle **de la dernière occurrence**, et c'est
correct : l'intervalle décrit la vitesse à laquelle l'herbe pousse *pendant* la
période écoulée. Le défaut n'était pas là — il était dans l'absence de report.

**Décalage climatique.** La **valeur** de l'intervalle n'est jamais décalée
(RG-25) : une tonte reste à sept jours partout. Les **bornes de saison**, elles,
le sont — sinon la saison de tonte s'ouvrirait en mars à Perpignan comme à
Briançon, et la justification de RG-25 serait creuse. Concrètement, une période
`mois_debut → mois_fin` est projetée en dates (1ᵉʳ du mois de début, dernier jour
du mois de fin) puis décalée, comme une fenêtre — mais sans butoir : une saison
de tonte n'a rien d'interdit.

> **A24** — Une règle R1 **hors saison ne produit jamais « aucune échéance »** :
> elle produit l'échéance reportée à l'ouverture de la saison suivante, visible
> en planning et non en tâche du jour. Une règle qui cesse de produire une
> échéance ne redémarre jamais — aucun mécanisme ne la réveille.

### R2 — Fenêtre calendaire, et le piège de l'année

Une fenêtre est stockée en couples jour/mois (`01/12 → 31/03`), pas en dates.
Il faut donc la **projeter sur une année concrète** avant toute comparaison.

> **A7** — Une fenêtre est projetée en **dates absolues** avant toute
> comparaison. Toute comparaison faite directement sur des couples jour/mois est
> fausse dès que la fenêtre traverse le 31 décembre — c'est-à-dire pour la
> taille des fruitiers à pépins, le cas le plus courant du produit.

L'algorithme :

```
traverse_annee = (mois_fin, jour_fin) < (mois_debut, jour_debut)

projeter(fenetre, annee_de_debut) :
    debut = date(annee_de_debut, mois_debut, jour_debut)
    fin   = date(annee_de_debut + (traverse_annee ? 1 : 0), mois_fin, jour_fin)
    appliquer le décalage climatique aux deux bornes
    ROGNER aux butoirs (RG-44) :                    ← ne jamais omettre
        butoir_debut projeté sur la même année ?  debut = max(debut, butoir_debut)
        butoir_fin   projeté sur la même année ?  fin   = min(fin,   butoir_fin)
    retourner [debut, fin]

prochaine_fenetre(aujourdhui) :
    candidates = [ projeter(fenetre, aujourdhui.annee - 1),
                   projeter(fenetre, aujourdhui.annee),
                   projeter(fenetre, aujourdhui.annee + 1) ]
    // celle qui contient aujourd'hui, sinon la première qui commence après
    retourner premiere(c : aujourdhui <= c.fin)
```

Trois candidates, pas deux : au 15 janvier 2027, la fenêtre en cours est celle
projetée sur **2026** (01/12/2026 → 31/03/2027). Ne projeter que l'année en
cours et la suivante ferait manquer la fenêtre ouverte — le bug exact que le
test n°1 du §6 du chapitre 2 attrape.

> **A25 — le rognage aux butoirs fait partie de la projection, pas d'une passe
> ultérieure.** RG-37 dit que la bibliothèque livre des fenêtres qui excluent
> déjà les périodes interdites, et RG-25 dit que le moteur décale les fenêtres.
> Les deux sont justes ; **ensemble, elles franchissent l'interdit**. La fenêtre
> de haie `16/08 → 15/03` devient :
>
> | Profil | Décalage | Sans butoir | Avec butoirs `16/08` / `15/03` |
> |---|---|---|---|
> | Montagne | +21 j | `06/09 → 05/04` — taille proposée le 1er avril | `06/09 → 15/03` |
> | Midi | −15 j | `01/08 → 28/02` — taille proposée le 5 août | `16/08 → 28/02` |
>
> Le butoir **rogne**, il ne déplace pas : la fenêtre montagnarde perd ses trois
> semaines de fin mars–début avril, elle ne glisse pas de trois semaines. Déplacer
> réintroduirait une contrainte R5, reportée au lot 3.
>
> C'est aussi ce qui borne le cas plus doux : on ne plante pas en décembre, même
> à Perpignan. Un décalage de quinze jours n'a pas à rendre une fenêtre
> agronomiquement absurde.
>
> **Le rognage ne doit jamais vider une fenêtre.** Si, pour l'un des cinq
> profils, il laisse moins de 14 jours ou fait sortir la période optimale, c'est
> une erreur de donnée de bibliothèque et la version n'est pas publiée (B-T13).
> Le moteur, lui, ne se défend pas : il applique. C'est la bibliothèque qui est
> testée, pas le calcul.

Puis :

```
périodicité pluriannuelle (1 an, 3 ans…) :
    dernière occurrence dans une fenêtre passée ?
      → sauter (périodicité - 1) fenêtres

état, selon aujourd'hui — **cascade ordonnée, première branche vraie l'emporte** :
    1. > fin, sans intervention   → manquée, et on calcule la fenêtre suivante
    2. < debut                    → à venir
    3. dans [fin - X, fin]        → urgente        (X = 15 par défaut)
    4. dans [optimal_debut, optimal_fin] → optimale
    5. sinon, dans [debut, fin]   → ouverte
```

**L'ordre est normatif, et il corrige deux défauts de la version précédente**,
qui énumérait les états sans dire lequel gagne :

- **Un trou.** Cerisier, fenêtre `01/08 → 30/09`, optimale `01/08 → 31/08`,
  X = 15 : du 1ᵉʳ au 14 septembre, aucune branche ne s'appliquait. La branche 5,
  en position de repli, ferme le trou.
- **Un chevauchement.** Quand l'optimale court jusqu'à la fin de la fenêtre,
  les quinze derniers jours sont à la fois `optimale` et `urgente`. L'urgence
  passe avant : elle porte l'information qui se périme, l'optimalité non.

### Fenêtres multiples (RG-36)

Une règle R2 peut porter plusieurs fenêtres — un traitement en deux passages.
Chacune est projetée indépendamment ; l'échéance retournée est **celle dont la
fenêtre est la plus proche** et qui n'a pas déjà été soldée dans sa propre
fenêtre.

> **A8** — L'unicité de RG-40 (une règle active ⇒ **une** échéance à venir)
> s'applique après ce filtrage. Une règle à trois fenêtres ne produit jamais
> trois échéances simultanées : elle en produit une, la prochaine.

### Le décalage climatique

Appliqué **à la projection**, jamais au stockage (§9 du CDC, point 9). Les
fenêtres restent écrites dans le climat de référence ; changer le profil du
jardin change les dates calculées, sans rien réécrire. Et il est **borné par les
butoirs** (RG-45, A25) : c'est la seule limite au mécanisme.

La valeur appliquée est reportée sur l'échéance matérialisée
(`decalage_climatique_applique_j`), pour pouvoir expliquer après coup une date
même si le profil a changé depuis (RG-06). Elle sert aussi à la reconnaître :
la date due d'une occurrence bouge avec le profil, et une occurrence close
l'est sous le décalage du jour ou sous celui qu'elle porte (D-99).

---

## 8. Performances : le vrai coût n'est pas le calcul

À 500 plantes × 3 règles, le moteur fait 1 500 calculs purs — quelques
millisecondes. **Le coût est dans le chargement**, et il a une forme piégeuse.

Le moteur a besoin, pour chaque règle, de **la dernière intervention du même
type sur la même cible**. Écrit naïvement, c'est 1 500 requêtes.

> **A9** — L'historique se charge en **une seule requête**, agrégée. Sous
> PostgreSQL, `DISTINCT ON` fait exactement ça :

```sql
SELECT DISTINCT ON (plante_id, type_intervention_id)
       plante_id, type_intervention_id, faite_le
FROM   interventions
WHERE  jardin_id = ?
ORDER  BY plante_id, type_intervention_id, faite_le DESC;
```

Une ligne par couple (cible, type), la plus récente. C'est la requête la plus
sollicitée de l'application — celle du tableau de bord et de la vue terrain.

**Index nécessaire :** `(jardin_id, plante_id, type_intervention_id, faite_le DESC)`.

> **A10** — Les règles R3 et R4 (lots 1 et 2) auront besoin de **plus d'une**
> occurrence passée — l'intervalle minimal entre deux traitements, le
> déclenchement par événement. `Historique` doit donc être conçu dès maintenant
> comme un **objet interrogeable** (« les N dernières de ce type »), même s'il
> ne contient qu'une date par couple au MVP. Le remplacer plus tard changerait
> la signature du moteur.

---

## 9. Le cloisonnement en pratique — et ses trois trous

D1 est la décision irrattrapable, et elle applique **RG-21** : le filtrage est
imposé par défaut, jamais laissé à la discipline de chaque requête. En Laravel,
le mécanisme naturel est le **global scope** :

```php
// Sur chaque modèle métier
protected static function booted(): void
{
    static::addGlobalScope(new JardinScope);
    static::creating(fn ($m) => $m->jardin_id ??= JardinCourant::id());
}
```

C'est confortable et ça couvre l'immense majorité des cas. **Mais il faut
savoir ce que ça ne couvre pas**, sinon la garantie est illusoire :

| Trou | Pourquoi | Parade |
|---|---|---|
| `DB::table('plantes')` | Le query builder ne passe pas par Eloquent, donc pas par le scope | **Interdit dans le code applicatif.** Vérifié par un test d'architecture |
| Les jobs en file | Un job s'exécute hors requête HTTP : pas de session, donc pas de jardin courant | Le job **porte son `jardin_id`** dans son payload et repositionne le contexte à l'exécution |
| Les commandes Artisan | Idem, pas de session | Toute commande touchant des données métier prend un jardin en argument, explicitement |

> **A11** — `JardinCourant` est un **contexte explicite**, pas une lecture de la
> session. Il est positionné par le middleware HTTP, par le job à son
> démarrage, par la commande Artisan à partir de son argument. Un code qui a
> besoin du jardin courant sans qu'il ait été positionné doit **lever une
> exception**, jamais retourner `null` — un `null` silencieux désactive le
> filtre et rend tout visible.

### Faut-il aller jusqu'à la sécurité au niveau des lignes ?

PostgreSQL sait appliquer le filtrage **dans la base** (Row Level Security),
ce qui couvre même `DB::table` et les erreurs d'inattention. C'est la garantie
la plus forte.

**Recommandation : pas au MVP, oui avant l'ouverture.** Tant que le seul compte
est celui du propriétaire du serveur, le global scope plus l'interdiction de
`DB::table` suffisent. Le
jour du lot Ouverture — quand des données de tiers arrivent — la RLS devient un
filet qui justifie son coût. Le modèle ne change pas : c'est une migration qui
ajoute des politiques, pas une refonte.

---

## 10. Où le moteur est appelé

| Moment | Ce qui se passe |
|---|---|
| Tableau de bord (bureau) | Calcul complet du jardin, affichage groupé par zone |
| Vue terrain, construction du paquet (§3 du chapitre 1) | Calcul complet, filtré aux 30 prochains jours. Chaque ligne est désignée par `(regle_id, due_le)` — H11 : elle n'existe dans aucune table |
| Après enregistrement d'une intervention | Recalcul **des règles de la cible concernée** seulement |
| Fiche d'une plante | Recalcul de ses règles seulement |

> Aucun recalcul planifié, aucune tâche de fond. Le passage d'une échéance en
> `manquée` (D3) est détecté **au calcul suivant**, pas par un travail
> nocturne : puisque le moteur est déterministe, l'état est toujours celui de
> la date du calcul. Une échéance ratée en mars sera vue comme manquée dès la
> première ouverture d'avril, et matérialisée à ce moment-là.

C'est ce qui évite d'avoir à faire tourner un ordonnanceur fiable sur un
serveur ordinaire — cohérent avec §8.6 du CDC.

---

## 11. Autorisations : voir n'est pas pouvoir

Deux mécanismes distincts, souvent confondus, et qui ne protègent pas la même
chose.

| | Ce que ça contrôle | Où | Quand |
|---|---|---|---|
| **Cloisonnement** (D1) | Ce qu'on **voit** — un jardin ne voit jamais un autre | Global scope, automatique | Dès le MVP |
| **Autorisation** (policy) | Ce qu'on a le droit de **faire** dans son propre jardin | Policy explicite, par action | Structure au MVP, utile au lot 1 bis |

Le premier est automatique et silencieux ; le second est explicite et se
demande. Un membre invité voit tout le jardin — c'est bien le même jardin —
mais ne peut pas modifier une règle ni archiver une plante (§2.1 du CDC).

> **A18** — Une policy vit **dans le module de ce qu'elle protège**
> (`Jardin/Policies/PlantePolicy`), jamais dans un dossier central. Elle est
> enregistrée explicitement, l'auto-découverte de Laravel ne trouvant pas cet
> emplacement (§2).

Au MVP, les policies sont triviales — un seul compte, propriétaire de tout.
Elles existent quand même, et pour une raison précise : **les écrire après coup
signifie relire chaque contrôleur** pour retrouver ce qui aurait dû être
protégé. Les poser vides coûte quelques minutes ; les rajouter coûte un audit.

Côté front, aucune règle n'est réimplémentée : le contrôleur envoie des
booléens calculés (`peut.modifierRegles`), le composant les affiche. Une
autorisation vérifiée uniquement en TypeScript n'est pas une autorisation.

---

## 12. Le traitement des images

Le chapitre 1 impose des miniatures 160 px WebP à URL immuable (H3) sans dire
qui les fabrique. Voici la chaîne complète.

```
réception d'une photo (bureau ou terrain)
   ↓  validation : type, poids, dimensions
   ↓  stockage de l'original sur disque
   ↓  ─── job en file ───────────────────────────
   ↓     miniature   160 px  WebP   ← le paquet terrain
   ↓     affichage  1200 px  WebP   ← la fiche de plante
   ↓  empreinte du contenu dans le nom de fichier
   ↓  mise à jour de la ligne PHOTO
```

**Trois points qui ne vont pas de soi :**

> **A19** — La génération des dérivées se fait **en file d'attente**, jamais
> pendant la requête. Une photo d'iPhone met plusieurs secondes à être
> redimensionnée ; le faire en synchrone bloquerait la saisie terrain, qui doit
> rester à trois gestes.

> **A20** — Le job **porte son `jardin_id` dans son payload** et repositionne
> `JardinCourant` à son démarrage. C'est l'application directe d'A11 : un job
> s'exécute hors requête HTTP, donc sans session, donc sans jardin courant. Un
> job qui l'oublie écrit dans le vide ou, pire, lit ce qu'il ne devrait pas.

> **A21** — Le nom de fichier contient une **empreinte du contenu**. C'est ce
> qui permet au service worker de mettre les miniatures en cache
> définitivement (H3) : une image ne change jamais, elle est remplacée par une
> autre URL. Sans ça, il faudrait gérer l'invalidation du cache sur un
> téléphone hors ligne — un problème sans solution propre.

**Le reste de la chaîne :**

- **Arborescence par jardin** : `photos/{jardin}/{annee}/{empreinte}.webp`.
  Cela rend la purge RGPD (D6) triviale — un dossier à supprimer — et évite un
  répertoire à 100 000 fichiers.
- **Tant que les dérivées n'existent pas**, la fiche affiche l'original
  redimensionné par le navigateur, et le paquet terrain omet la plante des
  miniatures à précharger. Rien ne bloque, rien n'attend.
- **Les orphelines** : une photo dont l'intervention a échoué à s'enregistrer
  (H10) reste sur le disque. Une commande de nettoyage, lancée à la main au
  début, supprime celles qui ne sont référencées nulle part depuis plus de
  7 jours.

---

## 13. Erreurs et observabilité

Un service maintenu par une seule personne doit **dire quand il casse**, sans
imposer d'infrastructure. La contrainte §8.6 du CDC — serveur Linux ordinaire,
pas de service managé — écarte les outils habituels.

### Les trois familles d'erreurs

| Famille | Où elle se voit | Ce qu'il faut |
|---|---|---|
| **Erreur serveur** (500, exception) | Journal fichier | Rotation quotidienne, et un courriel pour les erreurs non gérées |
| **Erreur de synchronisation terrain** | Nulle part, par défaut | **Le vrai problème** — voir ci-dessous |
| **Anomalie métier** (échéance déjà soldée, C1) | Ni erreur ni silence | Consignée, visible dans l'historique, jamais une exception |

> **A22** — Une mutation en **échec définitif** (422) remonte au serveur son
> contexte au moment du rejeu suivant : le payload refusé, le message d'erreur,
> la version de l'application, le nombre de tentatives. Sans ça, un bug de
> synchronisation est **indiagnosticable** : il s'est produit dehors, sur un
> téléphone, sans console ouverte, et l'utilisateur ne saura pas le décrire.

C'est la contrepartie de H8 : puisqu'on ne peut pas compter sur le rejeu en
arrière-plan, on ne peut pas non plus compter sur l'utilisateur pour signaler ce
qui a échoué. Le client doit le raconter lui-même.

### Ce qu'on ne fait pas

Pas d'APM, pas de service tiers de collecte, pas de traçage distribué. Le
volume ne le justifie pas et l'hébergement l'écarte. **Un fichier de log qu'on
sait lire vaut mieux qu'un tableau de bord qu'on ne regarde jamais.**

Le seul point qui mérite une alerte active : **l'espace disque**, parce que les
photos le remplissent en silence et que le service s'arrête net quand il est
plein.

---

## 14. Décisions de ce chapitre

| # | Décision | Enjeu |
|---|---|---|
| A1 | Deux niveaux de rangement : **le domaine métier**, durable — puis **la capacité**, un ensemble de cas d'usage | Sans le premier niveau, une prolifération de micro-tranches sans vue d'ensemble |
| A2 | `src/Echeances/Moteur/` n'importe rien de Laravel, vérifié automatiquement | La pureté s'érode sans garde-fou |
| A3 | Le moteur est pur — pas de base, pas d'horloge, pas d'aléa | D3, TS-4, TS-8 |
| A4 | Le moteur ignore ce qu'est sa cible | D7, lot 3 |
| A5 | Le moteur ne connaît pas Eloquent | Testabilité N1 |
| A6 | Le moteur ne persiste rien | D3 |
| A7 | Une fenêtre est projetée en dates absolues, sur **trois** années candidates | Le piège n°1 du produit |
| A8 | Une règle à fenêtres multiples produit une seule échéance à venir | RG-40 |
| A9 | L'historique se charge en une requête agrégée (`DISTINCT ON`) | 1 500 requêtes sinon |
| A10 | `Historique` est un objet interrogeable dès le MVP | R3 et R4 en auront besoin |
| A11 | `JardinCourant` est explicite et lève une exception s'il est absent | Un `null` silencieux désactive D1 |
| A12 | Les modules reprennent les cinq blocs du modèle de données, plus `Terrain` et `Support` | Découpage antérieur au code, donc stable |
| A13 | Bureau et terrain sont **deux bundles distincts** — *suspendue en V1 (un seul bundle, §3)* | Le cache du service worker ne doit pas porter le code bureau |
| A14 | Un module = des concepts métier, jamais une technologie | Empêche les modules `QRCode`, `Export`, `Notifications` |
| A15 | Laravel standard à l'intérieur des modules ; **une seule frontière stricte**, le moteur | Maintenabilité en solo |
| A16 | Chaque module déclare ses propres routes | Ajouter une fonctionnalité ne modifie aucun fichier existant |
| A17 | Une action métier a **un seul point d'implémentation**, quel que soit le point d'entrée | Deux chemins divergents produiraient des comportements différents selon l'appareil |
| A18 | Une policy vit dans le module de ce qu'elle protège, enregistrée explicitement | Les écrire après coup impose un audit de tous les contrôleurs |
| A19 | Les dérivées d'images sont générées en file d'attente | Le redimensionnement bloquerait la saisie à trois gestes |
| A20 | Un job porte son `jardin_id` et repositionne `JardinCourant` | Un job n'a ni session ni jardin courant (A11) |
| A21 | Le nom de fichier d'une image contient une empreinte du contenu | Permet un cache définitif côté service worker, sans invalidation |
| A22 | Une mutation en échec définitif remonte son contexte au rejeu suivant — *suspendue en V1* | Un bug de synchronisation est sinon indiagnosticable |
| A23 | Les contraintes terrain (48 px, contraste) sont portées par les composants | Une règle appliquée à la main est oubliée un jour |
| A24 | Une règle R1 hors saison **reporte** son échéance, elle n'en supprime jamais | Une règle qui cesse de produire ne redémarre jamais (RG-40) |
| A25 | Le **rognage aux butoirs** fait partie de la projection d'une fenêtre | RG-37 et RG-25 sont justes séparément et franchissent l'interdit ensemble |

## 15. Ce que ce chapitre ne traite pas

Volontairement hors périmètre, avec l'endroit où ça sera traité :

| Sujet | Où | Pourquoi pas ici |
|---|---|---|
| Schéma physique, index, migrations | **Chapitre 4** | Annoncé |
| Déploiement et exploitation | **Chapitre 5** | Annoncé |
| Environnement de développement, outillage, intégration et livraison continues, travail des agents | **Chapitre 6** | Écrit le 19/10/2026, avec le passage au développement par IA (CDC §1.5) |
| Découpage en chantiers | **Chapitre 7 — à écrire** | N'influence pas la structure du code. Les jalons MVP et V1 sont posés au CDC §6 |
| Système de design, bibliothèque de composants | **Tranché** — Tailwind + bibliothèque maison, §3 | — |
| Accessibilité (contraste, cibles 48 px, plein soleil) | **Avec la maquette** | §8.5 du CDC pose les exigences ; l'architecture ne les contraint pas |
| Langues | Non traité | Le CDC dit « interface en français » ; à rouvrir seulement si le service s'ouvre hors francophonie (Q12) |

### Sur la maquette

Il est utile de séparer ce qui en dépend de ce qui n'en dépend pas :

| N'en dépend pas — déjà décidé | En dépend — à faire après |
|---|---|
| La structure `apps/` · `modules/` · `common/` | Le découpage en composants |
| Les deux bundles distincts (A13) | Le nombre exact de pages |
| Le mécanisme Inertia : props, `lazy`, `only` | La composition de chaque écran |
| La stratégie hors ligne | Les états visuels d'une échéance |
| Les types générés depuis PHP | Le système de couleurs et d'espacements |

**Une décision est à prendre avant la maquette, pas après** : la bibliothèque de
composants et le socle de style. Elle contraint le dessin autant qu'elle en
découle, et elle doit intégrer trois exigences du CDC qui ne sont pas
négociables — lisible en plein soleil, cibles tactiles ≥ 48 px, utilisable avec
des gants (§8.5). Une maquette dessinée sans ces contraintes produira des écrans
inutilisables sur le terrain, et il sera trop tard pour les rattraper.

---

## 16. Points ouverts

| # | Question | Quand |
|---|---|---|
| O5 | Générateur de types PHP → TypeScript : lequel | **Tranchée** au sprint 0h : `spatie/laravel-typescript-transformer`, attribut `#[TypeScript]` (D-77) |
| O6 | Seuil de l'état `urgente` : 15 jours avant la fin de fenêtre, ou proportionnel à sa durée | À l'usage — une fenêtre de 4 mois et une de 3 semaines n'ont pas la même urgence. La **priorité** entre `urgente` et `optimale` est tranchée (§7, cascade) ; seule la valeur de X reste ouverte |
| O7 | Le tableau de bord recalcule tout à chaque affichage : acceptable jusqu'à quel volume | À mesurer à 500 plantes, pas à supposer |
| O8 | Row Level Security PostgreSQL | Avant le lot Ouverture (§6) |

---

## 17. Références

Cette structure n'est pas inventée pour l'occasion. Elle reprend des pratiques
documentées, adaptées à la taille de ce projet.

- [Laravel Beyond CRUD](https://laravel-beyond-crud.com/) — Brent Roose (Spatie). La référence pour structurer une application Laravel qui dépasse le CRUD : séparation domaine / application, actions plutôt que services, `src/` plutôt que `app/`.
- [How to structure the frontend of a Laravel Inertia React application](https://spatie.be/blog/how-to-structure-the-frontend-of-a-laravel-inertia-react-application) — Spatie. L'origine directe du découpage `apps/` · `modules/` · `common/` du §3, y compris le cas des applications à surfaces multiples.
- [Package by feature, not layer](http://www.javapractices.com/topic/TopicAction.do?Id=205) — l'énoncé le plus court du principe : un arbre de dossiers doit décrire ce que l'application fait, pas les technologies qu'elle emploie.
- [Vertical Slice Architecture](https://milanjovanovic.tech/blog/vertical-slice-architecture) — l'école du rangement par cas d'usage, dont vient le principe « minimiser le couplage entre tranches, maximiser le couplage dans une tranche ». Sa mise en pratique recommande les **deux niveaux** retenus ici : capacité métier, puis cas d'usage.
- [Pages — Inertia.js](https://inertiajs.com/pages) — la documentation des pages, des props et des rechargements partiels (`only`, `lazy`, `preserveState`).
