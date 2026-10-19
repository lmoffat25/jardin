# Questions pour Léon

Les questions d'expérience utilisateur que ni le CDC, ni une maquette validée,
ni la charte ne tranchent (README §3). Chacune : la question, l'écran ou le
parcours concerné, les options avec la recommandation en premier, ce qui attend
la réponse. **Léon répond sous « Réponse »** ; la passe suivante reprend le
sprint.

Un sprint qui attend porte `**En attente :** Q-UX-n` dans son en-tête de
`tasks.md`. Les questions ci-dessous sont posées dès l'écriture du plan, pour
que la réponse arrive avant le sprint.

## Sommaire

- [Q-UX-1 — Comment être prévenu](#q-ux-1--comment-être-prévenu)
- [Q-UX-2 — Une ligne fautive dans un lot](#q-ux-2--une-ligne-fautive-dans-un-lot)
- [Q-UX-3 — Les noms de « 12 pommiers »](#q-ux-3--les-noms-de--12-pommiers-)
- [Q-UX-4 — Une intervention datée dans le futur](#q-ux-4--une-intervention-datée-dans-le-futur)
- [Q-UX-5 — Les photos dans l'export](#q-ux-5--les-photos-dans-lexport)
- [Q-UX-6 — Archiver une zone qui contient des plantes](#q-ux-6--archiver-une-zone-qui-contient-des-plantes)
- [Q-UX-7 — Une règle que l'utilisateur a écrite lui-même](#q-ux-7--une-règle-que-lutilisateur-a-écrite-lui-même)

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
1. **Recommandé — rien n'est créé**, et les lignes fautives sont signalées par
   leur numéro : tu corriges et tu renvoies le tout ; jamais de lot à moitié
   créé à démêler.
2. Les bonnes lignes sont créées, les fautives te reviennent seules à corriger.

**Ce qui attend :** sprint 7b (V85, V86).
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

**Ce qui attend :** sprint 7b (V85).
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

**Ce qui attend :** une tâche du sprint 8a (le reste du sprint avance).
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

**Ce qui attend :** une tâche du sprint 12 (V122).
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

**Ce qui attend :** sprint 7c (V92).
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

**Ce qui attend :** sprint 9 (V106).
**Réponse :**
