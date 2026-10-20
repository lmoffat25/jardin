# Document technique — Chapitre 4 : schéma physique et migrations

Version 0.2 — 24/08/2026
Suite de `technique-03-architecture.md` (v1.0). Décisions **S1 à S16**.

**Cible :** PostgreSQL. Le modèle conceptuel est dans `modele-de-donnees.md`
(v0.6) ; ce chapitre le traduit en tables, types, contraintes et index.

> Les extraits SQL sont des **esquisses de référence**, pas des migrations
> exécutées. Elles seront écrites en Laravel (`Schema::create`) ; le SQL est ici
> parce qu'il dit précisément ce qu'on veut, sans ambiguïté de traduction.

---

## 1. Conventions

> **S1** — **Tout est en français**, tables comme colonnes. Le vocabulaire du
> CDC est normatif (§3) et il est en français : mélanger `plants` et `plantes`,
> ou `due_date` et `fenetre_fin`, crée une traduction mentale permanente entre
> le document et le code. Sans accents ni cédilles dans les identifiants —
> `echeances`, pas `échéances`.

| Élément | Convention | Exemple |
|---|---|---|
| Table | pluriel, minuscules, `snake_case` | `interventions`, `regles_reference` |
| Clé primaire | `id` | |
| Clé étrangère | `<table_au_singulier>_id` | `plante_id`, `jardin_id` |
| Booléen | préfixe `est_` ou participe | `est_active`, `accueille_cultures` |
| Date | participe passé + `_le` | `faite_le`, `archivee_le` |
| Table de liaison | les deux noms, ordre alphabétique | `adhesions` (nommée, car porteuse) |

---

## 2. Types : les six choix qui comptent

### S2 — Les identifiants sont des `uuid` natifs

```sql
id uuid PRIMARY KEY
```

Décision D2 : ils sont générés côté client pour permettre la création hors ligne
(H5). PostgreSQL a un type `uuid` natif de 16 octets — ne jamais le stocker en
`varchar(36)`, qui double la taille et ralentit les index.

**Exception :** les tables de référence partagées (`especes`,
`familles_botaniques`, `types_intervention`, `profils_climatiques`,
`regles_reference`) gardent des **entiers séquentiels**. Elles ne sont jamais
créées hors ligne, et leurs identifiants apparaissent dans des jeux de données
de démarrage qu'on veut lisibles.

### S3 — Les dates d'intervention sont des `date`, pas des `timestamp`

```sql
faite_le date NOT NULL
```

Le modèle l'impose : l'heure n'a aucun sens pour une intervention de jardin et
introduit des décalages d'un jour selon le fuseau. `saisie_le` et
`synchronisee_le`, en revanche, sont des `timestamptz` : ce sont des faits
techniques, pas métier.

### S4 — Les fenêtres sont des couples jour/mois en `smallint`

```sql
debut_jour  smallint NOT NULL CHECK (debut_jour BETWEEN 1 AND 31),
debut_mois  smallint NOT NULL CHECK (debut_mois BETWEEN 1 AND 12),
```

Jamais des `date` : une fenêtre n'appartient à aucune année, c'est le moteur qui
la projette (A7). Stocker `2026-12-01` obligerait à la réécrire chaque année.

### S5 — Les énumérations sont des `varchar` + `CHECK`, pas des `ENUM` PostgreSQL

```sql
source varchar(10) NOT NULL CHECK (source IN ('gps','manuel','plan'))
```

*(L'exemple portait auparavant sur `echeances.etat`, ce qui induisait en erreur :
cette colonne n'accepte que les états **matérialisés**, jamais `ouverte` ni
`optimale`. Voir S10.)*

Un type `ENUM` PostgreSQL est plus propre sur le papier, mais **ajouter une
valeur exige un `ALTER TYPE`** qui ne peut pas tourner dans une transaction
avant PostgreSQL 12, et qui reste pénible à annuler. Ce projet ajoutera des
états (les règles R3, R4, R5 amèneront `bloquee`) : la contrainte `CHECK` se
modifie sans douleur.

### S6 — Le JSON reste marginal et jamais requêté

Deux usages, et deux seulement : `regles_reference.parametres` (donnée d'import
du curateur, format évolutif — D4) et `geometries.coordonnees` (D5). Aucune
requête ne filtre sur leur contenu au MVP. Le jour où il le faudrait, c'est le
signe qu'il fallait des colonnes.

### S7 — Pas de `deleted_at` ; l'archivage est métier

RG-02 : une plante est **archivée**, pas supprimée. C'est un état métier
(`etat = 'archivee'`), visible dans l'historique, avec un motif. Le
*soft delete* de Laravel ferait disparaître les lignes des requêtes par défaut —
exactement l'inverse de ce que RG-02 demande.

Les vraies suppressions existent, mais elles sont rares et explicites : purge
RGPD (D6), nettoyage des photos orphelines.

---

## 3. Les tables du MVP

**Quatorze tables métier** — `utilisateurs`, `jardins`, `adhesions`, `zones`,
`plantes`, `compteurs_codes_courts`, `geometries`, `regles`, `fenetres`,
`intervalles_saisonniers`, `echeances`, `interventions`, `photos`,
`fonds_de_plan` — dont **douze portent un `jardin_id NOT NULL`** (toutes sauf
`utilisateurs` et `jardins`, qui est le jardin lui-même). Et **six tables de
référence** : `profils_climatiques`, `familles_botaniques`, `especes`,
`types_intervention` (dont `jardin_id` est nullable : `NULL` = type fourni),
`regles_reference` et `sources_regle`. Le test V27 du plan relit cette liste
dans le catalogue de PostgreSQL (décisions D-02, D-03, D-39 du plan).

### 3.1 Socle

```sql
CREATE TABLE utilisateurs (
    id                    uuid PRIMARY KEY,
    courriel              varchar(255) NOT NULL UNIQUE,
    mot_de_passe_hache    varchar(255) NOT NULL,
    nom_affiche           varchar(120),
    cree_le               timestamptz NOT NULL DEFAULT now(),
    derniere_connexion_le timestamptz,
    supprime_le           timestamptz
);

CREATE TABLE profils_climatiques (          -- référence, 5 lignes
    id              smallint PRIMARY KEY,
    libelle         varchar(80)  NOT NULL,
    decalage_jours  smallint     NOT NULL,
    ordre_affichage smallint     NOT NULL
);

CREATE TABLE communes (                     -- référence, importée (RG-52, D-66)
    code_insee            char(5)  PRIMARY KEY,
    nom                   varchar(120) NOT NULL,
    nom_recherche         varchar(120) NOT NULL,  -- sans accents ni casse
    codes_postaux         varchar(60)  NOT NULL,
    altitude_m            integer  NOT NULL,
    station               varchar(80)  NOT NULL,
    ecart_c               numeric(4,1) NOT NULL,
    decalage_jours        smallint NOT NULL,
    profil_propose_id     smallint NOT NULL REFERENCES profils_climatiques(id)
);
CREATE INDEX communes_recherche ON communes (nom_recherche varchar_pattern_ops);

CREATE TABLE jardins (
    id                    uuid PRIMARY KEY,
    proprietaire_id       uuid     NOT NULL REFERENCES utilisateurs(id),
    nom                   varchar(120) NOT NULL,
    profil_climatique_id  smallint NOT NULL REFERENCES profils_climatiques(id),
    commune_insee         char(5) REFERENCES communes(code_insee),
    latitude              numeric(9,6),
    longitude             numeric(9,6),
    altitude_m            integer,
    surface_m2            integer,
    quota_photos_mo       integer  NOT NULL DEFAULT 2048,
    cree_le               timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE adhesions (
    id              uuid PRIMARY KEY,
    utilisateur_id  uuid NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    jardin_id       uuid NOT NULL REFERENCES jardins(id)      ON DELETE CASCADE,
    role            varchar(20) NOT NULL CHECK (role IN ('proprietaire','membre')),
    invite_le       timestamptz,
    accepte_le      timestamptz,
    UNIQUE (utilisateur_id, jardin_id)
);
```

`profil_climatique_id` est **NOT NULL** : c'est RG-23, sans lui le moteur
produit des dates fausses dès le premier jour.

### 3.2 Terrain

```sql
CREATE TABLE zones (
    id               uuid PRIMARY KEY,
    jardin_id        uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    zone_parente_id  uuid REFERENCES zones(id),        -- lot 3, non alimenté
    nom              varchar(120) NOT NULL,
    archivee_le      timestamptz,                       -- décision D-04 du plan
    cree_le          timestamptz NOT NULL DEFAULT now(),
    UNIQUE (jardin_id, nom)
);

CREATE TABLE plantes (
    id                   uuid PRIMARY KEY,
    jardin_id            uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    zone_id              uuid NOT NULL REFERENCES zones(id),
    code_court           varchar(12) NOT NULL,
    nom                  varchar(120) NOT NULL,
    repere               varchar(200),
    espece_id            integer REFERENCES especes(id),   -- créée en §3.3, migration 2
    variete              varchar(120),
    variante             varchar(60),                      -- RG-51, variante de conduite
    plantee_le           date,
    age_estime_ans       smallint,
    cycle                varchar(12) NOT NULL DEFAULT 'perenne'
                         CHECK (cycle IN ('perenne','annuelle')),
    fin_le               date,                          -- lot 3
    plante_origine_id    uuid REFERENCES plantes(id),   -- scission, RG-12
    photo_couverture_id  uuid,                          -- FK ajoutée après photos
    sante                varchar(12)                    -- lot 1
                         CHECK (sante IS NULL OR sante IN ('bonne','surveiller','degradee')),
    etat                 varchar(12) NOT NULL DEFAULT 'active'
                         CHECK (etat IN ('active','archivee')),
    archivee_le          timestamptz,
    motif_archivage      varchar(200),
    notes                text,
    cree_le              timestamptz NOT NULL DEFAULT now(),

    UNIQUE (jardin_id, code_court),
    UNIQUE (zone_id, nom)
);

-- Compteur du code court, par jardin et par préfixe (S8, décision D-03 du plan).
CREATE TABLE compteurs_codes_courts (
    jardin_id  uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    prefixe    varchar(4) NOT NULL,
    dernier    integer NOT NULL,
    PRIMARY KEY (jardin_id, prefixe)
);

CREATE TABLE geometries (
    id           uuid PRIMARY KEY,
    jardin_id    uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    plante_id    uuid NOT NULL UNIQUE REFERENCES plantes(id) ON DELETE CASCADE,
    forme        varchar(10) NOT NULL CHECK (forme IN ('point','ligne','polygone')),
    coordonnees  jsonb NOT NULL,
    precision_m  smallint,
    source       varchar(10) NOT NULL CHECK (source IN ('gps','manuel','plan')),
    relevee_le   timestamptz NOT NULL
);
```

> **S15** — L'unicité d'une règle porte sur les **règles actives seulement**.
> Une `UNIQUE (plante_id, type_intervention_id)` sur la table entière — ce que
> portait la première version de ce schéma — rend le versionnement **impossible**
> : corriger une règle ne pourrait qu'écraser la précédente.
>
> Or RG-08 promet un « historique des versions de règle », le §9 point 3 du CDC
> interdit de « réécrire l'histoire des échéances passées », et une échéance
> matérialisée porte son explication (RG-06). Si la règle qui l'a produite a été
> écrasée, cette explication devient invérifiable — et ce sont précisément les
> échéances passées qui donnent de la valeur à l'outil au bout de trois saisons
> (RG-11). Voir D10 du modèle de données.

> **S8** — `UNIQUE (jardin_id, code_court)` — le code court est unique **par
> jardin**, pas globalement (RG-13). Deux jardins peuvent chacun avoir leur
> `FRU-014`. Et il n'est **jamais réattribué**, y compris après archivage : des
> étiquettes physiques en dépendent. La contrainte ne le garantit pas — c'est
> le générateur qui doit s'appuyer sur un compteur par jardin et par préfixe,
> jamais sur `COUNT(*)`.

### 3.3 Référentiel

```sql
CREATE TABLE familles_botaniques (
    id                integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    nom               varchar(80) NOT NULL UNIQUE,
    delai_retour_ans  smallint                       -- lot 3
);

CREATE TABLE especes (
    id                 integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    famille_id         integer REFERENCES familles_botaniques(id),
    cle                varchar(80) NOT NULL UNIQUE,      -- clé du fichier de bibliothèque
    nom_commun         varchar(120) NOT NULL,
    nom_latin          varchar(120),
    categorie          varchar(20) NOT NULL,
    cycle              varchar(12) NOT NULL DEFAULT 'perenne',
    exposition         varchar(60),
    zone_rusticite     varchar(20),
    hauteur_adulte_cm  integer,
    periode_floraison  varchar(60),
    description        text
);

CREATE TABLE types_intervention (
    id                integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    jardin_id         uuid REFERENCES jardins(id) ON DELETE CASCADE,  -- NULL = fourni
    libelle           varchar(80) NOT NULL,
    famille           varchar(20) NOT NULL,
    demande_quantite  boolean NOT NULL DEFAULT false,   -- lot 1
    demande_produit   boolean NOT NULL DEFAULT false    -- lot 2
);

-- Un libellé unique parmi les types fournis, et dans chaque jardin (D-38 du plan).
CREATE UNIQUE INDEX types_intervention_fournis_libelle
    ON types_intervention (libelle) WHERE jardin_id IS NULL;
ALTER TABLE types_intervention ADD UNIQUE (jardin_id, libelle);

CREATE TABLE regles_reference (
    id                    integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    espece_id             integer NOT NULL REFERENCES especes(id),
    variante              varchar(60),                  -- RG-51 ; nul = toute l'espèce
    type_intervention_id  integer NOT NULL REFERENCES types_intervention(id),
    type_regle            varchar(4) NOT NULL CHECK (type_regle IN ('R1','R2')),
    parametres            jsonb NOT NULL,
    cle                   varchar(120) NOT NULL,        -- clé de la règle dans le fichier
    confiance             varchar(12) NOT NULL
                          CHECK (confiance IN ('verifiee','indicative')),
    version               smallint NOT NULL DEFAULT 1,
    remplacee_le          timestamptz,
    UNIQUE (cle, version)
);

CREATE UNIQUE INDEX regles_reference_en_vigueur
    ON regles_reference (cle) WHERE remplacee_le IS NULL;

CREATE TABLE sources_regle (
    id                  integer PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    regle_reference_id  integer NOT NULL REFERENCES regles_reference(id) ON DELETE CASCADE,
    url                 varchar(500) NOT NULL,
    libelle             varchar(200),
    consultee_le        date NOT NULL,
    UNIQUE (regle_reference_id, url)
);
-- Plus un déclencheur de contrainte DEFERRABLE INITIALLY DEFERRED :
-- une règle 'verifiee' a au moins deux sources au moment du commit.
```

> **S9** — `confiance` est **NOT NULL sans valeur par défaut**, et une règle
> `verifiee` **ne s'enregistre pas sans deux sources** dans `sources_regle`
> (B2 ; décision D-39 du plan, qui remplace la colonne `source_url` unique et
> son CHECK de la version précédente de ce chapitre). RG-31 : une règle sans
> niveau de confiance déclaré est une supposition déguisée en certitude ; une
> règle qui se dit vérifiée sans dire par quoi est pire, parce qu'elle est
> crédible.
>
> Le protocole de bibliothèque (B1) annonçait cette contrainte ; elle n'existait
> pas jusqu'au 24/08/2026 — seul le test B-T8 y veillait, c'est-à-dire une
> vérification qui tourne *après* l'écriture au lieu de la refuser.
>
> Une règle `indicative` cite au moins une source (B2, vérifié par le test de
> structure de la bibliothèque) ; seule une `verifiee` en exige deux en base.

### 3.4 Moteur

```sql
CREATE TABLE regles (
    id                    uuid PRIMARY KEY,
    jardin_id             uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    plante_id             uuid NOT NULL REFERENCES plantes(id) ON DELETE CASCADE,
    type_intervention_id  integer NOT NULL REFERENCES types_intervention(id),
    regle_reference_id    integer REFERENCES regles_reference(id),
    type_regle            varchar(4) NOT NULL CHECK (type_regle IN ('R1','R2')),
    intervalle_jours      smallint,        -- R1
    periodicite_annees    smallint,        -- R2
    confiance             varchar(12) NOT NULL
                          CHECK (confiance IN ('verifiee','indicative')),
    origine               varchar(12) NOT NULL          -- décision D-51 du plan
                          CHECK (origine IN ('bibliotheque','ajustee','libre')),
    est_active            boolean NOT NULL DEFAULT true,
    version               smallint NOT NULL DEFAULT 1,
    remplacee_le          timestamptz,
    creee_le              timestamptz NOT NULL DEFAULT now(),

    CHECK (
        (type_regle = 'R1' AND intervalle_jours IS NOT NULL) OR
        (type_regle = 'R2' AND periodicite_annees IS NOT NULL)
    ),
    CHECK (est_active = false OR remplacee_le IS NULL)
);

-- Une seule règle ACTIVE par couple (plante, type) — les versions retirées
-- restent en table. Index unique partiel, pas contrainte UNIQUE : c'est la
-- seule forme qui exprime « unique parmi un sous-ensemble ».
CREATE UNIQUE INDEX idx_regles_active_unique
    ON regles (plante_id, type_intervention_id)
    WHERE est_active;

CREATE TABLE fenetres (
    id                  uuid PRIMARY KEY,
    regle_id            uuid NOT NULL REFERENCES regles(id) ON DELETE CASCADE,
    debut_jour          smallint NOT NULL CHECK (debut_jour BETWEEN 1 AND 31),
    debut_mois          smallint NOT NULL CHECK (debut_mois BETWEEN 1 AND 12),
    fin_jour            smallint NOT NULL CHECK (fin_jour BETWEEN 1 AND 31),
    fin_mois            smallint NOT NULL CHECK (fin_mois BETWEEN 1 AND 12),
    optimal_debut_jour  smallint, optimal_debut_mois smallint,
    optimal_fin_jour    smallint, optimal_fin_mois   smallint,

    -- RG-44 : bornes que le décalage climatique ne franchit jamais.
    -- Nullables : la plupart des fenêtres n'en ont pas besoin.
    butoir_debut_jour   smallint CHECK (butoir_debut_jour BETWEEN 1 AND 31),
    butoir_debut_mois   smallint CHECK (butoir_debut_mois BETWEEN 1 AND 12),
    butoir_fin_jour     smallint CHECK (butoir_fin_jour   BETWEEN 1 AND 31),
    butoir_fin_mois     smallint CHECK (butoir_fin_mois   BETWEEN 1 AND 12),

    libelle             varchar(80),
    ordre               smallint NOT NULL DEFAULT 1,

    -- un butoir se donne en entier ou pas du tout
    CHECK ((butoir_debut_jour IS NULL) = (butoir_debut_mois IS NULL)),
    CHECK ((butoir_fin_jour   IS NULL) = (butoir_fin_mois   IS NULL))
);

CREATE TABLE intervalles_saisonniers (
    id                uuid PRIMARY KEY,
    regle_id          uuid NOT NULL REFERENCES regles(id) ON DELETE CASCADE,
    mois_debut        smallint NOT NULL CHECK (mois_debut BETWEEN 1 AND 12),
    mois_fin          smallint NOT NULL CHECK (mois_fin  BETWEEN 1 AND 12),
    intervalle_jours  smallint NOT NULL CHECK (intervalle_jours >= 0)
);

CREATE TABLE echeances (              -- matérialisées seulement (D3)
    id                             uuid PRIMARY KEY,
    jardin_id                      uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    regle_id                       uuid NOT NULL REFERENCES regles(id) ON DELETE CASCADE,
    plante_id                      uuid NOT NULL REFERENCES plantes(id) ON DELETE CASCADE,
    due_le                         date NOT NULL,
    fenetre_debut                  date,
    fenetre_fin                    date,
    optimal_debut                  date,
    optimal_fin                    date,
    decalage_climatique_applique_j smallint NOT NULL DEFAULT 0,
    etat                           varchar(12) NOT NULL
                                   CHECK (etat IN ('manquee','suspendue','soldee')),
    intervention_id                uuid REFERENCES interventions(id),
    explication                    varchar(400) NOT NULL,
    motif_suspension               varchar(200),
    materialisee_le                timestamptz NOT NULL DEFAULT now(),

    UNIQUE (regle_id, due_le)
);
```

> **S10** — La table `echeances` ne contient **que les échéances
> matérialisées** : soldées, manquées, suspendues. Les échéances à venir sont
> calculées à la volée (D3) et n'existent nulle part. C'est pourquoi le `CHECK`
> sur `etat` n'accepte pas `ouverte` ni `optimale` : une échéance dans ces
> états en base serait un bug, et la contrainte le dit.

`UNIQUE (regle_id, due_le)` protège d'une double matérialisation de la même
occurrence — le pendant en base de l'idempotence du moteur.

> **S16** — Ce couple `(regle_id, due_le)` est aussi **l'identité d'une échéance
> qui n'est pas matérialisée**. C'est la conséquence directe de S10 : les états
> `ouverte`, `optimale` et `urgente` n'existent dans aucune table, et les lignes
> affichées sur le téléphone n'ont donc pas d'`id` à porter (H11, décision T4 du
> modèle). Le moteur étant pur (A3), le couple est déterministe : il survit à un
> rafraîchissement du paquet terrain, là où un identifiant tiré au hasard à la
> génération changerait à chaque calcul.
>
> `explication` est `NOT NULL` : RG-06 n'admet pas d'échéance sans phrase
> d'explication, et une colonne nullable finit toujours par être nulle.

### 3.5 Journal

```sql
CREATE TABLE interventions (
    id                    uuid PRIMARY KEY,       -- fourni par le client (H5)
    jardin_id             uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    plante_id             uuid NOT NULL REFERENCES plantes(id),
    type_intervention_id  integer NOT NULL REFERENCES types_intervention(id),
    auteur_id             uuid NOT NULL REFERENCES utilisateurs(id),
    faite_le              date NOT NULL,
    duree_min             smallint,
    note                  text,
    lot_saisie_id         uuid,
    position_capturee     jsonb,
    quantite              numeric(8,2),           -- lot 1
    unite                 varchar(12),            -- lot 1
    saisie_le             timestamptz NOT NULL DEFAULT now(),
    synchronisee_le       timestamptz
);

CREATE TABLE photos (
    id               uuid PRIMARY KEY,
    jardin_id        uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    plante_id        uuid REFERENCES plantes(id) ON DELETE CASCADE,
    intervention_id  uuid REFERENCES interventions(id) ON DELETE CASCADE,
    empreinte        varchar(64) NOT NULL,
    chemin_original  varchar(300) NOT NULL,
    derivees_pretes  boolean NOT NULL DEFAULT false,
    taille_ko        integer NOT NULL,
    largeur_px       integer, hauteur_px integer,
    prise_le         date,
    televersee_le    timestamptz NOT NULL DEFAULT now(),

    CHECK (plante_id IS NOT NULL OR intervention_id IS NOT NULL)
);

ALTER TABLE plantes
  ADD CONSTRAINT fk_photo_couverture
  FOREIGN KEY (photo_couverture_id) REFERENCES photos(id) ON DELETE SET NULL;
```

> **S11** — `interventions.id` est **fourni par le client**, pas généré par la
> base. C'est le pivot de l'idempotence (H5) : le rejeu d'une mutation déjà
> enregistrée se traduit par `INSERT ... ON CONFLICT (id) DO NOTHING`, qui
> réussit sans rien créer. Une clé générée côté serveur rendrait ce mécanisme
> impossible.

`derivees_pretes` traduit A19 : tant que le job n'a pas tourné, l'interface sait
qu'elle doit afficher l'original.

### 3 bis. Ajouts de la V1 — plan du terrain

Ajouté le 19/10/2026 (CDC v0.12, §6 lot 4). Une seule table nouvelle ;
`geometries` est déjà prête depuis le MVP, c'était tout l'objet de RG-14.

```sql
CREATE TABLE fonds_de_plan (
    id            uuid PRIMARY KEY,
    jardin_id     uuid NOT NULL REFERENCES jardins(id) ON DELETE CASCADE,
    nature        varchar(12) NOT NULL CHECK (nature IN ('image','orthophoto')),
    fichier       varchar(255),          -- image calée : chemin sur disque, comme une photo
    calage        jsonb,                 -- image calée : 2 ou 3 couples (pixel, lon/lat)
    actif         boolean NOT NULL DEFAULT false,
    cree_le       timestamptz NOT NULL DEFAULT now(),

    CHECK (nature = 'orthophoto' OR (fichier IS NOT NULL AND calage IS NOT NULL))
);

CREATE UNIQUE INDEX fonds_de_plan_un_actif
    ON fonds_de_plan (jardin_id) WHERE actif;
```

> **S17** — `geometries.coordonnees` est du **GeoJSON** (`Point`,
> `LineString`, `Polygon`, WGS 84). Le choix du JSON est maintenu en V1
> (modèle de données, D5) : tout calcul cartographique porte sur le jardin
> courant et se fait en mémoire.

> **S18** — La règle « la position la plus fiable gagne » (RG-48) s'applique
> **à l'écriture**, dans le module `Jardin`. Un relevé `gps` reçu pour une
> plante dont la géométrie est en source `plan` ou `manuel` ne remplace pas
> `geometries`. Il reste dans `interventions.position_capturee`, avec sa
> précision. Le faire à l'affichage obligerait chaque écran à s'en souvenir.

Une image calée compte dans le quota photo du jardin, et part avec lui à la
suppression (D6). Le fond `orthophoto` ne stocke rien : les tuiles sont
demandées au service IGN à l'affichage, sans cache sur le téléphone en V1 (le
hors-ligne est suspendu, chapitre 1).

---

## 4. Les index

Trois seulement comptent vraiment ; les autres viendront de la mesure, pas de
la supposition.

```sql
-- 1. LE plus important : le chargement de l'historique par le moteur (A9)
CREATE INDEX idx_interventions_historique
    ON interventions (jardin_id, plante_id, type_intervention_id, faite_le DESC);

-- 2. Le sélecteur terrain et l'inventaire : lister les plantes actives d'un jardin
CREATE INDEX idx_plantes_actives
    ON plantes (jardin_id, zone_id)
    WHERE etat = 'active';

-- 3. Les échéances matérialisées d'un jardin, pour l'historique
CREATE INDEX idx_echeances_jardin
    ON echeances (jardin_id, plante_id, due_le DESC);
```

> **S12** — **Tout index de lecture commence par `jardin_id`.** Puisque toute
> requête applicative est bornée par le jardin courant (D1, RG-21), un index qui
> ne commence pas par cette colonne ne sera pas utilisé.
>
> **Les index d'unicité font exception, et c'est normal** : `UNIQUE (zone_id,
> nom)`, `UNIQUE (regle_id, due_le)` ou l'index partiel sur les règles actives
> ne servent pas à lire, ils servent à interdire. Leur portée est déjà bornée
> par une clé étrangère qui appartient au jardin. Y ajouter `jardin_id` ne les
> rendrait pas plus sûrs, seulement plus larges.

L'index n°2 est **partiel** (`WHERE etat = 'active'`) : les plantes archivées
s'accumulent avec les années et ne sont jamais dans les listes courantes. Un
index partiel reste petit indéfiniment.

---

## 5. Les migrations

### Ordre de création

Les dépendances imposent un ordre strict :

```
1. utilisateurs, profils_climatiques, familles_botaniques
2. jardins, especes
3. adhesions, types_intervention, zones, regles_reference
4. plantes
5. geometries, regles, interventions
5 bis. index unique partiel sur regles (plante_id, type_intervention_id)
       WHERE est_active                              (S15)
6. fenetres, intervalles_saisonniers, echeances, photos
7. contrainte plantes.photo_couverture_id → photos   (cycle, ajouté en dernier)
```

`echeances.intervention_id` référence `interventions`, créée à l'étape 5 : pas
de cycle ici, seulement un ordre à respecter.

Le cycle `plantes ↔ photos` est réel : une plante référence sa photo de
couverture, une photo référence sa plante. Il se résout en ajoutant la
contrainte après coup, jamais en supprimant l'une des deux relations.

### Règles de travail

> **S13** — **Une migration passée ne se modifie jamais**, même en
> développement, dès qu'elle a tourné ailleurs que sur sa propre machine. On
> ajoute une migration corrective. Le jour où le jardin pilote contient une
> saison de données réelles, une migration réécrite devient une divergence
> silencieuse entre deux bases.

> **S14** — Les migrations sont **centralisées** dans `database/migrations`,
> pas réparties par module (§2 du chapitre 3). L'ordre entre modules compte plus
> que le rangement : `plantes` doit exister avant `regles`, quel que soit le
> module qui les porte.

### Le jeu de données de démarrage

Trois jeux, de natures différentes :

| Jeu | Contenu | Quand |
|---|---|---|
| **Référence** | Les 5 profils climatiques, les ~25 types d'intervention fournis | À chaque installation. Idempotent : il peut être rejoué |
| **Bibliothèque** | Espèces, familles, règles de référence sourcées, **avec leurs butoirs** (RG-44) | Versionné, rejouable, complété au fil des saisons (RG-24). **Ne se publie pas sans passer B-T1 à B-T14**, sur les cinq profils climatiques (B7, B8) |
| **Démonstration** | Un jardin fictif avec 500 plantes (points, lignes, polygones) et deux ans d'historique (décision D-14 du plan) | Développement et tests seulement. **Jamais en production** |

Le troisième mérite l'effort : il permet de voir le tableau de bord peuplé, de
tester le moteur sur des cas réels, et de reproduire un bug signalé sans avoir
à saisir cinquante plantes à la main.

---

## 6. Décisions de ce chapitre

| # | Décision | Enjeu |
|---|---|---|
| S1 | Tables et colonnes en français, sans accents | Le vocabulaire du CDC est normatif |
| S2 | `uuid` natif pour le métier, entiers pour le référentiel | Création hors ligne (D2, H5) |
| S3 | `date` pour les faits métier, `timestamptz` pour les faits techniques | L'heure n'a pas de sens ; les fuseaux décalent d'un jour |
| S4 | Les fenêtres sont des couples jour/mois, jamais des dates | Une fenêtre n'appartient à aucune année (A7) |
| S5 | `varchar` + `CHECK` plutôt que `ENUM` PostgreSQL | Les états évolueront avec R3, R4, R5 |
| S6 | JSON marginal, jamais requêté | Une requête sur du JSON signale qu'il fallait des colonnes |
| S7 | Pas de `deleted_at` : l'archivage est un état métier | RG-02 |
| S8 | `code_court` unique par jardin, jamais réattribué | Des étiquettes physiques en dépendent (RG-13) |
| S9 | `confiance` NOT NULL sans défaut | Force le curateur à trancher (RG-31) |
| S10 | `echeances` ne contient que les échéances matérialisées | D3 — un état `ouverte` en base serait un bug |
| S11 | `interventions.id` est fourni par le client | Pivot de l'idempotence (H5) |
| S12 | Tout index commence par `jardin_id` | Conséquence directe de D1 |
| S13 | Une migration passée ne se modifie jamais | Divergence silencieuse entre bases |
| S14 | Migrations centralisées, pas par module | L'ordre prime sur le rangement |
| S15 | L'unicité d'une règle ne porte que sur les règles **actives** | Sinon le versionnement (RG-08, D10) est impossible |
| S16 | `(regle_id, due_le)` identifie aussi une échéance **non matérialisée** | Les lignes affichées hors ligne n'ont pas d'`id` (S10, H11) |
| S17 | Géométries en GeoJSON, sans PostGIS, y compris en V1 | Les calculs de carte portent sur 500 plantes au plus (D5) |
| S18 | RG-48 appliquée à l'écriture de `geometries` | Un relevé GPS ne défait pas une correction faite sur la carte |

## 7. Points ouverts

| # | Question | Quand |
|---|---|---|
| O9 | Faut-il une table `mutations` côté serveur pour tracer les rejeux, ou le journal suffit-il ? | À l'implémentation du chapitre 1 |
| O10 | `position_capturee` en `jsonb` ou trois colonnes ? | Si l'on veut un jour chercher « les interventions faites près d'ici » |
| O11 | Partitionner `interventions` par année ? | Jamais avant 100 000 lignes — donc probablement jamais |
| O12 | Row Level Security (chapitre 3, §9 et O8) | Avant le lot Ouverture |
