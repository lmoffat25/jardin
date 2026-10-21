# Référentiel UX pour revoir les wireframes

Produit : SaaS de gestion de jardin (taille, traitements, tonte). Deux usages : **terrain** (téléphone 390×844, dehors, gants, soleil, réseau intermittent, sessions de quelques secondes) et **bureau** (1440 px, inventaire en lot, tableaux, filtres, édition en masse, export). Toutes les URL citées ont été ouvertes.

## 1. Principes généraux

### Heuristiques de Nielsen
Source : https://www.nngroup.com/articles/ten-usability-heuristics/

1. **État du système visible** : chaque tap a un retour immédiat, sinon on tape deux fois (https://www.nngroup.com/articles/visibility-system-status/). « Fait » change la ligne tout de suite ; un relevé hors ligne affiche « non envoyé ».
2. **Monde réel** : « taille », « tonte », « haie côté rue », jamais « intervention » ou « asset ».
3. **Contrôle et liberté** : annuler facile à trouver ; le retour d'un écran superposé le ferme sans remonter de deux niveaux (https://www.nngroup.com/articles/user-control-and-freedom/).
4. **Cohérence** : « fait » a la même forme et la même place dans la liste du jour, la fiche et la tournée.
5. **Prévention des erreurs** : proposer la plante issue du scan ou du contexte plutôt que la faire chercher.
6. **Reconnaître plutôt que se souvenir** : plantes récentes, types fréquents, historique, plutôt qu'un champ vide (https://www.nngroup.com/articles/recognition-and-recall/).
7. **Efficacité** : sélection multiple puis une commande au bureau ; « refaire la même chose » sur le terrain.
8. **Minimalisme** : sur la fiche terrain, rien qui ne serve la prochaine action.
9. **Erreurs** : langage clair et solution proposée (section 4).
10. **Aide** : courte, au point d'usage (rappel sous le champ « dose »).

### Laws of UX
- **Fitts** (https://lawsofux.com/fittss-law/) : cible grande et proche = atteinte vite ; petite cible visée vite = erreurs. « Valider » est large, en bas, séparé de « Annuler ».
- **Hick** (https://lawsofux.com/hicks-law/) : le temps de décision croît avec le nombre d'options. L'étape « type » montre 4 à 6 types fréquents, l'option due aujourd'hui mise en avant, « Autre » pour le reste.
- **Jakob** (https://lawsofux.com/jakobs-law/) : on attend les conventions des autres applis. Onglets en bas, retour en haut à gauche, tri par clic sur l'en-tête.
- **Miller et ses limites** (https://lawsofux.com/millers-law/) : la page déconseille d'utiliser « 7 ± 2 » comme contrainte rigide. On garde le découpage en blocs (identité, échéances, historique), pas un plafond.
- **Proximité** (https://lawsofux.com/law-of-proximity/) et **région commune** (https://lawsofux.com/law-of-common-region/) : proche ou sur un même fond = un groupe. Une échéance = une carte (plante, type, « fait »), espace entre cartes plus grand qu'à l'intérieur.
- **Doherty** (https://lawsofux.com/doherty-threshold/) : répondre en moins de 400 ms. Validation locale immédiate, envoi ensuite, aucun spinner bloquant.
- **Position en série** (https://lawsofux.com/serial-position-effect/) : début et fin d'une série sont mieux retenus. Actions clés aux extrémités de la barre d'onglets.
- **Tesler** (https://lawsofux.com/teslers-law/) : la complexité se déplace vers le système ou vers l'utilisateur. Le système déduit date, position, plante scannée ou fiche ouverte.

### Hiérarchie visuelle
https://www.nngroup.com/articles/visual-hierarchy-ux-definition/ : contraste, taille, regroupement ; au plus 3 tailles de texte, 3 niveaux de contraste, 2 grands éléments. « If everything is contrasted, then nothing stands out. » Un seul point focal par écran terrain ; test du flou sur chaque wireframe.

### Divulgation progressive
https://www.nngroup.com/articles/progressive-disclosure/ : options fréquentes d'abord, avancées au second niveau ; au-delà de deux niveaux l'utilisabilité chute. Le relevé terrain montre type, plante, valider ; note, photo, dose sont derrière un « Détails » visible et bien nommé.

## 2. Règles des plateformes mobiles

### Apple Human Interface Guidelines
- **Cibles** : 44×44 pt par défaut sur iOS, 28×28 pt minimum absolu ; espacement aussi important que la taille, environ 12 pt autour d'un élément avec fond, 24 pt sans fond. https://developer.apple.com/design/human-interface-guidelines/accessibility
- **Boutons** : un ou deux boutons proéminents par vue ; jamais le rôle principal sur une action destructrice. https://developer.apple.com/design/human-interface-guidelines/buttons
- **Barres du haut** : boutons Retour et Fermer standard, en symbole ; titre de moins de 15 caractères ; 3 groupes d'éléments au plus, le reste dans un menu « Plus » ; éviter les fonds de barre teintés. https://developer.apple.com/design/human-interface-guidelines/toolbars
- **Safe areas et image pleine largeur** : contenu et contrôles dans la zone sûre (barre d'état, Dynamic Island, indicateur d'accueil), fond étendu en plein écran sous les barres, et un effet de bord au défilement plutôt qu'un fond opaque sous les contrôles. https://developer.apple.com/design/human-interface-guidelines/layout — Fiche plante : la photo passe sous la barre d'état, le bouton retour superposé reste dans la safe area.

### Material Design 3
m3.material.io ne s'affiche pas à la lecture (rendu JavaScript) ; ces règles viennent de la documentation Android et du dépôt officiel des composants Material, qui appliquent M3.
- **Cible de 48 dp minimum** pour tout élément interactif ; un composant maison doit la garantir. https://developer.android.com/develop/ui/compose/accessibility/api-defaults
- **FAB** : une seule action principale, ancrée en bas à droite. « Je viens de faire » est le FAB de l'accueil terrain. https://developer.android.com/develop/ui/compose/components/fab
- **Barre du bas** : navigation principale et actions clés, FAB à droite. https://developer.android.com/develop/ui/compose/components/app-bars
- **Top app bar transparente sur image** : barre sans fond, icônes claires, contraste à vérifier car les images varient ; cercle possible derrière l'icône de navigation ; la barre prend un fond au défilement. https://github.com/material-components/material-components-android/blob/master/docs/components/TopAppBar.md

### Zone du pouce (Steven Hoober)
https://www.uxmatters.com/mt/archives/2013/02/how-do-users-really-hold-mobile-devices.php : sur 780 observations, 49 % à une main, 36 % tenu d'une main et touché de l'autre, 15 % à deux mains ; un tiers des usages à une main se font de la gauche ; la prise change toutes les quelques secondes. L'auteur précise ensuite que ses schémas de portée étaient trop simplifiés et que le centre est touché plus vite et plus juste que les bords. Donc : actions principales en bas et au centre, rien de fréquent dans les coins hauts, pas de conception pour la seule main droite.

### WCAG 2.2
- **2.5.8 (AA)** : cible d'au moins 24×24 px CSS, ou espacée pour qu'un cercle de 24 px centré dessus ne touche aucune autre cible. Un plancher, loin de 44 pt / 48 dp. https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html — 2.5.5 (AAA) : 44×44 px CSS. https://www.w3.org/WAI/WCAG22/Understanding/target-size-enhanced.html
- **1.4.3 (AA)** : 4,5:1 pour le texte, 3:1 pour le grand texte (18 pt ou 14 pt gras), sans arrondi. https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html
- **1.4.6 (AAA)** : 7:1, et 4,5:1 pour le grand texte. https://www.w3.org/WAI/WCAG22/Understanding/contrast-enhanced.html
- **1.4.11 (AA)** : 3:1 pour contours et états des contrôles et graphiques utiles (pastilles de statut, tracés de carte). https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html
- **2.4.7 (AA)** : focus clavier visible, surtout au bureau. https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html

## 3. Terrain et plein air

- **Soleil** : le reflet gêne même un texte contrasté et rend un texte pâle presque illisible ; le gris se lit aussi comme « désactivé ». https://www.nngroup.com/articles/low-contrast/ — Viser 7:1 (1.4.6) sur les écrans terrain.
- **Gants, mouvement, une main** : cible d'au moins 1 cm × 1 cm, plus grande pour l'action principale et l'usage en mouvement ; impact du pouce d'environ 2,5 cm ; deux actions côte à côte plutôt qu'empilées. https://www.nngroup.com/articles/touch-target-size/ — Aucune source ouverte ne chiffre l'usage avec gants : prendre l'action principale nettement au-dessus de 44 pt / 48 dp, et aucun geste fin (appui long, balayage précis) comme seul chemin.
- **Sessions courtes** : une microsession dure moins de 15 s ; réduire le coût d'interaction, rendre les tâches clés accessibles sans navigation. https://www.nngroup.com/articles/mobile-microsessions/ — L'appli s'ouvre sur la liste du jour.
- **Moins de saisie, état sauvegardé** : caméra plutôt que code tapé, position plutôt qu'adresse ; connexion inégale ; sauver l'état en permanence pour reprendre une tâche interrompue. https://www.nngroup.com/articles/mobile-ux/ — Scan QR, plante proposée par la carte, et « non envoyé » sur chaque ligne non synchronisée avec un compteur visible jusqu'à l'envoi.
- **Annuler plutôt que confirmer** : confirmer seulement le grave ou l'irréversible, sinon on valide sans lire ; nommer les boutons par leur effet ; offrir l'annulation. https://www.nngroup.com/articles/confirmation-dialog/ — « Fait » s'applique avec « Annuler » ; seule la suppression demande « Supprimer le tilleul » / « Garder ». Un toast disparaît en quelques secondes : bon pour informer, mauvais pour une erreur. https://www.nngroup.com/articles/indicators-validations-notifications/
- **Défauts et formulaires courts** : la plupart gardent la valeur par défaut ; choisir la plus probable, jamais une arbitraire. https://www.nngroup.com/articles/the-power-of-defaults/ — Date = maintenant, plante = fiche ou scan, type = échéance due. Libellés visibles hors du champ. https://www.nngroup.com/articles/form-design-placeholders/

## 4. Bureau : tableaux, filtres, masse, import

- **Tableaux** (https://www.nngroup.com/articles/data-tables/) : servir trouver, comparer, modifier une ligne, agir sur des lignes. Identifiant lisible en première colonne (plante et emplacement), en-têtes et première colonne figés, colonnes masquables et réordonnables sans glisser-déposer obligatoire, une ou deux actions en ligne au plus, édition dans un panneau latéral non modal.
- **Filtres** : avec « Appliquer » quand les critères sont précis ou la réponse lente ; ne pas remonter la page pendant la sélection. https://www.nngroup.com/articles/applying-filters/ — Toujours un récapitulatif des filtres appliqués, chacun retirable, avec « Tout effacer » ; un compteur seul ne suffit pas. https://baymard.com/blog/how-to-design-applied-filters
- **Édition en masse** : « Tout sélectionner », barre d'actions contextuelle, retour clair et annulation. https://www.nngroup.com/videos/bulk-actions-design-guidelines/ — La confirmation dit combien de lignes elle touche.
- **Erreurs et import** : message près de la source, en clair, avec la correction, saisie conservée (https://www.nngroup.com/articles/error-message-guidelines/) ; récapitulatif en haut jamais seul, champ fautif marqué sur place par couleur et icône (https://www.nngroup.com/articles/errors-forms-design-guidelines/). Aucune source ouverte ne traite l'import de fichier ; en appliquant ces règles : importer les lignes valides, lister les rejetées avec numéro, colonne et raison, corrigeables sur place.
- **Export et options avancées** : exporter vers le tableur, cacher l'avancé sans le supprimer. https://www.nngroup.com/articles/complex-application-design/

## 5. Grille d'audit (oui/non, un écran à la fois)

| # | Question | Principe |
|---|---|---|
| 1 | L'action principale est-elle dans le tiers bas de l'écran ? | Hoober, FAB |
| 2 | Un seul bouton proéminent, deux au plus ? | Apple Buttons |
| 3 | Toute cible fait-elle 44 pt / 48 dp, l'action principale plus ? | Apple, Material, NN/g |
| 4 | Cibles voisines séparées d'au moins 12 pt (24 pt sans fond) ? | Apple, WCAG 2.5.8 |
| 5 | Un élément connu du contexte (plante de la fiche, scan, date) est-il redemandé ? (oui = défaut) | Tesler, défauts |
| 6 | « Je viens de faire » tient-il en 3 gestes avec les défauts ? | Microsessions |
| 7 | L'étape « type » a-t-elle 6 options ou moins, la probable en avant ? | Hick |
| 8 | « Fait » a-t-il un retour immédiat, sans spinner bloquant ? | Heuristique 1, Doherty |
| 9 | « Fait » offre-t-il « Annuler » plutôt qu'une confirmation ? | NN/g confirmations |
| 10 | Seul le destructeur est-il confirmé, avec des boutons nommés ? | NN/g, Apple Buttons |
| 11 | Un relevé non synchronisé est-il marqué sur sa ligne ? | Heuristique 1 |
| 12 | Une saisie interrompue est-elle retrouvée intacte ? | NN/g mobile |
| 13 | Le texte terrain atteint-il 7:1 (4,5:1 partout) ? | WCAG 1.4.6, 1.4.3 |
| 14 | Contours, pastilles et tracés de carte à 3:1 ? | WCAG 1.4.11 |
| 15 | Une information est-elle portée par la seule couleur ? (oui = défaut) | NN/g erreurs |
| 16 | Sur la photo de couverture, le retour est-il dans la safe area et lisible sur image claire ? | Apple Layout, Material |
| 17 | Contenu et contrôles évitent-ils encoche et indicateur d'accueil ? | Apple Layout |
| 18 | Tape-t-on ce qu'un scan ou la position fournirait ? (oui = défaut) | NN/g mobile |
| 19 | Les plantes récentes ou proches sont-elles proposées ? | Reconnaissance |
| 20 | Chaque champ a-t-il un libellé visible hors du placeholder ? | NN/g placeholders |
| 21 | L'avancé est-il derrière un seul niveau « Détails » ? | Divulgation progressive |
| 22 | Une carte est-elle plus serrée en dedans qu'avec ses voisines ? | Proximité, région commune |
| 23 | Le vocabulaire est-il celui du jardinier ? | Heuristique 2 |
| 24 | Navigation et retour sont-ils à leur place usuelle ? | Jakob, Apple Toolbars |
| 25 | Identifiant lisible en première colonne, en-têtes figés ? | NN/g tableaux |
| 26 | Filtres appliqués visibles, retirables, « Tout effacer » ? | Baymard |
| 27 | La sélection affiche-t-elle une barre d'actions, « Tout sélectionner », annulation ? | NN/g masse |
| 28 | L'erreur est-elle à côté du champ ou de la ligne, avec la correction ? | NN/g formulaires |
| 29 | L'import partiel liste-t-il les lignes rejetées (numéro, colonne, raison), corrigeables ? | NN/g erreurs |
| 30 | Le focus clavier est-il visible sur chaque cellule et contrôle ? | WCAG 2.4.7 |
