# Recherche — proposer le climat depuis la commune (RG-52, Q24)

Rapports de recherche du 20/10/2026, copiés tels quels. Ce qui y est marqué « supposé » n'est pas vérifié.

## Commune → profil climatique : sources et méthode

Consultations du 20/10/2026. « Vérifié » = page ouverte ou fichier lu ; « supposé » = non vérifié. Fichiers téléchargés dans `scratchpad/dl-climat-jtDq/` (La Poste 4 Mo, table Joly 3,6 Mo) et `scratchpad/dl-re2020-Mcfh/` (annexes RE2020, 22 Mo, 1825 p.).

## 1. Référentiel des communes (code, nom, CP, coordonnées, altitude)

**Constat vérifié : aucun fichier national officiel à jour ne donne l'altitude par commune.**

| Source | Contenu | Licence | Mise à jour | Vérifié |
|---|---|---|---|---|
| COG Insee — data.gouv.fr/datasets/code-officiel-geographique-1 | codes, libellés, mouvements (`v_mvt_commune_2026.csv`) ; ni coordonnées ni altitude | `fr-lo` (Licence Ouverte) | `annual` | métadonnées API |
| Base officielle des codes postaux (La Poste) — CSV `datanova.laposte.fr/data-fair/api/v1/datasets/laposte-hexasmal/metadata-attachments/base-officielle-codes-postaux.csv` | `code_commune_insee, nom_de_la_commune, code_postal, libelle_d_acheminement, ligne_5, _geopoint, latitude, longitude` ; 39 193 lignes, 4,1 Mo | « Licence Ouverte / Open Licence » | `semiannual` ; données du 08/10/2026 | fichier lu |
| ADMIN EXPRESS (IGN) — data.gouv.fr/datasets/admin-express-… | contours et chefs-lieux, shapefile | `lov2` | `monthly` | métadonnées ; présence d'une altitude **non vérifiée** (supposée absente) |
| geo.api.gouv.fr | `centre`, `codesPostaux`, `population` ; le champ `altitude` demandé n'est pas rendu (`/communes?code=05023&fields=…,altitude`) | — | — | appel testé |
| GEOFLA (IGN) | « Sa production est arrêtée. GEOFLA® a été remplacé par le produit AdminExpress. » | `fr-lo` | arrêté | description data.gouv |
| Dérivé GEOFLA (Opendatasoft, data.gouv id 536991c5…) | « Altitude moyenne » ; mise à jour 2016, codes postaux tirés de Wikipédia | `fr-lo` | figé | description lue ; à écarter (périmé) |
| BD ALTI® (IGN) | « Modèle Numérique de Terrain (MNT) aux pas de 25 m et 75m » | `lov2` | `irregular` | description data.gouv |

**Altitude obtenue par calcul unique à l'import** — vérifié : le service altimétrique de la Géoplateforme rend l'altitude d'une liste de points.
`https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json?lon=6.6536|2.3522|2.8954&lat=44.8995|48.8566|42.6986&resource=ign_rge_alti_wld&zonly=true` → `{"elevations": [1335.85, 34.83, 31.94]}` (Briançon, Paris, Perpignan). Appelé une fois, au moment de l'import, sur les ~35 000 points La Poste, puis stocké : compatible avec §8.6 (aucun appel au calcul). Limites de volume et conditions d'usage du service : **non vérifiées**. Autre voie, sans réseau : statistiques zonales sur la BD ALTI 75 m × contours ADMIN EXPRESS (min, max, moyenne) — non testée.

Le point La Poste est un centroïde, pas le jardin : une commune de montagne peut avoir son bourg à 400 m et des hameaux à 1 200 m (supposé, non chiffré).

## 2. Rattacher une commune à un type de climat

### Joly et al., Cybergeo 2010 — la seule table par commune trouvée

Article : https://journals.openedition.org/cybergeo/23155 (doc. 501, mis en ligne le 18/06/2010). Table : `https://journals.openedition.org/cybergeo/26894?file=1`.

- Contenu cité : « Ce tableau, inscrit dans un fichier au format TXT, comporte 36267 lignes (une par commune) et 16 colonnes, soit respectivement, l'identifiant communal INSEE, les 14 variables climatiques traitées et une variable définissant le type climatique où se range la commune. »
- Fichier réel (vérifié) : TSV, CRLF, 36 238 communes, **27 colonnes** : `DC`, 14 variables, `TYPO_CLIM`, `PROBA_TYP1…PROB_TYP_8` (affinité à chaque type), 3 axes AFC. Effectifs de `TYPO_CLIM` : 1 = 4 194 ; 2 = 5 889 ; 3 = 10 918 ; 4 = 7 068 ; 5 = 4 034 ; 6 = 1 127 ; 7 = 1 558 ; 8 = 1 423 ; 0 = 24 ; 9 = 3 (codes 0 et 9 non documentés).
- Types (titres de l'article) : « Type 1 : les climats de montagne », « Type 2 : le climat semi-continental et le climat des marges montagnardes », « Type 3 : Le climat océanique dégradé des plaines du Centre et du Nord », « Type 4 : Le climat océanique altéré », « Type 5 : Le climat océanique franc », « Type 6 : Le climat méditerranéen altéré », « Type 7 : Le climat du Bassin du Sud-Ouest », « Type 8 : Le climat méditerranéen franc ».
- Corse exclue : « La Corse a été exclue du champ spatial de l'étude ». Normales 1971-2000. Codes Insee de 2009 : les communes nouvelles se rattachent via `v_mvt_commune_2026.csv` du COG.
- **Licence — bloquant pour un SaaS commercial** : « Le texte seul est utilisable sous licence CC BY 4.0 . Les autres éléments (illustrations, fichiers annexes importés) sont susceptibles d'être soumis à des autorisations d'usage spécifiques. » et, pour les fichiers : « L'usage de ces fichiers est strictement limité au domaine des services publics. » Attribution exigée : « Source : Base de données climatiques communales 2009. THEMA Université de Franche-Comté, CNRS UMR6049 (F-25000 Besançon) /CESAER INRA UMR1041 (F-21000 Dijon) ; d'après Météo France 1971-2000. » → il faut une autorisation écrite de THEMA / INRAE avant tout usage.

Pièges vus dans la table (vérifié) :

| Commune | Type | Remarque |
|---|---|---|
| Briançon 05023 | 2 | pas « montagne » : le test d'altitude doit primer |
| Besançon 25056 | 1 | « montagne » à ~250 m (supposé) : type 1 ≠ +21 j |
| Bordeaux 33063, Brest 29019 | 5 | même type : pas « océanique frais » |
| Paris, Strasbourg, Grenoble | 4 | — |
| Nice 06088 | 6 | le type 6 compte aussi 47 communes des Landes et 27 de Loire-Atlantique (« en plusieurs endroits du littoral atlantique », dit l'article) |
| Toulouse, Lyon | 7 | — |
| Marseille, Perpignan | 8 | — |

### RE2020 (arrêté du 4 août 2021, NOR LOGL2107359A)

Légifrance bloque curl (« Just a moment… ») ; lu sur AIDA (https://aida.ineris.fr/reglementation/arrete-040821-relatif-exigences-performance-energetique-environnementale) et dans les annexes (`aida.ineris.fr/sites/default/files/2024-09/annexes_a20210804.pdf`).
- « il a été retenu 8 zones climatiques » ; « Les données d'entrée pour le climat sont la zone climatique et l'altitude. » ; variable `Alt` : « 0 m à 400 m | 400 m à 800 m ou 800 m et plus » ; variable `département` : « Numéro du département (Zone climatique) ».
- La carte (Figure 9) est une image : **la table département → zone n'a pas été trouvée en texte**. Granularité : département + 3 classes d'altitude. Faite pour le chauffage et le confort d'été des bâtiments, pas pour la phénologie. Texte réglementaire (réutilisable, supposé).

### Météo-France, zones de rusticité

- Météo-France : aucun jeu de « types de climat par commune » trouvé (recherche data.gouv vide). Les normales par station existent (supposé) : il faudrait construire la typologie soi-même.
- Rusticité : aucune carte officielle française trouvée ; les cartes en circulation sont des adaptations privées de l'USDA (supposé). Elles ne mesurent que le froid minimal hivernal, pas l'avance de saison.

## 3. Recommandation

**Méthode (deux étapes, résultat toujours modifiable) :**
1. L'utilisateur tape une commune ou un code postal → recherche dans la table La Poste importée (un CP peut couvrir plusieurs communes : faire choisir).
2. Altitude du chef-lieu (calculée une fois à l'import, voir §1) > 600 m → **Montagne +21 j**, quel que soit le type.
3. Sinon, type de climat → profil. Correspondance **qualitative, à valider par Léon, rien de chiffré** : 8 → Midi −15 ; 6 et 7 → Sud-Ouest/Rhône −7 (6 avec réserve, cf. Landes) ; 4 et 5 → plaine 0 ; 1, 2, 3 → Nord/Est +7. Variante possible : se servir des colonnes `PROBA_TYP*` pour signaler une commune « limite » et l'annoncer à l'utilisateur.
4. Afficher la raison : « Bordeaux, 30 m, climat océanique franc → plaine tempérée. Modifier ». Une question en plus quand le chef-lieu est entre 400 et 800 m : « Votre jardin est-il à plus de 600 m ? »

**Limites :**
- Licence Joly : usage limité aux services publics → demander l'autorisation (THEMA, Besançon). Sans elle, la méthode n'est pas utilisable.
- Normales 1971-2000 : avant le réchauffement récent ; Corse absente (département par défaut) ; codes communaux de 2009.
- Altitude du centroïde ≠ altitude du jardin ; microclimats hors d'atteinte (déjà assumé au §5.4).
- Les libellés « zones 9-10 » du §5.4 ne sont pas liés aux types Joly : la colonne « Correspond à » mériterait d'être remplacée par des exemples de villes.

**Repli sans référentiel climatique** (seulement la table La Poste ou les 2 premiers chiffres du CP) : une table département → profil par défaut (~96 lignes, écrite et relue à la main), plus la question d'altitude systématique. Pièges : 20xxx = Corse 2A/2B ; un CP n'est pas toujours dans le département de la commune (supposé) ; départements hétérogènes (Alpes-Maritimes, Drôme, Hérault, Pyrénées-Atlantiques : littoral et montagne). Le profil reste une proposition, et l'utilisateur la corrige.

**Sans aucun import** : la liste de 5 actuelle, avec des exemples de villes à la place des zones de rusticité.

## Profil climatique depuis des données ouvertes

Consultations du 20/10/2026. « Vérifié » : page ou fichier ouvert ; « supposé » : non vérifié. Téléchargements : `scratchpad/dl-normales-1792484751/`. `~/jardin` non modifié.

## 1. Normales Météo-France par station : oui, en Licence Ouverte 2.0

**Jeu « Fiches climatologiques »** (Météo-France), https://www.data.gouv.fr/datasets/684c2d56f3861808c0a5d465, licence `lov2` = « Licence Ouverte / Open Licence version 2.0 », mis à jour le 16/10/2026 (vérifié, API data.gouv). Description citée : « Fiche synthétique contenant les moyennes mensuelles, les normales et les records d'une station. » ; « Les fiches climatologiques sont téléchargeables au format PDF et data. Elles sont mises à jour mensuellement. »

- Liste des stations (GeoJSON, `num`, `nom`, coordonnées) : `https://meteofrance.s3.sbg.io.cloud.ovh.net/data/synchro_ftp/REF_STATION/liste_fiches_clim.geojson` (vérifié). **1 574 stations, dont 1 455 en métropole** (codes 01 à 95, Corse comprise), 67 en 97x, 52 en 98x (compté).
- Fiche d'une station : `https://meteofrance.s3.sbg.io.cloud.ovh.net/data/synchro_ftp/REF_STATION/FICHECLIM_<num>.data` (et `.pdf`). Vérifié sur 27 stations : HTTP 200.
- Format (vérifié, `FICHECLIM_75114001.data`) : texte UTF-8, CRLF, séparateur `;`, un bloc par paramètre, 12 mois + année. En-tête : « Statistiques  1991-2020  et  records » ; « PARIS-MONTSOURIS (75)      Indicatif : 75114001, alt : 75m, lat : 48°49'18"N, lon : 2°20'16"E ». L'altitude de la station est dans la fiche.
- Paramètres (Paris) : Tmax, **« Température moyenne (Moyenne en °C) »**, Tmin, records, jours Tx ≥ 25/30 °C et Tn ≤ 0/−5/−10 °C, pluie, « Degrés Jours Unifiés », rayonnement, insolation, ETP, rafales.
- **Piège vérifié : beaucoup de fiches ne couvrent pas 1991-2020.** Sur un tirage de 20 stations métropolitaines, toutes ont une température moyenne, mais 11 portent une ligne du type « Statistiques établies sur la période 2003-2020 » (aussi 2007-2020, 1995-2014…). Il faut lire cette ligne et écarter (ou pondérer) les séries courtes. Stations à période complète : non comptées.
- Licence (citée, https://www.etalab.gouv.fr/licence-ouverte-open-licence) : « droit non exclusif et gratuit de libre « Réutilisation » de l'« Information » objet de la présente licence, à des fins commerciales ou non » ; « de l'exploiter à titre commercial, par exemple en la combinant avec d'autres informations, ou en l'incluant dans votre propre produit ou application. Sous réserve de : mentionner la paternité de l'«Information» : sa source (a minima le nom du « Concédant ») et la date de la dernière mise à jour ».

Aucun jeu « normales » distinct chez Météo-France sur data.gouv (vérifié, API) : elles sont dans ces fiches.

## 2. Grilles nationales

| Grille | Contenu | Licence | Commercial |
|---|---|---|---|
| **SIM2 mensuel (Safran-Isba)**, data.gouv `65e040c50a5c6872ebebc711` | « La résolution est de 0.072° (environ 8km) » ; un CSV gzip par an, 1958 → 2026 : `https://meteofrance.s3.sbg.io.cloud.ovh.net/data/REF_CC/SIM_MENS/MENS_SIM2_<année>.csv.gz` (2020 : 3,8 Mo) ; colonnes `LAMBX;LAMBY;DATE;…;T;…`, où `T` = « Température (moyenne mensuelle) » (PDF des paramètres) ; grille de **8 981 mailles** avec `lat_dg`, `lon_dg`, `num_dep` (`.dbf` lu) | `lov2` | oui (vérifié, LO 2.0) |
| DRIAS, data.gouv `5dfb22926f44413ada2c54b4` | projections climatiques régionalisées | `fr-lo` sur data.gouv | oui pour ce qui est sur data.gouv ; conditions propres du portail drias-climat.fr non lues |
| E-OBS (ECA&D) | grille européenne | citée (ecad.eu/download/ensembles/download.php) : « These observational data are strictly for use in non-commercial research and non-commercial education projects only. » | **non** |
| WorldClim 2.1 | grille mondiale | citée (worldclim.org/about.html) : « The data are freely available for academic use and other non-commercial use. Redistribution or commercial use is not allowed without prior permission. » | **non** sans autorisation |

SIM2 n'a pas de normale toute faite : il faut moyenner soi-même les 30 fichiers 1991-2020 (~30 × 4 Mo, calcul unique). La grille `.dbf` ne donne pas l'altitude des mailles (vérifié : aucun champ altitude) ; une maille de 8 km lisse le relief (supposé : en montagne, la vallée et le versant ont la même valeur).

## 3. Écart de température → décalage en jours : sources

Citations tirées des résumés (API HAL `api.archives-ouvertes.fr/search/?q=halId_s:…&fl=abstract_s`, page NORA). Aucune source agronomique française de vulgarisation (INRAE grand public, chambres, Météo-France) n'a donné de coefficient en jours par °C : seulement des avances par décennie.

1. **Vitasse et al. 2010**, *Functional Ecology* (INRAE, gradient 100–1 600 m, hêtre et chêne), https://hal.inrae.fr/hal-02664554v1 : « The reaction norms of leaf unfolding date to temperature linearly accelerated for both species with an average shift of −5·7 days per degree increase. »
2. **Asse et al. 2018**, *Agricultural and Forest Meteorology* (Chuine, Vitasse, Badeau ; réseau Phenoclim, Alpes françaises), https://hal.science/hal-01809598v1 : « For similar cold winter temperatures, warmer preseasons significantly advanced budburst and flowering along the elevation gradient (−5.3 to −8.4 days °C−1). On average, the effect of winter warming was 2.3 times lower than the effect of spring warming. » et : « warmer winters significantly delayed budburst and flowering along the elevation gradient (+0.9 to +5.6 days °C−1) ».
3. **Menzel et al. 2006**, *Global Change Biology* (125 000 séries, 21 pays), https://nora.nerc.ac.uk/id/eprint/407/ : « species' phenology is responsive to temperature of the preceding months (mean advance of spring/summer by 2.5 days°C1, delay of leaf colouring and fall by 1.0 day°C1) ». C'est une sensibilité **dans le temps** (années chaudes), pas d'un lieu à l'autre.
4. **Lebourgeois et al. 2008**, *Revue forestière française* (réseau Renecofor), https://hal.science/hal-03449805v1 : « Le débourrement est plus tardif pour les résineux et dépend fortement de l'altitude (retard d'environ 1,5 jour par 100 m) » ; et pour le gradient ouest-est : « retard de 2 jours par degré de longitude ».

Ce sont des arbres forestiers, pas des plantes de jardin : l'application au jardin est **supposée**. La fourchette « printemps » utilisable est donc ~5 à 8 j/°C entre lieux (1, 2), ~2,5 j/°C d'une année à l'autre (3).

Correction d'altitude : seule source trouvée, Infoclimat (lexique, pas Météo-France) : « Ce gradient dans l'atmosphère est généralement de l'ordre de 6,5 °K/km (diminution de 0,65 °C par 100m de dénivelé), mais il peut varier en fonction des régions et de la situation météorologique. » Une page Météo-France équivalente n'a pas été trouvée.

## 4. Essai sur les fiches lues (calcul, pas une source)

Moyenne février-avril (FMA) des fiches, écart à Paris-Montsouris (9,13 °C), × 5,7 j/°C (Vitasse) :

| Station (alt.) | FMA °C | Écart | Décalage calculé | Profil attendu |
|---|---|---|---|---|
| Perpignan (42 m) | 11,80 | +2,67 | −15 j | −15 |
| Nice (2 m) | 11,90 | +2,77 | −16 j | −15 |
| Bordeaux-Mérignac (47 m) | 10,50 | +1,37 | −8 j | −7 |
| Lyon-Bron (202 m) | 8,83 | −0,30 | +2 j | −7 (vallée du Rhône) |
| Strasbourg-Entzheim (150 m) | 7,43 | −1,70 | +10 j | +7 |
| Radinghem, 62 (115 m) | 6,87 | −2,26 | +13 j | +7 |
| Embrun (873 m) | 6,93 | −2,20 | +13 j | +21 (montagne) |
| Labergement, 25 (863 m) | 3,60 | −5,53 | +32 j | +21 |

Le sud et le Sud-Ouest tombent juste ; **Lyon ne sort pas en « vallée du Rhône »** (Montsouris est une station urbaine chaude, et Lyon a un printemps plus frais) ; Embrun montre qu'au-dessus de 600 m l'écart réel peut être bien moindre que l'altitude ne le laisse croire (vallée ensoleillée).

## 5. Recommandation

**Méthode la plus simple et sourçable :**
1. À l'import (une fois) : télécharger la liste GeoJSON et les ~1 455 fiches `.data`, garder les stations dont la température moyenne couvre 1991-2020 (ligne « Statistiques établies sur la période » absente), stocker `num, lat, lon, alt, FMA`.
2. Commune (La Poste, altitude IGN) → station la plus proche **avec un écart d'altitude limité**, ou moyenne des 3 plus proches ; corriger la FMA de (alt. station − alt. commune) × 0,65 °C/100 m, ou mieux, d'un gradient ajusté sur les stations elles-mêmes (régression FMA ~ altitude + latitude, calculable sur les 1 455 fiches).
3. Écart à une FMA de référence « plaine tempérée » (à choisir : médiane des stations du Bassin parisien plutôt que Montsouris), × 5,7 j/°C → décalage → profil le plus proche parmi −15/−7/0/+7/+21. Seuil d'altitude 600 m conservé comme règle à part.
4. Afficher la raison et laisser l'utilisateur corriger.
5. Mention obligatoire : « Source : Météo-France, fiches climatologiques, mise à jour du JJ/MM/AAAA ».

Variante sans stations : normale 1991-2020 de `T` sur SIM2 (8 km, LO 2.0), maille la plus proche + correction d'altitude. Plus homogène, mais il faut l'altitude des mailles (non trouvée).

**Limites :** coefficients tirés d'arbres forestiers (5,3 à 8,4 j/°C selon espèce) ; FMA arbitraire comme fenêtre ; la chaleur hivernale joue en sens inverse (Asse 2018) ; stations urbaines/littorales ; profils discrets (Lyon tombe à 0).

**Reste à vérifier :** nombre de stations à période complète ; altitude des mailles SIM ; un coefficient sur plantes fruitières ou potagères (Legave, INRAE, non lu : HAL bloque le téléchargement des PDF par curl) ; une source Météo-France pour le gradient vertical ; les conditions propres du portail DRIAS ; le choix de la référence et des seuils entre profils, à faire valider par Léon.

## Coefficient jours/°C, gradient d'altitude, station de référence

Consultations du 20/10/2026. Fait suite à `recherche-climat-normales.md`. « Vérifié » : page ou fichier ouvert et lu ; « supposé » : déduit, non lu. Téléchargements : `scratchpad/dl-coef-ScD0/` (fiches dans `fiches/`). `~/jardin` non modifié.

## 1. Coefficient sur fruitiers ou plantes de jardin

**Trouvé et lu :**

1. **Pommier, Allemagne : −6 j/°C, mais d'une année à l'autre** (vérifié, texte intégral ouvert dans Chrome). Weil, Ullmann, Camberlin, *Climatologie* 15 (2018), DOI 10.4267/climatologie.1314, https://climatology.edpsciences.org/articles/climat/full_html/2018/01/climat201815p1/climat201815p1.html. Résumé français : « la floraison des pommiers est très étroitement liée à la variabilité des températures moyennes de mars à mai (R2 = 0,82; -6 jours/°C) ». Section 2.2 : « A change of 1°C in the mean temperature from mid-March to mid-May equals an advancement of bloom onset of approximately 6 days. » La pente est calculée sur les **moyennes nationales annuelles 1951-2014** (fig. 7 : « The points represent the years 1951 to 2014 »), donc un gradient **temporel**. L'analyse spatiale du même article régresse la date sur latitude, longitude et altitude (R² ajusté 83 %), pas sur la température. Fenêtre 10 mars-10 mai, pas février-avril.
   - Le même article cite (non lu à la source) Chmielewski et Rötzer 2001 : « 7 days advance in leaf unfolding for a change of 1°C (February – April) » (arbres, temporel). C'est la seule mention trouvée d'une fenêtre **février-avril** identique à celle du SaaS.
2. **Plantes herbacées de jardins botaniques : −7 j/°C, dans l'espace comme dans le temps** (vérifié, résumé de communication). iDiv Annual Conference 2025, « Space-for-Time substitution to study phenological shifts in herbaceous species is most reliable for early-flowering species », https://conference.idiv.de/event/3/contributions/70/ : 17 espèces herbacées, jardins botaniques de Vienne sur 150 ans et 11 jardins botaniques le long d'un gradient spatial ; « We showed that the space-time equivalence is fulfilled for the present species and spatial gradient and found that for both approaches, flowering was advanced by 7 days when the temperature increased by 1°C. » Résumé de congrès, pas un article relu par des pairs ; fenêtre de température non précisée dans le résumé.
3. **Pommier, Europe de l'Ouest (Legave et al. 2013, *Int. J. Biometeorol.* 57:317-331)** : le résumé donne des avances en jours (10 j continental, 6-7 j océanique) mais **aucune pente par °C** (lu via recherche, page non ouverte). Non retenu.

**Non trouvé :** aucun coefficient spatial publié en j/°C pour pommier, poirier, cerisier, abricotier ou vigne. Vigne (IFV, BSV) : seulement des sommes de degrés-jours et des avances de campagne. Les PDF HAL INRAE (Legave 2008, `hal-02757466/document`, `hal-02737778/file/...`) et la HU Berlin (Chmielewski) renvoient une page anti-robot Anubis par curl ; non lus.

**Bilan des pentes disponibles :** 5,7 j/°C (arbres forestiers, spatial, Vitasse 2010) ; 6 j/°C (pommier, temporel) ; 7 j/°C (herbacées, spatial = temporel). Fourchette 5,7-7 ; **6 j/°C est une valeur centrale raisonnable (choix, supposé)**.

## 2. Gradient de température avec l'altitude (Météo-France)

Vérifié, page ouverte : Météo-France, « Pourquoi les températures peuvent être très différentes d'une région à l'autre ? », https://meteofrance.com/actualites-et-dossiers/comprendre-la-meteo/pourquoi-les-temperatures-peuvent-etre-tres-differentes : « Dans l'atmosphère standard, la température décroît avec l'altitude. On perd environ 0,65 °C en s'élevant de 100 mètres, ce qui explique qu'il fait généralement plus froid en montagne que dans la vallée. » La page poursuit sur les exceptions anticycloniques hivernales.

Réserve, même source institutionnelle (https://meteofrance.com/actualites-et-dossiers/actualites/climat/relief-des-temperatures-minimales-plus-froides-dans-les-vallees) : « En montagne, la température baisse généralement avec l'altitude, mais ce n'est pas toujours le cas. » (inversion de température en fond de vallée).

Pour mémoire (vérifié, vignette du paquet R `pep725`, https://cran.r-project.org/web/packages/pep725/vignettes/spatial-patterns.html) : « phenological phases are delayed by 2-4 days per 100 meters of elevation gain ». Cohérent avec 0,65 °C/100 m × 6 j/°C ≈ 3,9 j/100 m (calcul).

## 3. Station de référence « plaine tempérée, Bassin parisien »

Fiches lues : `https://meteofrance.s3.sbg.io.cloud.ovh.net/data/synchro_ftp/REF_STATION/FICHECLIM_<num>.data`, toutes en en-tête « Statistiques  1991-2020  et  records ». Sous « Température moyenne », aucune ligne « Statistiques établies sur la période » pour les 22 stations téléchargées (vérifié). Mais des séries courtes existent sur d'**autres** paramètres (rayonnement, insolation) : Orléans (rayonnement 2010-2020), Melun (2009-2020), Chartres, Châteaudun, Beauvais, Troyes, Orly, Roissy, Le Bourget, Évreux.

**Recommandation : Trappes (78621001, alt. 167 m, 48°46'27"N 2°00'35"E)**, seule station du Bassin parisien testée dont la fiche n'a **aucune** série courte (vérifié : 0 ligne « Statistiques établies »). Station de plateau, hors agglomération dense (supposé, d'après sa position). Températures moyennes lues dans la fiche :

| Mois | Trappes | Orléans (45055001, 123 m) | Paris-Montsouris |
|---|---|---|---|
| Février | 4,9 °C | 4,9 | 6,0 |
| Mars | 7,9 °C | 7,9 | 9,2 |
| Avril | 10,7 °C | 10,6 | 12,2 |
| **FMA** | **7,83** | 7,80 | 9,13 |

Contrôle (calcul) : FMA de 10 stations hors agglomération parisienne (Trappes 7,83, Orléans 7,80, Melun 7,87, Chartres 7,63, Châteaudun 7,67, Évreux 7,43, Beauvais 7,20, Troyes 7,30, Bourges 8,20, Tours 8,40) : médiane **7,74**. Trappes est à +0,1 °C de cette médiane ; Montsouris (+1,3 °C) est un îlot de chaleur urbain, Orly/Roissy/Le Bourget à 8,27-8,33. Orléans est une alternative équivalente pour la température.

## 4. Recalcul des huit villes

Formule : décalage = (FMA_référence − FMA_station) × k, avec FMA_réf = (4,9 + 7,9 + 10,7) / 3 = 7,83 °C (Trappes) et k = 6 j/°C (valeur centrale ; entre parenthèses k = 5,7 et 7). Négatif = en avance. Profil = le plus proche parmi −15/−7/0/+7/+21 (bornes implicites −11, −3,5, +3,5, +14 ; supposé). FMA lues dans les fiches 1991-2020 (février ; mars ; avril).

| Station (alt.) | Fév ; mars ; avr | FMA | Écart | Calcul (k = 6) | Fourchette 5,7-7 | Profil |
|---|---|---|---|---|---|---|
| Perpignan (42 m) | 9,2 ; 12,0 ; 14,2 | 11,80 | +3,97 | −3,97 × 6 = **−23,8 j** | −22,6 à −27,8 | −15 |
| Nice (2 m) | 9,8 ; 11,8 ; 14,1 | 11,90 | +4,07 | **−24,4 j** | −23,2 à −28,5 | −15 |
| Bordeaux-Mérignac (47 m) | 7,8 ; 10,7 ; 13,0 | 10,50 | +2,67 | **−16,0 j** | −15,2 à −18,7 | −15 |
| Lyon-Bron (202 m) | 5,2 ; 9,0 ; 12,3 | 8,83 | +1,00 | **−6,0 j** | −5,7 à −7,0 | −7 |
| Rennes-St Jacques (36 m) | 6,6 ; 8,8 ; 11,0 | 8,80 | +0,97 | **−5,8 j** | −5,5 à −6,8 | −7 |
| Brest-Guipavas (92 m) | 7,1 ; 8,6 ; 10,1 | 8,60 | +0,77 | **−4,6 j** | −4,4 à −5,4 | −7 |
| Clermont-Fd (331 m) | 5,1 ; 8,3 ; 10,9 | 8,10 | +0,27 | **−1,6 j** | −1,5 à −1,9 | 0 |
| Strasbourg-Entzheim (150 m) | 3,6 ; 7,4 ; 11,3 | 7,43 | −0,40 | **+2,4 j** | +2,3 à +2,8 | 0 |

Aucun profil ne change dans la fourchette 5,7-7 j/°C.

Changements par rapport à la recherche précédente (référence Montsouris) : Lyon passe de 0 à **−7** ; Bordeaux de −7 à **−15** ; Strasbourg de +7 à **0**. Perpignan et Nice dépassent nettement −15 (≈ −24 j) : le profil −15 est un plafond.

Points à faire trancher (supposé, non vérifié sur des observations phénologiques) :
- **Brest et Rennes en −7** : la FMA y est élevée par l'hiver doux (février 7,1 et 6,6 °C) alors qu'avril est frais (10,1 à Brest, < Trappes 10,7). Une fenêtre mars-avril, ou avril seul, rapprocherait Brest de 0. Asse et al. 2018 (recherche précédente) montrent qu'un hiver doux peut retarder le débourrement.
- **Strasbourg à 0** : son avril (11,3) est plus chaud que Trappes ; seul février (3,6) le refroidit. Cohérent avec l'idée que la FMA mélange froid hivernal et chaleur printanière.
- Clermont-Ferrand (331 m) reste en plaine (0) : pas de correction d'altitude à faire puisque la station est la mesure elle-même ; la correction 0,65 °C/100 m ne s'applique qu'entre station et commune.

## Synthèse

- Coefficient fruitier spatial : **pas trouvé**. Meilleures sources : pommier −6 j/°C (temporel, Allemagne, vérifié) ; herbacées −7 j/°C (spatial = temporel, résumé de congrès, vérifié) ; arbres −5,7 j/°C (spatial). Retenir 6 j/°C, fourchette affichable 5,7-7.
- Gradient vertical : Météo-France, « environ 0,65 °C en s'élevant de 100 mètres » (vérifié).
- Référence : **Trappes, FMA 7,83 °C** (fiche sans aucune série courte ; médiane Bassin parisien 7,74).
- Profils obtenus : Perpignan, Nice, Bordeaux −15 ; Lyon, Rennes, Brest −7 ; Clermont-Ferrand, Strasbourg 0.
