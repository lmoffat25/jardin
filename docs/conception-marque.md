# Conception de la marque initiale

Version 0.1 — 21/10/2026
Complète `conception-ecrans.md` (livrable 2 de la révision du 21/10, M13).
Décisions **M14 à M17**.

**Pourquoi ce document.** Une première proposition (trois palettes posées sur
le même écran, une police chacune) a été jugée « pas très recherchée » par
Léon : mêmes cartes, mêmes boutons, même rangement, des choix sans
justification. La marque se conçoit donc avec une méthode, des critères écrits
et des explorations qui diffèrent vraiment, avant d'être appliquée à la
maquette.

**Ce qu'elle n'est pas.** La marque définitive : elle sera reprise avec un
designer. Ce qui en sortira tient dans les `Primitives` et les styles de texte
(M3), pour que la marque du designer la remplace sans toucher aux écrans.

---

## 1. Les étapes

| # | Étape | Produit | Qui décide |
|---|---|---|---|
| 1 | **Entretien** avec Léon (§4) | Ses réponses, telles quelles | Léon |
| 2 | **Plateforme de marque** | Cible, promesse, personnalité (« elle est / elle n'est pas »), ton, territoire visuel | Proposée, validée par Léon |
| 3 | **Tour de la concurrence** | Planche des applications de jardin et d'entretien : couleurs, polices, formes, ce qui est banal dans le secteur | — |
| 4 | **Typographie** (§2) | Présélection de 6 à 8 paires notées, 3 essayées sur de vrais écrans | Léon choisit parmi 3 |
| 5 | **Explorations divergentes** (§3) | Au moins 3 propositions par élément clé, qui diffèrent par la forme ; 3 dispositions des écrans T1 et T5, faites avec la passe sur les wireframes | Léon, sur son téléphone |
| 6 | **Test dehors** | Les planches retenues jouées sur le téléphone, au soleil, avec des gants | Léon |
| 7 | **Convergence** | Le système de design (jetons, styles, composants), puis les `Primitives` de `design/interface/fondations.py` et la maquette rejouée | — |

Le nom du produit est à Léon. Des pistes peuvent être proposées, sans
vérification de disponibilité tant qu'il n'en a pas retenu une.

## 2. La typographie

> **M14** (remplace la partie police de M10, décision de Léon du 21/10/2026)
> — Une **paire de polices** : une pour les titres, une pour le texte. Un seul
> jeu de graisses par police, limité à ce qui sert. Le reste de M10 tient :
> palette courte, thème clair, pas d'animation hors du retour d'une action.

**Critères, notés pour chaque police de la présélection :**

1. Lisibilité sur petit écran en plein soleil : hauteur des minuscules,
   ouverture des formes (`a`, `e`, `c`), distinction `Il1` et `0O`.
2. Chiffres tabulaires (dates et quantités en colonne) — **vérifiés dans le
   fichier de police**, pas déduits d'une page de présentation.
3. Couverture du français (accents, `œ`, guillemets « », espaces fines).
4. Graisses disponibles et rendu aux petites tailles.
5. Licence : usage commercial et embarquement web (SIL OFL de préférence),
   vérifiée sur le fichier `OFL.txt` du dépôt officiel.
6. Poids des fichiers web (sous-ensemble latin).
7. Accord de la paire : contraste de rôle (le titre a un caractère, le texte
   s'efface), proportions compatibles.
8. Ce que la police dit de la marque, au regard de la plateforme (étape 2).

Les fichiers de police viennent du dépôt officiel `google/fonts` (ou de la
fonderie, s'il n'y est pas), téléchargés dans un dossier à part, jamais dans
le dépôt tant qu'une police n'est pas retenue.

## 3. Explorer avant de choisir

> **M15** — Chaque élément clé est exploré en **au moins trois propositions
> qui diffèrent par la forme**, pas seulement par la couleur : carte
> d'échéance, bouton « Fait », action principale, en-tête de la fiche plante,
> état « non envoyé ». Les dispositions des écrans T1 (Aujourd'hui) et T5
> (fiche plante) sont explorées de la même façon, en lien avec la passe sur
> les wireframes (M13).

> **M16** — Chaque choix de marque (police, couleur, forme, disposition) est
> écrit avec **sa raison et ce qui a été écarté**, dans ce document (§6).
> Un choix sans raison écrite ne passe pas à la maquette.

> **M17** — Les explorations se jugent **sur le téléphone**, pas sur l'écran
> de l'ordinateur (comme M2), et passent les contrôles de la charte
> (`conception-ecrans.md` §5) : 7:1 sur le terrain, cibles de 48 px, un état
> jamais dit par la seule couleur. Les contrastes sont calculés par script.

## 4. L'entretien

Les réponses de Léon sont gardées telles quelles, puis reformulées dans la
plateforme de marque. Aucune donnée personnelle ni donnée du jardin pilote
n'entre dans ce dépôt public.

1. Qui est la première personne à qui tu vendrais l'outil ? Décris une
   journée où elle s'en sert.
2. Et dans trois ans : particuliers, jardiniers amateurs avertis,
   professionnels (paysagistes, gestionnaires de domaines) ?
3. Qu'est-ce qu'elle utilise aujourd'hui à la place (carnet, tableur,
   mémoire, une autre appli) ? Qu'est-ce qui l'agace dans ces solutions ?
4. Quelle phrase voudrais-tu qu'elle dise de l'outil à un voisin ?
5. Trois mots pour la personnalité de la marque. Et trois qu'elle ne doit
   surtout pas évoquer.
6. Des marques, applications ou objets dont tu aimes l'allure (pas forcément
   du jardin) ? Et une que tu trouves ratée ?
7. Plutôt outil de travail sobre, ou compagnon chaleureux ? Plutôt
   botanique et naturaliste, ou plutôt technique et précis ?
8. Le tutoiement ou le vouvoiement dans l'interface ?
9. Des couleurs ou des styles à éviter absolument ?
10. Le prix visé et la façon de vendre (abonnement, achat) : est-ce que la
    marque doit faire « haut de gamme » ou « accessible » ?

### Réponses de Léon (21/10/2026)

Reprises au plus près de ses mots. Une mention familiale est remplacée par sa
description, pour que rien de personnel n'entre dans le dépôt.

1. **Premiers utilisateurs** : lui-même, pour son jardin, ses arbres, les
   lieux à entretenir, les plantes à récupérer. Ensuite, un grand domaine
   familial « de la taille d'un petit village », entretenu par un employé, qui
   tient aujourd'hui l'équivalent de l'outil dans un carnet papier.
2. **Dans trois ans** : des paysagistes qui entretiennent les jardins de
   leurs clients.
3. **Aujourd'hui** : le carnet. Le suivi y est moins simple et demande « une
   sacrée discipline pour ne pas perdre le fil » ; sinon on ne le fait pas, et
   le terrain est mal entretenu.
4. **La phrase** : « Avec ça, tu sauras entretenir ton jardin facilement. »
5. **Personnalité** : organisée, ludique, savante (*knowledgeable*). **Ne
   doit pas évoquer** : une association, le bas de gamme.
6. **Allures aimées** : PictureThis, Merlin Bird ID.
7. **Registre** : outil de travail sobre ; botanique et naturaliste.
8. **Tutoiement.**
9. **À éviter** : le violet, le rouge, le flat design.
10. **Modèle** : freemium, abonnement annuel ; la marque doit faire haut de
    gamme.

## 5. Plateforme de marque

*Proposée le 21/10/2026 à partir de l'entretien, à valider par Léon.*

- **Pour qui** : celui qui a plus de terrain que de mémoire. D'abord le
  particulier avec un grand terrain, puis le domaine entretenu par un
  employé, puis le paysagiste qui suit les jardins de ses clients. Le carnet
  papier est le concurrent réel.
- **Promesse** : l'outil tient le fil à ta place. Tu notes en trois gestes ce
  que tu as fait ; il sait quand recommencer, et pourquoi.
- **Personnalité** :

  | Elle est | Elle n'est pas |
  |---|---|
  | Organisée : chaque chose à sa place, l'urgent d'abord | Administrative, un tableur déguisé |
  | Ludique : la satisfaction d'un geste fait, la saison qui se remplit | Enfantine, des badges et des confettis |
  | Savante : elle explique d'où vient chaque échéance, sources à l'appui | Professorale, du jargon de botaniste |
  | Haut de gamme : matière, photo, typographie soignée | Associative, faite de bric et de broc |

- **Ton** : tutoiement, phrases courtes, verbes de jardinier (« tailler »,
  « traiter », « tondre »), l'explication à portée de main mais jamais
  imposée. Le passage du vouvoiement au tutoiement touche tous les écrans :
  il se fait dans la passe sur les wireframes.
- **Territoire visuel** : le naturaliste de terrain, comme dans les
  références aimées : la photo de la plante au premier plan, la précision
  d'un guide de terrain, de la profondeur et de la matière plutôt que des
  aplats.
- **Contraintes de couleur** : ni violet ni rouge **dans la marque**
  (palette, logo, illustrations, action principale). Les couleurs
  **fonctionnelles** sont à part (précision de Léon, 21/10/2026) : une
  urgence, un retard ou une erreur peuvent être en rouge, comme une
  notification. Elles vivent dans les rôles sémantiques `etat/*` et `erreur`,
  et restent doublées d'une icône et d'un mot (`conception-ecrans.md` §5).

## 6. Choix retenus

*(Rempli à mesure des étapes, avec la raison et ce qui a été écarté — M16.)*

**Tour de la concurrence (21/10/2026).** Ce qui est banal dans le secteur :
le vert feuille saturé, la sans-serif neutre et arrondie (Inter, Nunito,
Montserrat, police système), les boutons en pilule sur de grandes cartes
blanches, le ton copain et les badges. Le trio vert sapin, fond crème et
titres à empattements est déjà pris par une application de plantes grand
public. Une palette ne suffit donc pas à se distinguer : la marque part d'un
**objet réel** du monde naturaliste.

**Trois territoires explorés** (M15), choisis parmi les cinq territoires
libres relevés :

| Territoire | L'objet de départ | Pourquoi il sert le produit |
|---|---|---|
| **A · Étiquette de collection** | L'herbier et l'étiquette de jardin botanique, avec son numéro d'accession | Chaque plante a déjà son code (`FRU-001`) : l'outil devient le registre d'une collection, organisé et savant |
| **B · Carnet de terrain** | Le journal daté du naturaliste et sa fiche par espèce | Il reprend le carnet papier que l'outil remplace, et sa discipline sans l'effort |
| **C · Planche botanique** | La planche pédagogique légendée, au trait | Le côté ludique et savant : expliquer une taille par un schéma |

**Écartés comme territoire principal** : la carte en relief estompé, gardée
pour les écrans de carte plutôt que pour l'identité ; le catalogue grainier,
trop proche de A, dont il est fondu.

**Couleurs** : trois couches, marque, neutres, états. Le rouge d'état est
terreux ou corail, posé sur de petites surfaces (pastille, filet, mention
« en retard ») ; le vert « fait » se distingue du vert de marque ; un mot ou
une forme double toujours la couleur (rouge et vert se confondent pour une
partie des daltoniens).

## 7. Outils

| Outil | Sert à | Remarque |
|---|---|---|
| Skill `frontend-design` (plugin Anthropic) | Direction esthétique, typographie, éviter le rendu générique | Pour les planches d'exploration en HTML |
| Plugin `design` (Anthropic) : `design-critique`, `design-system`, `ux-copy`, `accessibility-review` | Critique structurée des planches, documentation du système, rédaction de l'interface | Les connecteurs qu'il déclare (Slack, Notion…) ne sont pas utilisés |
| Pages claude.ai de type « Design » | Les explorations côte à côte, consultables sur le téléphone | Privées, partageables sur demande |
| Page claude.ai de type « Design System » | La référence de la marque une fois choisie | Recopiée dans les `Primitives` (M3) |
| FigCraft (`design/jardin.figcraft`) | La maquette, source des écrans | Inchangé |
| `docs/principes-ux.md` | La grille d'audit appliquée aux explorations | — |
| Script de contraste (Python) | Les rapports WCAG de chaque couple | À verser dans `scripts/design/` à la convergence |

**Écartés :** le plugin `brand-voice` (partenaire), qui dérive une voix d'un
fonds documentaire existant que le produit n'a pas ; les plugins de design
de l'annuaire non relus par Anthropic ou un partenaire (brand-md,
design-superpowers, design-skills…), qui feraient piloter l'agent par des
consignes non vérifiées.
