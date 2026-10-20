# Conception des écrans — maquette dans FigCraft

Version 0.1 — 19/10/2026
Applique le CDC v0.13 (§2.2, §7, §8.5, §1.5). Décisions **M1 à M12**. Points
ouverts **O22 à O25**, à la suite du chapitre technique 6.

**Ce que ce document traite.** Comment les écrans sont conçus avant d'être
développés : les étapes, l'organisation du fichier FigCraft, la façon dont le
fichier est construit pour qu'une marque future s'applique partout, la liste
des écrans et des composants dans l'ordre où les concevoir, la charte à
respecter, et la façon dont les agents de développement reçoivent la maquette.

**Ce qu'il ne traite pas.** La marque elle-même (nom, logo, couleurs) : elle
n'existe pas encore, et le fichier est conçu pour l'attendre (§2).

> *Rien n'a encore été construit.* FigCraft 0.7.0 est installé
> (`figcraft-cli`), avec composants à variantes, variables à modes, mise en
> page automatique, sections, prototype et versions nommées. **Construire un
> écran par la ligne de commande n'a pas encore été fait** : ce sera vérifié sur
> le premier wireframe.

---

## 1. Deux étapes, et un arrêt entre les deux

> **Révision du 19/10/2026 (décision de Léon).** L'arrêt entre les deux
> étapes n'est plus une porte : les vagues s'enchaînent sans attendre la
> validation, et Léon revoit les wireframes et l'interface après coup. Ce qui
> est écrit ci-dessous sur la « validation » se lit désormais comme une
> **revue** : elle produit des demandes de correction, elle ne bloque rien.
> Un écran se développe sur sa maquette telle qu'elle est (M12), et une
> remarque ultérieure de Léon devient un sprint de correction. Les choix d'UX
> faits par défaut sont inscrits au registre `docs/plan/questions-ux.md`.

| Étape | Ce qu'on décide | Ce qu'on s'interdit |
|---|---|---|
| **1. Wireframes** | Les parcours : quels écrans, dans quel ordre, combien de gestes, quelle information à quel endroit, quels états | Couleur, police définitive, illustration, finition. Tout est en gris |
| **Validation par Léon** | Le prototype cliquable est essayé **sur le téléphone, dehors**, pour les écrans terrain | — |
| **2. Interface** | Les variables, puis les composants, puis les écrans construits uniquement avec ces composants | Changer un parcours validé sans revenir à l'étape 1 |

> **M1** — Aucune couleur, aucune police et aucun composant définitif avant la
> validation des wireframes. Discuter d'un vert pendant qu'un parcours compte
> cinq gestes au lieu de trois, c'est corriger le mauvais problème.

> **M2** — Un wireframe terrain se valide **en le manipulant sur le
> téléphone**, pas en le regardant sur un écran d'ordinateur. Le compte des
> gestes de P1 (3 au plus) et le temps (moins de 15 s) se mesurent sur le
> prototype.

**Le prototype des wireframes.** Les wireframes de chaque vague sont reliés en
un prototype cliquable, avant toute ligne de code : un **parcours de
prototype** par parcours du CDC (P1, P2…), qui démarre sur son premier écran.
Les liens portent les vrais gestes : appui sur « fait », retour, fenêtre de
confirmation en superposition. Les états qui dépendent d'une condition (saisie
enregistrée ou **non envoyée**, RG-50) se jouent avec une variable du
prototype, pour qu'on puisse essayer les deux branches. Le but est de sentir le
parcours, pas de simuler l'application : pas de fausses données qui changent,
pas de logique au-delà de ce qui sert à essayer un geste.

Les wireframes sont des **cadres simples**, sans composants : ils servent à
décider puis sont archivés. Les reconstruire en interface coûte moins que de
porter dès le départ des composants qui changeront à chaque retour.

Chaque validation crée une **version nommée** du fichier
(« Wireframes terrain validés »). C'est le point de retour si l'interface
s'égare.

---

## 2. Construit pour recevoir une marque

Le produit n'a pas encore de marque. Le jour où elle existera, **la changer
doit se faire à un seul endroit**, et tous les écrans doivent suivre sans
retouche. Ce n'est vrai que si aucun écran ni aucun composant ne porte de
valeur en dur.

### Trois couches, et une seule porte des valeurs

| Couche | Collection FigCraft | Contenu | Qui la change |
|---|---|---|---|
| **Primitives** | `Primitives` | Les valeurs brutes : palette (`vert/500`, `neutre/100`…), familles de police, échelle des tailles de texte, échelle des espacements, rayons | **La marque**, et rien d'autre |
| **Sémantique** | `Sémantique` | Les rôles : `fond/page`, `fond/surface`, `texte/principal`, `texte/secondaire`, `action/principale`, `action/principale-texte`, `bordure/defaut`, `focus`, `etat/en-retard`, `etat/a-faire`, `etat/fait`, `etat/hors-saison`, `etat/non-envoye`. Chacun est un **alias** d'une primitive | Le design, quand un rôle change de couleur |
| **Densité** | `Densité`, deux modes : `Bureau` et `Terrain` | `cible/min` (24 / 48 px), `texte/corps`, `espace/ligne`, `espace/bloc` | Le design, pour les contraintes de contexte |

> **M3** — Les valeurs n'existent que dans la collection `Primitives`. Changer
> de marque, c'est modifier `Primitives`, puis le logo et le nom (ci-dessous).

> **M4** — Les composants et les écrans ne se lient qu'aux variables
> **sémantiques** et de **densité**, jamais à une primitive, jamais à une valeur
> brute. Un bouton qui prend `vert/500` directement ne suivra pas la marque ; un
> bouton qui prend `action/principale` la suivra.

La densité en modes porte A23 (chapitre 3) dans la maquette : un même
composant passe en contexte terrain en changeant de mode, sans variante
dessinée à la main.

### Ce qui complète les variables

- **Styles de texte** (`Titre 1` à `Titre 3`, `Corps`, `Corps fort`,
  `Légende`, `Chiffre`) : leur police et leur taille sont **liées aux
  variables**. Aucun texte d'écran n'a de police choisie à la main.
- **Logo** : un seul composant `Marque/Logo`, provisoire. Toutes ses
  apparitions en sont des instances.
- **Nom du produit** : une variable texte `marque/nom`, liée à chaque texte qui
  le porte.
- **Icônes** : un seul jeu, chaque icône étant un composant `Icône/<nom>`.
  Lucide, qui existe aussi en composants React (`lucide-react`). Licence ISC
  (et MIT pour les icônes reprises de Feather) : usage commercial permis, à
  condition de conserver l'avis de copyright dans le code livré (O25, levé le
  20/10/2026). Les tracés sont générés depuis `lucide-static` 0.460.0 par
  `scripts/design/importer-lucide.py` dans `design/interface/icones.py`, et
  l'avis est dans `design/interface/LICENSE-lucide`. Une icône a un trait de
  2 px lié à `texte/principal`, que l'instance relie à un autre rôle
  (`texte/inverse`, `texte/desactive`). Les icônes suivent la taille de leur
  instance (contraintes « scale ») : 24 px par défaut, 16 ou 20 px à côté
  d'un texte de 15 ou de 20 px au plus.
  - **Permuter une icône rend le trait de l'icône d'origine.** Dans un
    composant qui porte une icône permutable (`icone`, `iconeApres` du
    Bouton), changer d'icône par la propriété remet sur le trait le rôle du
    composant `Icône/<nom>`, pas celui que lui donnait le composant hôte. On
    relie donc le trait à nouveau sur l'instance, après la permutation
    (`Doc.bouton` le fait selon `fondations.BOUTON_TEXTE`).
  - **Icône seule** pour les gestes universels : retour, retirer, monter,
    descendre, ouvrir une liste. Chacune porte un nom accessible (« Retour »,
    « Retirer Haies »), qui nomme le calque et devient l'`aria-label` du
    bouton.
  - **Le texte reste** sur ce qui engage : « Fait », « Continuer », « Sauter
    cette fois », « Ajouter une photo ». Une icône peut l'accompagner, jamais
    le remplacer.
  - **Les glyphes des wireframes deviennent des icônes à l'interface.** ⚠ et
    ✓ en tête de texte, ↓ en fin de valeur, ✓ en fin d'un texte aligné à
    droite (`Doc.texte`) ; « + » et « ‹ » en tête de bouton, « › » et « → »
    en fin (`Doc.bouton`). Les wireframes gardent leurs glyphes.
  - Glyphes qui restent du texte : « › » des fils d'Ariane, les puces, les
    points du mot de passe, « × » des dimensions, la légende de la frise, et
    le ✓ de l'appareil d'essai (« Essai : réseau ✓ »), qui n'est pas de
    l'interface.

> **M5** — Les variables portent **les mêmes noms dans le code** (variables
> CSS, configuration Tailwind), avec les trois mêmes couches. La marque change
> alors en deux endroits, l'un dans la maquette et l'autre dans le code, et
> nulle part ailleurs.

> **M6** — Avant chaque validation, un **contrôle automatique** liste les
> remplissages, contours et textes qui ne sont liés ni à une variable ni à un
> style. Il doit rendre zéro. Une valeur en dur passée inaperçue est
> précisément ce qui casse le jour de la marque (O22).
>
> **Levé le 20/10/2026.** `design/interface/controle.py` relit par
> `node.get` chaque calque posé par la passe interface (`boundVariables`,
> `styles.text`) et rend `CONFIRME M6` ou la liste des fautes. Il est lancé à
> chaque construction ; il échoue aussi si un calque n'a pas pu être lu.

### Ce qui n'est pas fait maintenant, et pourquoi

Appliqué selon la mesure du CDC §1.5 :

- **Pas de mode sombre ni de second thème de marque.** Aucun besoin en V1
  (thème clair, §8.5), et la couche sémantique permet de les ajouter plus tard
  par un mode, sans toucher aux écrans. L'ajout tardif est bon marché, donc on
  attend.
- **La couche sémantique, elle, est posée dès le départ.** L'ajouter après
  coup, c'est relier à nouveau chaque calque de chaque écran.

---

## 3. Organisation du fichier

Un seul fichier, `design/jardin.figcraft`, dans le dépôt (chapitre 6, §2),
versionné avec le code. Le dépôt est public pendant le développement : le
fichier ne contient **aucune donnée réelle** du jardin pilote, comme le reste
du dépôt (C4, RG-28).

| Page | Rôle |
|---|---|
| `0 · Mode d'emploi` | Comment lire le fichier, statuts, conventions de noms |
| `1 · Charte` | Les règles du §5, résumées en une planche |
| `2 · Parcours` | P0 à P7 en schémas d'enchaînement, écran par écran |
| `3 · Wireframes terrain` | Une section par parcours |
| `4 · Wireframes bureau` | Une section par parcours |
| `5 · Fondations` | Les trois collections de variables, les styles de texte, les icônes, le logo provisoire |
| `6 · Composants` | Les composants et leurs variantes |
| `7 · Écrans terrain` | Les écrans finis, construits avec les instances de `6` |
| `8 · Écrans bureau` | Idem |
| `Archive` | Ce qui a été remplacé, jamais supprimé |

**Conventions :**

- Cadres d'écran : `<parcours> · <rang> · <nom>`, par exemple
  `P1 · 02 · Choisir la plante`. État dans le nom quand il y en a plusieurs :
  `P1 · 02 · Choisir la plante — vide`.
- Formats : terrain 390 × 844, bureau 1440 × 900.
- Statut porté par le nom de la section : `[à valider]` ou `[validé JJ/MM]`.
- Composants : `Catégorie/Nom` (`Échéance/Ligne`). Les propriétés de variante
  portent **les noms des props React** (`variante = principale | secondaire |
  discrete`, `etat = a-faire | en-retard | fait`). Un agent lit ainsi le
  composant à écrire sans interpréter.

**Comment le fichier se construit.** Il n'est jamais retouché à la main :
`python3 -I design/wireframes/construire.py` le régénère en entier, en deux
passes sur le même code de mise en page (`wireframes/vague1.py` à
`vague6.py`).

1. **Wireframes** (pages 0 à 4) : tout en gris.
2. **Interface** (pages 5 à 8) : `interface/fondations.py` pose les variables,
   les styles et les composants, puis les mêmes vagues sont rejouées. Chaque
   gris y est traduit en rôle sémantique (table `ROLES` de `vague1.py`), chaque
   texte reçoit un style, chaque bouton, case ou logo devient une instance. Un
   gris sans rôle **arrête la construction** : M4 tient par construction.
3. **Contrôles** : M6 (`interface/controle.py`), puis l'export des variables
   pour le code (`interface/variables.css`, M5).

Chaque étape écrit une ligne `CONFIRME` ou `ECHOUE`. Un parcours se corrige
donc à un seul endroit, et l'interface suit.

> **M7** — Le fichier vit dans le dépôt et se versionne avec le code. Une
> maquette séparée du code finit toujours par décrire un autre produit que
> celui qui est livré.

---

## 4. Ce qu'il faut concevoir, et dans quel ordre

### Les écrans de la V1

| # | Écran | Contexte | Parcours, fonction | Lot |
|---|---|---|---|---|
| T1 | Aujourd'hui : liste du jour, « fait » | Terrain | P2 | 0 |
| T2 | Je viens de faire : choix du type | Terrain | P1 | 0 |
| T3 | Choix des plantes, triées par proximité | Terrain | P1 | 0 |
| T4 | Saisie enregistrée, ou **non envoyée** (RG-50) | Terrain | P1, P2 | 0 |
| T5 | Fiche plante, avec photo | Terrain | P6 | 0 |
| T6 | Lecture d'un QR | Terrain | Étiquettes scannables | 4 |
| T7 | Carte terrain et ordre de tournée. On y entre par un **bouton « Carte »** dans l'en-tête de T1, pas par un glissé (19/10/2026) ; la tournée est un panneau fixe sous la carte, sans glissé (vague 6, Q-UX-10) | Terrain | Carte | 4 |
| B1 | Connexion | Bureau | Socle | 0 |
| B2 | Mise en route : jardin, commune, profil climatique proposé | Bureau | P0 | 0 |
| B3 | Inventaire rapide : zones, puis plantes (saisie en ligne, duplication, collage) | Bureau | P0 | 0 |
| B4 | Tableau de bord : aujourd'hui, semaine, mois, groupé par zone, avec explication | Bureau | P3 | 0 |
| B5 | Liste des plantes : tableau, filtres, édition en masse | Bureau | Inventaire | 0 |
| B6 | Fiche plante : frise, échéances, photos, règles | Bureau | P6 | 0 |
| B7 | Créer une plante | Bureau | P4 | 0 |
| B8 | Intervention passée, sur plusieurs plantes | Bureau | P5 | 0 |
| B9 | Corriger une règle | Bureau | P7 | 0 |
| B10 | Étiquettes : aperçu avant impression | Bureau | Étiquettes | 0 |
| B11 | Catalogue des types d'intervention | Bureau | Catalogue | 0 |
| B12 | Export des données | Bureau | Socle | 0 |
| B13 | Carte bureau : fonds, placement, glisser-déposer, tracés | Bureau | Carte | 4 |

### L'ordre : du plus risqué au plus simple

| Vague | Écrans | Pourquoi à ce rang |
|---|---|---|
| 1 | T1 → T4 | La contrainte la plus dure du produit : 3 gestes, 15 s, des gants |
| 2 | B2, B3 | Le parcours où un utilisateur abandonne (P0, RG-27) |
| 3 | B6, B7, B5, T5 | La plante, objet central, sur ses deux contextes |
| 4 | B4, B8, B9 | Préparer, rattraper, corriger |
| 5 | B1, B10, B11, B12 | Écrans simples, peu de décisions |
| 6 | B13, T7, T6 | La carte, construite après le cœur (CDC §6) |

Les wireframes se revoient par vague. L'interface commence quand les vagues 1
et 2 sont dessinées : ce sont elles qui fixent les composants principaux.

> **M8** — Chaque écran est dessiné dans **ses états**, pas seulement dans son
> état heureux : **vide** (premier jour), **normal**, **chargé** (150 plantes,
> les noms les plus longs), **erreur**, et pour le terrain **non envoyé**.
> L'état vide et l'état chargé sont ceux que l'on oublie, et ceux qui font
> abandonner.

### Les composants, à l'étape 2

Une première liste, à confirmer par les wireframes : Bouton, Champ, Liste de
choix, Case à cocher, Ligne d'échéance, Pastille d'état, Explication
d'échéance, Vignette de plante, Photo, Bandeau de message (information,
erreur, non envoyé), Confirmation, Navigation terrain, Navigation bureau,
Tableau (en-tête, ligne), Filtre, Icône.

**Où on en est (20/10/2026).** Sept composants existent sur la page 6, avec
les 19 `Icône/<nom>` Lucide (D-85). Toutes leurs apparitions dans les écrans
de l'interface en sont des instances ; les wireframes ne changent pas.

| Composant | Variantes | Propriétés |
|---|---|---|
| `Bouton` | `variante = principale \| secondaire \| inactif` | `libelle` ; `avecIcone`, `avecLibelle`, `avecIconeApres` ; `icone`, `iconeApres` (permutation) |
| `Case` | `etat = vide \| cochee` | — |
| `Marque/Logo` | — | — |
| `Pastille` | `etat = en-retard \| a-faire \| fait \| hors-saison \| non-envoye \| neutre`, `forte = non \| oui` | `libelle` |
| `Champ` | `etat = vide \| rempli \| erreur` | `valeur` ; `liste` (le chevron d'une liste de choix) |
| `Bandeau` | `sorte = succes \| non-envoye \| attention \| erreur` | `titre`, `detail` ; `avecDetail`, `avecAction` (un `Bouton` imbriqué) |
| `Échéance/Ligne` | `etat = a-faire \| en-retard` | `plante`, `detail`, `rang` ; `avecNumero` (la tournée) ; un `Bouton` « Fait » imbriqué |

Écartés par M9, parce qu'un seul écran s'en sert : une ligne d'échéance du
bureau (`Échéance/Rang`, seulement B4), les cartes de B6 et de T5, le motif
de B9, la zone de collage de B3. Les autres états (hors saison sur une
fiche, par exemple) restent portés par les variables `etat/*` appliquées aux
calques.

> **M9** — Un composant n'est créé que si> **M9** — Un composant n'est créé que si **au moins deux écrans** s'en
> servent. Sinon c'est un élément de l'écran. La bibliothèque reste à la
> taille du produit, et chaque composant créé correspond à un composant React
> à écrire et à tester.

---

## 5. La charte

### Accessibilité : WCAG 2.2 niveau AA, et RGAA

| Exigence | Bureau | Terrain |
|---|---|---|
| Contraste du texte | 4,5:1 au moins | **7:1** (plein soleil, CDC §8.5) |
| Cible tactile ou cliquable | 24 × 24 px au moins | **48 px** (gants, CDC §8.5) |
| Texte courant | lisible à distance de bureau | plus grand qu'au bureau, fixé par le mode `Terrain` |

- **Un état ne se dit jamais par la couleur seule** : « en retard » porte une
  icône et un mot, pas seulement du rouge.
- Focus visible sur tout élément actif, et ordre de tabulation logique au
  bureau.
- Toute image utile a un texte de remplacement prévu dans l'annotation de
  l'écran.

### Terrain

- **Une action principale par écran**, placée dans la zone du pouce, en bas.
- Au plus **4 champs obligatoires** (CDC §2.2). La date vaut « maintenant » par
  défaut, la plante proposée est la dernière consultée ou la plus proche.
- L'utilisateur sait toujours si ce qu'il vient de saisir est enregistré
  (RG-50) : l'état « non envoyé » est dessiné, pas laissé au développement.

### Bureau

- Densité d'information élevée : tableaux, filtres, édition en masse, clavier.
- Les saisies en lot (B3, B8) ne demandent jamais d'ouvrir une fiche par ligne.

### Contenus

- En français, sans faux texte : des noms de plantes, de zones et de règles
  vraisemblables, dont les plus longs (« Pommier 'Reine des reinettes' — rang
  du haut »).
- Dates écrites comme dans l'interface finale (« mar. 21 oct. »).
- Jamais les données réelles du jardin pilote (§3).

### Mesure

> **M10** — La maquette suit la mesure du CDC §1.5 : une seule famille de
> police, une palette courte, un thème clair, pas d'animation autre que le
> retour d'une action. Ce qui serait ajouté « parce que les produits finis en
> ont » attend un besoin.

---

## 6. Le passage aux agents de développement

- Chaque écran validé est exporté en image dans `docs/maquette/`
  (`figcraft-cli export … --node <cadre>`), avec un index
  `docs/maquette/README.md` : écran → image → parcours → critères
  d'acceptation (§10 du CDC).
- Les variables sont transcrites dans le code sous les mêmes noms (M5), par
  `design/interface/exporter_css.py` : une propriété CSS par variable
  (`fond/page` → `--fond-page`), les alias conservés en `var(…)`, les tailles
  en px, le mode Terrain sous `[data-densite="terrain"]`. Le fichier
  `design/interface/variables.css` est régénéré à chaque construction et ne se
  modifie jamais à la main (O23, levé le 20/10/2026).

> **M11** — Un agent qui développe un écran le compare à son image validée.
> Un écart nécessaire (une contrainte technique, un état manquant) se
> **signale** dans la demande de fusion ; il ne s'improvise pas. C'est la même
> règle que pour l'attendu d'un test (RG-49).

> **M12** — Un écran sans maquette ne se développe pas (depuis le 19/10/2026 :
> une maquette dessinée suffit, la revue de Léon vient après), sauf les
> écrans d'administration technique, qui n'existent pas en V1.

**Écarté :** les tests de régression visuelle par comparaison d'images. Ils
coûtent cher à stabiliser et ne servent qu'une fois l'interface figée ; ils se
posent au plus tôt après la V1.

---

## 7. Décisions

| # | Décision | Enjeu |
|---|---|---|
| M1 | Ni couleur, ni police, ni composant avant la validation des wireframes | On corrige le parcours avant l'apparence |
| M2 | Un wireframe terrain se valide en le manipulant sur le téléphone | Les gestes se comptent, ils ne se devinent pas |
| M3 | Les valeurs ne vivent que dans `Primitives` | La marque change à un seul endroit |
| M4 | Composants et écrans ne se lient qu'aux variables sémantiques et de densité | Sinon un écran ne suit pas la marque |
| M5 | Mêmes noms de variables dans la maquette et dans le code | Deux endroits à changer, pas cinquante |
| M6 | Contrôle automatique : zéro valeur non liée avant validation | Une valeur en dur casse le jour de la marque |
| M7 | Le fichier vit dans le dépôt | La maquette décrit le produit livré |
| M8 | Chaque écran dessiné dans ses états, dont vide, chargé et non envoyé | Ce sont les états qui font abandonner |
| M9 | Un composant n'existe que s'il sert à deux écrans | La bibliothèque reste à la taille du produit |
| M10 | Une police, une palette courte, un thème clair, presque pas d'animation | Mesure du CDC §1.5 |
| M11 | Un écart à la maquette se signale, ne s'improvise pas | Même règle que RG-49 |
| M12 | Pas d'écran développé sans maquette validée | Les agents n'inventent pas l'ergonomie |

## 8. Points ouverts

| # | Question | Quand |
|---|---|---|
| O22 | Le contrôle M6 se fait-il avec `figcraft-cli` (lecture des liaisons de variables du fichier) ? **Levé le 20/10/2026** : oui, `node.get` rend les liaisons ; voir `design/interface/controle.py` | Au premier composant |
| O23 | `figcraft-cli` sait-il exporter les variables en un format lisible par un script, pour les transcrire dans le code ? **Levé le 20/10/2026** : `variable.export {format: css}`, mis en forme par `design/interface/exporter_css.py` | Avant le premier composant React |
| O24 | Construire les écrans par la ligne de commande ou par le serveur MCP de FigCraft : lequel tient le mieux ? **En partie levé le 19/10/2026** : `figcraft-cli run` construit des cadres, des textes et des liens de prototype (essai à deux écrans, lien enregistré dans le fichier). Les identifiants rendus sont des entiers attribués dans l'ordre de création ; un script ne peut pas citer le résultat d'une étape précédente, il faut donc générer les scripts | Au premier wireframe |
| O26 | Le prototype se joue-t-il sur le téléphone par la version web de FigCraft (serveur maison de Léon, adresse hors du dépôt) ? À défaut : une page générée depuis le fichier (images exportées et zones cliquables lues dans ses liens) | **Levé en partie le 20/10/2026** : Léon a joué le prototype au format téléphone par la version web, depuis son PC, et « cela fonctionne bien ». Sur le téléphone lui-même, la version web ne convient pas : un lien de partage n'ouvre que l'application. D'où la page générée (D-92, `design/prototype/generer.py`), jouée dans Chrome le 20/10/2026 (301 zones, aucune erreur). Reste l'essai de Léon sur son téléphone |
| O25 | Jeu d'icônes : licence de Lucide pour un usage commercial, à vérifier. **Levé le 20/10/2026** : ISC (et MIT pour les icônes issues de Feather), usage commercial permis, avis de copyright à conserver | Avant l'étape 2 |
