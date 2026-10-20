# Questions pour Léon

Les questions d'expérience utilisateur que ni le CDC, ni une maquette validée,
ni la charte ne tranchent (README §3). Chacune : la question, l'écran ou le
parcours concerné, les options avec la recommandation en premier, ce qui attend
la réponse. **Léon répond sous « Réponse »**, dans le clone principal, sans geste git :
`scripts/plan/porter-reponses.sh` porte la réponse sur `main` par une demande
de fusion et retire le marqueur du sprint (décision D-30) ; la passe suivante
reprend le sprint.

Un sprint qui attend porte `**En attente :** Q-UX-n` dans son en-tête de
`tasks.md`, et ne contient que le travail qui dépend de la question (D-31). Les questions ci-dessous sont posées dès l'écriture du plan, pour
que la réponse arrive avant le sprint.

## Sommaire

- [Q-UX-1 — Comment être prévenu](#q-ux-1--comment-être-prévenu)
- [Q-UX-2 — Une ligne fautive dans un lot](#q-ux-2--une-ligne-fautive-dans-un-lot)
- [Q-UX-3 — Les noms de « 12 pommiers »](#q-ux-3--les-noms-de--12-pommiers-)
- [Q-UX-4 — Une intervention datée dans le futur](#q-ux-4--une-intervention-datée-dans-le-futur)
- [Q-UX-5 — Les photos dans l'export](#q-ux-5--les-photos-dans-lexport)
- [Q-UX-6 — Archiver une zone qui contient des plantes](#q-ux-6--archiver-une-zone-qui-contient-des-plantes)
- [Q-UX-7 — Une règle que l'utilisateur a écrite lui-même](#q-ux-7--une-règle-que-lutilisateur-a-écrite-lui-même)
- [Q-UX-8 — Au-delà de combien d'échéances on groupe](#q-ux-8--au-delà-de-combien-déchéances-on-groupe)
- [Q-UX-9 — Ce qui est « urgent »](#q-ux-9--ce-qui-est--urgent-)
- [Q-UX-10 — La tournée sur la carte terrain](#q-ux-10--la-tournée-sur-la-carte-terrain)
- [Q-UX-11 — Une intervention saisie après coup](#q-ux-11--une-intervention-saisie-après-coup)
- [Q-UX-12 — Mot de passe oublié](#q-ux-12--mot-de-passe-oublié)
- [Q-UX-13 — Supprimer un type d'intervention déjà utilisé](#q-ux-13--supprimer-un-type-dintervention-déjà-utilisé)
- [Q-UX-14 — Choisir la variante de conduite à la création](#q-ux-14--choisir-la-variante-de-conduite-à-la-création)
- [Q-UX-15 — Saisir les plantes zone par zone](#q-ux-15--saisir-les-plantes-zone-par-zone)
- [Q-UX-16 — Altitude incertaine à la création du jardin](#q-ux-16--altitude-incertaine-à-la-création-du-jardin)
- [Q-UX-17 — Trop d'essais de connexion](#q-ux-17--trop-dessais-de-connexion)

---

## Q-UX-1 — Comment être prévenu

**Question :** quand l'enchaînement s'arrête (acte qui t'engage) ou que tous
les sprints restants attendent une réponse, comment veux-tu être prévenu ? (O27)
**Concerne :** ta façon de suivre le chantier, pas un écran du produit.
**Options :**
1. **Recommandé — un mail par `envoyer-mail`**, avec la fin de `run.log` et le
   lien vers ce fichier : une trace relisible, une preuve d'envoi.
2. Une notification sur le téléphone : plus immédiate, sans trace à relire.
3. Les deux.

**Ce qui attend :** rien ne bloque. Sans réponse, le script ne prévient pas
(`ENCHAINER_PREVENIR` vide) et tu relis `run.log`.
**Réponse :**

## Q-UX-2 — Une ligne fautive dans un lot

**Question :** quand tu crées 150 plantes d'un coup et qu'une ligne est fautive
(espèce inconnue, nom déjà pris dans la zone), que se passe-t-il ?
**Concerne :** B3 (inventaire en lot), parcours P0, critère 1.
**Options :**
1. **Recommandé — ce que montre la maquette B3 · 04** : les bonnes lignes sont
   importées, les fautives sont signalées par leur numéro et restent à
   corriger ; une espèce absente de la bibliothèque donne une plante sans règle,
   signalée (« Néflier… importé sans règle ») ; une zone inconnue propose
   « Créer la zone » ; le bouton dit le nombre réel (« Importer 11 plantes »).
2. Rien n'est créé tant qu'une ligne est fautive : tu corriges et tu renvoies
   le tout ; jamais de lot à moitié créé, mais tout est bloqué par une ligne.

**Ce qui attend :** sprint 7d (V85, V86, V87).
**Réponse :**

## Q-UX-3 — Les noms de « 12 pommiers »

**Question :** une ligne « 12 pommiers » dans la zone Verger crée douze
plantes ; le nom doit être unique dans la zone (RG-13). Comment s'appellent-elles ?
**Concerne :** B3 (lot), critère 24, puis le placement sur la carte (B13).
**Options :**
1. **Recommandé — « Pommier 1 » à « Pommier 12 »**, en continuant la
   numérotation si « Pommier 3 » existe déjà ; tu renommes ensuite si tu veux.
2. Un nom par plante, saisi dans le lot (douze lignes au lieu d'une).
3. Le nom suivi du repère (« Pommier rang 2 ») quand un repère est donné, sinon
   l'option 1.

La maquette B3 · 04 montre l'option 1 (« 12 plantes : Pommier 1 à Pommier 12 ·
FRU-002 à FRU-013 »).

**Ce qui attend :** sprint 7d (V85).
**Réponse :**

## Q-UX-4 — Une intervention datée dans le futur

**Question :** si une intervention arrive avec une date postérieure à demain
(erreur de saisie, horloge du téléphone fausse), l'outil la refuse-t-il ?
**Concerne :** T2–T4 (saisie terrain), B8 (saisie au bureau).
**Options :**
1. **Recommandé — refus au-delà de demain**, avec le message « Cette date est
   dans le futur. » : demain est toléré pour un téléphone à l'heure d'un autre
   fuseau.
2. Acceptée telle quelle (H9 : jamais refusée pour une raison métier).
3. Acceptée, mais ramenée à aujourd'hui et signalée.

**Ce qui attend :** sprint 8c (V133) ; le reste de la saisie (8a) avance.
**Réponse :**

## Q-UX-5 — Les photos dans l'export

**Question :** l'export du jardin (critère 8) contient-il les photos, et sous
quelle forme ?
**Concerne :** B12 (export).
**Options :**
1. **Recommandé — les images d'affichage (1 200 px)** par défaut, une case
   « avec les originaux » pour l'archive complète : l'export reste léger et
   lisible, l'original reste récupérable.
2. Toujours les originaux : export complet mais lourd (plusieurs Go à terme).
3. Aucune photo, seulement leurs noms de fichier : export le plus léger, mais
   incomplet.

**Ce qui attend :** sprint 12b (V134) ; le reste de l'export (12a) avance.
**Réponse :**

## Q-UX-6 — Archiver une zone qui contient des plantes

**Question :** que se passe-t-il quand tu archives une zone qui contient encore
des plantes actives ?
**Concerne :** B3 (zones).
**Options :**
1. **Recommandé — refus**, avec le nombre de plantes : « Cette zone contient
   12 plantes. Déplace-les ou archive-les d'abord. »
2. Les plantes sont archivées avec la zone, après confirmation.
3. On te demande vers quelle zone les déplacer, puis la zone est archivée.

**Ce qui attend :** sprint 7e (V92).
**Réponse :**

## Q-UX-7 — Une règle que l'utilisateur a écrite lui-même

**Question :** pour une plante absente de la bibliothèque, tu écris tes propres
règles (« je tonds tous les 10 jours »). Comment l'outil la présente-t-il ?
**Concerne :** B6 (fiche plante), B9 (réglage d'une règle), et la phrase
d'explication au tableau de bord (B4, T1).
**Options :**
1. **Recommandé — « règle que vous avez définie »** dans l'explication, sans la
   mention « indicative » réservée à la bibliothèque.
2. La même présentation qu'une règle « indicative » de la bibliothèque.
3. Aucune mention : seule la date compte.

**Ce qui attend :** sprint 9b (V106).
**Réponse :**

## Q-UX-8 — Au-delà de combien d'échéances on groupe

**Question :** au tableau de bord, quand une même intervention est due sur
beaucoup de plantes d'une zone (« Tailler 14 rosiers »), au-delà de combien de
plantes les montre-t-on en une seule ligne dépliable ?
**Concerne :** B4 (tableau de bord), T1 (à faire), critère 11.
**Options :**
1. **Recommandé — au-delà de 10** : jusqu'à 10, chaque plante a sa ligne ;
   au-delà, une ligne « Tailler 14 rosiers — Roseraie » qui se déplie.
2. Au-delà de 5 : un tableau plus court, plus de dépliages.
3. Toujours groupé par zone et par type, quel que soit le nombre.

**Ce qui attend :** rien ne bloque. Le sprint 6 construit avec 10 (décision
D-24, provisoire) ; une autre réponse deviendra un sprint de correction.
**Réponse :**

## Q-UX-9 — Ce qui est « urgent »

**Question :** une échéance est dite « urgente » quand la fin de sa fenêtre
approche. À combien de jours de la fin ?
**Concerne :** B4, T1 (mise en avant), phrase d'explication.
**Options :**
1. **Recommandé — 15 jours** : le temps d'organiser un samedi de travail.
2. 7 jours : moins d'alertes, plus tardives.
3. Une part de la durée de la fenêtre (O6) : une fenêtre de 4 mois et une de
   3 semaines n'ont pas la même urgence.

**Ce qui attend :** rien ne bloque. Le sprint 3b construit avec 15 (décision
D-18, provisoire) ; une autre réponse deviendra un sprint de correction.
**Réponse :**

## Q-UX-10 — La tournée sur la carte terrain

**Question :** sur la carte du téléphone, la liste de la tournée est-elle un
panneau fixe sous la carte, ou un tiroir qu'on remonte d'un glissé ?
**Concerne :** T7 (wireframes, vague 6).
**Options :**
1. **Recommandé, dessiné — panneau fixe** sous la carte, avec un bouton
   « Modifier l'ordre » : aucun geste caché, cible de 48 px, même logique que
   le bouton « Carte » de T1 (décision de Léon du 19/10/2026).
2. Un tiroir à glisser vers le haut : plus de carte visible, mais un geste à
   découvrir et difficile avec des gants.

**Ce qui attend :** rien ne bloque ; la maquette porte l'option 1.
**Réponse :**

## Q-UX-11 — Une intervention saisie après coup

**Question :** une taille faite en février et saisie en avril, alors que
l'échéance a été consignée « manquée » entre-temps : que devient l'échéance ?
**Concerne :** B8 (saisie au bureau), historique de B6.
**Options :**
1. **Recommandé, dessiné — elle devient « soldée »** à la date réelle de
   l'intervention : l'historique dit ce qui a été fait, et la suivante se
   calcule depuis cette date.
2. Elle reste « manquée », l'intervention s'ajoute à côté : l'historique garde
   la trace du retard de saisie, mais affirme une chose fausse.

**Ce qui attend :** rien ne bloque ; la maquette porte l'option 1 (B8 · 03).
**Réponse :**

## Q-UX-12 — Mot de passe oublié

**Question :** l'écran de connexion propose-t-il « mot de passe oublié » en V1 ?
**Concerne :** B1 (vague 5).
**Options :**
1. **Recommandé, dessiné — non en V1** : les comptes sont créés à la main pour
   le jardin pilote ; un mot de passe perdu se réinitialise par l'administrateur.
2. Oui, par un lien envoyé par mail : il faut alors un envoi de mail fiable
   dès la V1, à installer et à surveiller.

**Ce qui attend :** rien ne bloque. L'option 2 devient nécessaire avant toute
ouverture à des clients.
**Réponse :**

## Q-UX-13 — Supprimer un type d'intervention déjà utilisé

**Question :** un type d'intervention qui figure dans l'historique peut-il être
supprimé du catalogue ?
**Concerne :** B11 (vague 5).
**Options :**
1. **Recommandé, dessiné — il est masqué, pas supprimé** : il disparaît des
   listes de saisie, l'historique continue de l'afficher.
2. Suppression interdite tant qu'il est utilisé.
3. Suppression avec remplacement par un autre type dans tout l'historique.

**Ce qui attend :** rien ne bloque ; cohérent avec le filtre `TypesVisibles`
(D-55).
**Réponse :**

## Q-UX-14 — Choisir la variante de conduite à la création

**Question :** comment l'écran de création d'une plante demande-t-il la variante
de conduite d'une espèce qui en a (RG-51, framboisier remontant ou non) ?
**Concerne :** B7 (vague 4), T-création rapide si elle propose des règles.
**Options :**
1. **Recommandé — un choix à deux boutons sous l'espèce**, qui n'apparaît que
   pour une espèce à variantes, chaque bouton portant une phrase qui aide à
   reconnaître la plante (« donne en été seulement » / « donne aussi à
   l'automne ») ; les règles proposées se mettent à jour au choix.
2. Une liste déroulante « Variante ».
3. Deux entrées dans la recherche d'espèce (« Framboisier — remontant »).

**Ce qui attend :** rien ne bloque ; l'état B7 · 05 est dessiné.
**Réponse :**

## Q-UX-15 — Saisir les plantes zone par zone

**Question :** à la mise en route (B3), saisit-on toutes les plantes dans un seul
tableau avec une colonne « Zone », ou une zone à la fois ?
**Concerne :** B3 · 02 à B3 · 04 (vague 2), B5 « Ajouter des plantes ».
**Options :**
1. **Recommandé, dessiné — une zone à la fois** : une pastille par zone (avec
   le nombre de plantes saisies), un tableau sans colonne « Zone », « Zone
   suivante » pour avancer, chaque pastille pour sauter. On pense à ce qu'on a
   devant soi, sans passer d'un coin à l'autre à chaque ligne. Le collage depuis
   un tableur garde une colonne de zone facultative.
2. Un seul tableau avec une colonne « Zone » (version précédente).

**Ce qui attend :** rien ne bloque. Proposé par Léon le 20/10/2026.
**Réponse :**

## Q-UX-16 — Altitude incertaine à la création du jardin

**Question :** quand la commune est entre 400 et 800 m (RG-52), que fait l'écran
de la réponse « Je ne sais pas » ?
**Concerne :** B2 · 03 (vague 2), V136.
**Options :**
1. **Recommandé, dessiné — garder le profil tiré de l'altitude du centre** de la
   commune, avec sa raison, sans autre question : il se corrige à tout moment
   (RG-26), et une question de plus retarde la première échéance.
2. Ne rien proposer et ouvrir la liste des cinq.

**Ce qui attend :** rien ne bloque. Tranché par la recommandation le 20/10/2026.
**Réponse :**

## Q-UX-17 — Trop d'essais de connexion

**Question :** après cinq essais manqués en une minute, la connexion est
bloquée jusqu'à la fin de la minute. Que dit l'écran ?
**Concerne :** B1 (vague 5), V36.
**Options :**
1. **Recommandé — « Trop d'essais. Réessayez dans une minute. »**, sous le
   champ courriel, à la place de « Identifiants incorrects. » : il dit quoi
   faire, sans compte à rebours à tenir à jour.
2. Le nombre exact de secondes restantes (« Réessayez dans 42 secondes. ») :
   plus précis, mais faux dès qu'on attend sans recharger.
3. Le même « Identifiants incorrects. » : rien ne trahit le blocage, mais un
   mot de passe juste paraît faux pendant une minute.

**Ce qui attend :** rien ne bloque. Appliquée : la recommandation (sprint 2c,
D-82), le 20/10/2026.
**Réponse :**

## Q-UX-18 — La phrase qui explique une échéance

**Question :** chaque échéance dit d'où elle vient (RG-06). Le CDC donne
« Taille du pommier, fenêtre déc.–mars décalée de 15 jours pour votre climat. »,
mais l'outil ne connaît ni le genre ni l'article du nom que tu donnes à une
plante (« Pommier 'Reine des reinettes' », « Haie du fond ») : « du » ne se
calcule pas. Comment la phrase s'écrit-elle ?
**Concerne :** B4 (tableau de bord), B6 (fiche plante, carte d'échéance), T1 ;
la colonne `echeances.explication` (400 caractères).
**Options :**
1. **Recommandé — « Taille · Pommier : règle de la bibliothèque, fenêtre
   déc.–mars, décalée de 15 jours pour votre climat. »**, puis « Règle
   indicative. » quand elle l'est ; « règle ajustée » pour une règle modifiée ;
   « avancée de 15 jours » pour un climat plus doux ; rien sur le climat en
   plaine. Reprend la maquette B6 (« Règle de la bibliothèque : …, décalée de
   7 jours pour votre climat »), sans article à deviner.
2. « Taille de Pommier, fenêtre déc.–mars… » : plus proche du CDC, mais
   « de Pommier », « de Haie du fond » sonnent faux.
3. Ne pas nommer la plante dans la phrase (la carte la nomme déjà) :
   « Règle de la bibliothèque : fenêtre déc.–mars, … ».

**Ce qui attend :** rien ne bloque. Appliquée : la recommandation (sprint 3b,
D-88), le 20/10/2026. Une autre réponse ne change que `Explication::enPhrase`.
**Réponse :**
