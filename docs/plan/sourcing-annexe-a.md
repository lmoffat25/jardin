# Sourcing de l'annexe A — bibliothèque de départ

Version 0.1 — 19/10/2026
Applique `docs/protocole-bibliotheque.md` (B1 à B8) aux lignes R1, R2 et butoirs
de l'annexe A du CDC, et aux quatre espèces « à sourcer » du §5.1 (pêcher, vigne,
framboisier, groseillier), plus la transposition R2 de l'arbuste à floraison
printanière. **Ce document propose ; il ne modifie ni le CDC ni le protocole.**
Les conséquences pour ces deux documents sont listées en fin de synthèse, à
trancher par Léon.

---

## Méthode et conventions

**Consultation.** Toutes les pages citées ont été ouvertes le **19/10/2026**,
pendant cette session (B4), par trois recherches menées en parallèle. Aucune
valeur ne vient de mémoire ni de l'annexe A actuelle, qui a servi seulement de
liste de valeurs à confirmer ou infirmer.

**Fiabilité des citations.**
- Citations tirées de PDF : texte extrait par `pdftotext`, littérales.
- Citations tirées de pages HTML : rendues par l'outil de lecture web (qui passe
  par un modèle de résumé), demandées mot pour mot. Très probablement exactes,
  **pas garanties au caractère près**. Celles signalées **[WF]** n'ont pas pu être
  recoupées sur le texte brut. À relire sur la page avant de les figer en YAML.
- Contre-vérifiées directement dans cette session, à l'identique : SNHF cerisier,
  SNHF framboisier, CTIFL chancre bactérien (PDF), décret 2026-829 (AIDA),
  instruction du 17/07/2026 (PDF), communiqué de la préfecture de l'Yonne (PDF),
  page d'accueil de Pépins & Cie.

**Lecture du protocole retenue ici** (à valider par Léon, inscrite pour être
contestable) :
1. **B2, concordance.** `verifiee` = au moins deux sources **indépendantes** qui
   donnent la même valeur. Deux pages d'un même éditeur comptent pour une.
2. **B3, désaccord.** Quand deux sources de rang supérieur (institutionnel ou
   technique) concordent contre une source de vulgarisation, la hiérarchie B3
   tranche et la règle reste `verifiee`, désaccord consigné. Quand le désaccord
   est entre sources de même rang, ou qu'une seule source de rang supérieur
   existe, la règle est `indicative`.
3. **Sites éditoriaux avec boutique.** Gerbeaud (boutique.gerbeaud.com) et
   Jardiner Malin (rubrique « Boutique ») sont des éditeurs de contenu qui vendent
   à côté. Ils sont comptés comme **vulgarisation**, pas comme « sites marchands »
   au sens de B3 (pépiniériste ou jardinerie dont le contenu sert le catalogue).
   Mais **aucune règle `verifiee` ne repose sur eux seuls.** Si Léon les range
   parmi les marchands, les statuts touchés sont signalés règle par règle.
4. **Périodicité.** Quand toutes les sources décrivent le geste comme un rendez-vous
   du calendrier (« en février », « chaque hiver ») et qu'aucune ne donne une
   fréquence pluriannuelle, la périodicité retenue est **1 an** : c'est la lecture
   de la source, signalée « lecture du calendrier ». Une source qui chiffre une
   fréquence pluriannuelle crée un désaccord.
5. **Transcription saisons → dates.** Les sources parlent en mois ou en saisons.
   Une borne écrite `JJ/MM` qui transcrit un mot (« fin d'hiver », « début octobre »)
   est signalée **(transcription)** ; elle n'est pas une valeur citée.

**Climat.** Aucune source ne précise le bassin parisien. Les mentions de région
sont données règle par règle ; à défaut, la valeur est réputée « plaine tempérée »
au sens du CDC (décalage 0), ce qui est une hypothèse.

---

## Synthèse

| Règle | Type | Valeurs proposées (référence) | Statut proposé | Sources retenues |
|---|---|---|---|---|
| Toutes haies — butoirs de nidification | butoirs | début **16/08**, fin **15/03** (exclusion 16/03 → 15/08) | `verifiee` | 6 |
| Pommier, poirier — taille d'hiver | R2 | 01/12 → 31/03, optimale 01/02 → 31/03, 1 an | `verifiee` | 6 |
| Cerisier — taille | R2 | 01/08 → 30/09, 1 an | `verifiee` | 6 |
| Prunier — taille | R2 | 01/08 → 30/09, 1 an | `indicative` | 5 |
| Haie persistante — taille | R2 ×1 (et non ×2) | 16/08 → 30/09, 1 an | `verifiee` | 4 |
| Haie caduque — taille | R2 | 01/11 → 15/03, périodicité à trancher | `indicative` | 4 |
| Arbuste à floraison printanière — taille | R2 (transposition de R4) | 01/04 → 31/07, optimale 01/05 → 30/06 | `indicative` | 5 |
| Arbuste à floraison estivale — taille | R2 | 01/02 → 31/03, optimale 01/03 → 31/03, 1 an | `verifiee` | 5 |
| Rosier (buisson remontant) — taille | R2 | 01/02 → 31/03, 1 an ; « jusqu'en novembre » retiré | `verifiee` | 4 |
| Pelouse — tonte | R1 saisonnier | 0 / 7 / 14 / 7 / 0 j selon le mois | `indicative` | 5 |
| Pêcher — taille de fructification | R2 | 01/02 → 31/03, optimale 01/03 → 31/03, 1 an | `indicative` (conflit B-T1) | 5 |
| Vigne — taille d'hiver | R2 | 01/12 → 31/03, optimale 01/02 → 31/03, 1 an | `verifiee` | 4 (+1 contexte) |
| Framboisier non remontant — taille | R2 | 01/02 → 31/03, 1 an | `verifiee` | 4 |
| Framboisier remontant — taille | R2 | 01/11 → 28/02, 1 an | `verifiee` | 4 |
| Groseillier à grappes — taille | R2 | 01/01 → 15/03, optimale 01/02 → 28/02, 1 an | `verifiee` | 3 |
| Cassissier — taille (hors liste demandée) | R2 | 01/11 → 28/02, 1 an | `verifiee` | 4 |
| Groseillier à maquereau | — | — | **écarté de la liste de départ** | 1 |

### Conséquences pour le CDC, le protocole et le plan (non appliquées)

1. **La période de nidification du CDC est périmée.** Le « 01/04 → 31/07 » de
   RG-37, RG-44, de l'annexe A, du critère 4, de B-T2, B-T12, B-T14 et des jeux
   témoins (`tasks.md`, `JeuTemoin.php` : haie `01/08 → 31/03`) vient de l'arrêté
   PAC de 2015. Les textes en vigueur donnent **16/03 → 15/08**. Voir la section
   « Butoirs de nidification ».
2. **La nature de la contrainte n'est pas celle que dit le CDC.** Pour une haie de
   jardin de particulier, ce n'est **pas une interdiction légale** mais une
   recommandation (préfecture de l'Yonne, OFB et LPO cités par des tiers), doublée
   de l'interdiction permanente de détruire des nids d'espèces protégées. Le régime
   unique de la haie (2027) **exclut** les haies qui forment l'enceinte d'un jardin
   attenant à une habitation. Le CDC qualifie l'usage « Interdiction — légale ou
   écologique » (RG-44) : le mot « écologique » reste juste, « légale » ne l'est
   pas pour ce cas.
3. **Haie persistante : R2 ×2 n'est pas tenable.** Les deux tailles des sources
   (printemps et fin d'été) placent la première en pleine nidification. Une seule
   fenêtre survit aux butoirs.
4. **Pêcher et B-T1.** Les sources placent la taille du pêcher en fin d'hiver
   (février–mars). Or B-T1 interdit à tout fruitier **à noyau** une fenêtre qui
   recouvre décembre → février. Le pêcher ferait échouer B-T1 avec n'importe
   quelle fenêtre sourcée. Il faut soit une exception nominative dans B-T1, soit
   restreindre B-T1 au cerisier et au prunier.
5. **Traitements fruitiers.** L'annexe A les écrit en R3 (hors MVP), mais le
   sprint 4b les attend dans `traitements.yaml`. Ils ne sont pas sourcés ici (hors
   périmètre de la demande). RG-36 permet de les écrire en R2 à deux fenêtres :
   ce sourcing reste à faire.
6. **Framboisier : deux entrées.** Remontant et non remontant n'ont pas la même
   règle ; B-T7 interdit deux règles du même type sur une espèce. Proposition :
   deux espèces de bibliothèque.

---

## Butoirs de nidification (toutes haies)

**Valeurs proposées.**

```yaml
butoirs: [16/08, 15/03]   # exclusion 16/03 → 15/08 inclus, 153 jours (≈ 21,9 semaines)
confiance: verifiee
nature: recommandation pour une haie de jardin ; obligation pour les agriculteurs PAC
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| N1 | Ministère de l'Agriculture, Telepac, fiche conditionnalité 2026 BCAE 8 (PDF, 12/04/2026) | réglementaire | https://www2.telepac.agriculture.gouv.fr/telepac/pdf/conditionnalite/2026/technique/Conditionnalite-2026_fiche-technique_BCAE8_biodiversite.pdf | « Interdiction de taille des haies et des arbres entre le 16 mars et le 15 août » ; « période nidification et de reproduction des oiseaux entre le 16 mars et le 15 août inclus. » (extraction partielle du PDF) |
| N2 | Ministère de l'Agriculture, « La PAC 2023-2027 en un coup d'œil », annexe 14 (PDF, 21/04/2026) | institutionnel | https://agriculture.gouv.fr/telecharger/135138 | « Il est désormais interdit de tailler et/ou de couper les arbres et les haies pendant la période de nidification et de reproduction des oiseaux entre le 16 mars et le 15 août. » |
| N3 | Instruction interministérielle du 17/07/2026, NOR TECL2618420J (Bulletin officiel, PDF) | réglementaire | https://www.bulletin-officiel.developpement-durable.gouv.fr/documents/Bulletinofficiel-0034650/TECL2618420J.pdf | « NB : Pour les haies agricoles soumises à la conditionnalité PAC, la période d'interdiction de travaux actuelle fixée nationalement du 16 mars au 15 août demeure applicable en 2026. » ; la période préfectorale « d'une durée d'au moins 21 semaines » ; « entrée en vigueur interviendra au 1er janvier 2027 » ; exclusion des haies « qui constituent l'enceinte d'un jardin ou d'un parc attenant à une habitation ou qui se situent à l'intérieur de cette enceinte » (vérifié sur le texte extrait) |
| N4 | Préfecture de l'Yonne, communiqué, Auxerre, 24/03/2023 (PDF) | institutionnel | https://www.yonne.gouv.fr/index.php/contenu/telechargement/39931/324707/file/CP_bonnes pratiques taille haies 240323.pdf | « il n'y a pas d'interdiction, pour les particuliers, collectivités ou professionnels, de la taille des haies dans l'Yonne jusqu'en août. Il est cependant fait appel à la responsabilité des particuliers […] pendant la période du 16 mars au 15 août » ; « cette interdiction s'applique exclusivement aux agriculteurs qui bénéficient des aides de la politique agricole commune (PAC). » (vérifié sur le texte extrait) |
| N5 | Département de Loir-et-Cher, livret « Les réglementations encadrant les travaux sur les haies » (PDF, 12/01/2026) | institutionnel | https://www.departement41.fr/app/uploads/2026/04/Livret-reglementations-haies_v3.pdf | « l'interdiction de taille des haies et arbres entre le 16 mars et le 15 août […] Le début de la période d'interdiction peut cependant être reportée par arrêté préfectoral en cas d'intempéries. » |
| N6 | Décret n° 2026-829 du 28/08/2026, art. R.412-51 (texte reproduit par AIDA/INERIS) | réglementaire | https://aida.ineris.fr/reglementation/decret-ndeg-2026-829-280826-relatif-regime-unique-haie | « […] respectent la période d'interdiction de travaux, correspondant à celle de la nidification des oiseaux, qui ne peut être inférieure à 21 semaines, et qui est précisée par les arrêtés préfectoraux prévus au 1° de l'article L. 412-27 » (vérifié) |

Sources de contexte, non comptées pour la valeur :
- LPO Tarn, « Arbres et arbustes : optez pour la taille hivernale » (PDF 2024,
  sans auteur nommé) — https://tarn.lpo.fr/wp-content/uploads/2024/02/Lpo-Taille-hivernale.pdf —
  « La LPO déconseille de tailler et d'élaguer au printemps et à l'été (du 16 mars
  au 31 août) ».
- Que Choisir, Fabrice Pouliquen, 16/03/2024 [WF] —
  https://www.quechoisir.org/actualite-jardin-ecoresponsable-au-printemps-mollo-sur-le-taille-haie-et-la-tondeuse-n119622/ —
  cite la LPO (« de la mi-mars à la fin août ») et l'OFB (« entre le 16 mars et le
  15 août »), et des arrêtés préfectoraux étendus aux particuliers (Territoire de
  Belfort, Vosges).
- Sénat, question écrite Maurey, réponse du 09/07/2015 [WF] —
  https://www.senat.fr/questions/base/2015/qSEQ150616952.html — « Cette période sera
  donc du 1er avril au 31 juillet. » (arrêté du 24/04/2015) : **origine du
  01/04 → 31/07 du CDC.**
- Préfecture de la Haute-Marne, plaquette « Haies » 2019 —
  https://www.haute-marne.gouv.fr/index.php/contenu/telechargement/15169/100195/file/201908-Plaquette_HAIES-Sensibilisation-W5.pdf —
  « Durant la période du 1er avril au 31 juillet, toute intervention […] est
  interdite (au titre de la PAC). » Daté de 2019, **périmé** depuis la PAC 2023.
- Collectivité européenne d'Alsace, plaquette 2011 —
  https://www.alsace.eu/media/6837/cea-plaquette-savoir-faire-haies.pdf — arrêté
  préfectoral local « du 15 mars au 31 juillet ».
- Légifrance, art. L.411-1 du code de l'environnement [WF] —
  https://www.legifrance.gouv.fr/codes/id/LEGIARTI000047297439/2023-03-12 —
  interdiction de « La destruction ou l'enlèvement des oeufs ou des nids ». La
  version affichée est celle « en vigueur du 10/08/2016 au 20/08/2026 » : **la
  version actuelle n'a pas été lue.**

**Désaccords et arbitrage.**
- 01/04 → 31/07 (PAC 2015) contre 16/03 → 15/08 (PAC 2023-2027, en vigueur en
  2026) : le premier est périmé, il est écarté.
- LPO : jusqu'au 31/08, plus strict. Recommandation associative, une seule
  source directement lue ; non retenue comme butoir, mentionnable dans
  l'explication.
- À partir du 01/01/2027, les agriculteurs suivront des dates préfectorales
  (≥ 21 semaines, variables par département). La haie de jardin en est exclue.
  Aucun arrêté 2027 n'a été trouvé pour l'Île-de-France ni les départements
  voisins. La valeur 16/03 → 15/08 reste la référence nationale la plus citée
  pour les particuliers (N4) ; sa durée (≈ 21,9 semaines) est compatible avec le
  plancher de 21 semaines.
- Arrêtés préfectoraux locaux (Alsace, Vosges, Belfort) : relèvent du jardin, pas
  de la bibliothèque nationale (RG-09).

**Statut proposé : `verifiee`** pour les dates 16/03 → 15/08 (N1 à N5
concordants, rang réglementaire et institutionnel). Climat : national, sans
décalage (c'est le rôle d'un butoir).

**Contrôle des cinq profils** sur les deux haies ci-dessous : avec les butoirs
16/08 et 15/03, la haie persistante (16/08 → 30/09) rognée à −15 j donne
16/08 → 15/09 (31 j) et à +21 j donne 06/09 → 21/10 ; la haie caduque
(01/11 → 15/03) rognée à +21 j donne 22/11 → 15/03. Aucune ne recouvre la
nidification, toutes dépassent 14 jours (calcul fait à la main, à confirmer par
B-T13 et B-T14).

---

## Pommier, poirier — taille d'hiver

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 01/12, fin: 31/03, optimal: [01/02, 31/03], libelle: "hors gel"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| P1 | Bruxelles Environnement, « Mon jardin fruitier » (PDF, février 2018) | institutionnel (Belgique, océanique) | https://document.environnement.brussels/opac_css/elecfile/IF_Potager_Jardinfruitier_fruittuin_FR | « La taille se fait généralement en hiver lorsqu'il ne gèle pas pour les arbres à pépin. » ; « avant la montée de la sève […] pendant l'hiver (hors gel). » ; « Les arbres espaliers […] nécessitent une taille annuelle » ; basse-tige « tailler annuellement » ; hautes tiges « ne doivent être taillés qu'une fois par an » |
| P2 | PNR de la Montagne de Reims, guide verger, rédaction Alexandra Pinelle (PDF, 2022) | institutionnel (Champagne) | https://www.parc-montagnedereims.fr/app/uploads/2023/08/guide-verger-web-1.pdf | « Arbres à pépins : ils sont généralement taillés en fin d'hiver, » ; « une taille peut être appliquée chaque année pour augmenter la production de fruits » |
| P3 | CRA-W Gembloux, mémento verger haute tige (PDF, sans date) | institutionnel (Belgique) | https://biodimestica.eu/sites/default/files/linked_docs/Fruits/3-Travaux/Memento_Plantation_VergerHT.pdf | « Les opérations de taille se font en fin d'hiver – février-mars – par temps sec […] et hors de période de fortes gelées. » (taille de formation) |
| P4 | Gerbeaud, X. Gerbeaud, taille de fructification (29/07/2022) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/taille-fructification.php | « le coeur de l'hiver est une saison propice, à condition qu'il ne gèle pas » ; « Pour pommier, poirier, pêcher ou abricotier, la première quinzaine de février est généralement bien adaptée. » |
| P5 | Gerbeaud, Isabelle C., quand tailler un fruitier (07/07/2025) — même éditeur que P4 | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/quand-tailler-arbre-fruitier,1654.html | « la fin de l'hiver (février/mars) pour les arbres à pépins » ; « une à deux fois dans l'année, ou occasionnellement, une fois tous les deux ou trois ans » |
| P6 | Au Jardin, taille des pommiers (02/08/2011) | vulgarisation | https://www.aujardin.info/fiches/taille-pommiers.php | « En hiver après les poiriers (janvier-février) sur les arbres formés. » ; plein vent : « de temps en temps un élagage » |

Complément : Jardiner Malin, fiche pommier (28/09/2026),
https://www.jardiner-malin.fr/fiche/entretien-pommier.html — « pendant le repos
végétatif, hors période de gel », sans mois. SNHF pommier et poirier (Denis
Retournard) : technique seule, aucune période.

**Désaccords et arbitrage.**
- Fenêtre « hiver hors gel » : concordante (P1, P4, P6). Le début 01/12 est une
  **(transcription)** de « hiver » ; aucune source ne donne une date de début.
- Optimale : « fin d'hiver » (P2), « février-mars » (P3, P5), « première quinzaine
  de février » (P4), « janvier-février » (P6). Proposition 01/02 → 31/03, portée
  par P2, P3 et P5. **Le 15/03 de l'annexe actuelle n'est porté par aucune source**,
  il est remplacé par 31/03.
- Périodicité : annuelle pour espaliers et basses-tiges (P1, P2) et même hautes
  tiges (P1). « Tous les 2–3 ans en forme libre adulte » ne repose que sur
  Gerbeaud (P5) et le vague « de temps en temps » de P6, contredits par P1 :
  **retiré**, périodicité 1.
- Si Gerbeaud était exclu : P1, P2, P3 suffisent, statut inchangé.

**Taille en vert (été), intervention distincte, hors liste de départ.** Juillet →
septembre : PNR Reims (P2) « de juillet à septembre » ; Jardins de France (SNHF,
D. Retournard), https://www.jardinsdefrance.org/la-taille-en-vert-acharnement-torture-ou-attentions/ —
« De juin à septembre, l'allongement des pousses herbacées se ralentit » (phrase
de constat plus que de prescription) ; Jardiner Malin,
https://www.jardiner-malin.fr/fiche/taille-vert-fruitiers.html (28/08/2026) —
palissés seulement, « Mi-juillet à mi-septembre », limites à avancer de 10 à 15 j
dans le Nord et l'Est. Statut s'il est ajouté : `indicative` (une seule
prescription institutionnelle nette, P2). Il demande un type d'intervention
distinct (B-T7).

**Statut proposé : `verifiee`.** Climat : Belgique et Champagne pour les sources
institutionnelles, sans précision ailleurs.

---

## Cerisier, prunier — taille

**Valeurs proposées.**

```yaml
# cerisier
type_regle: R2
fenetres: [{debut: 01/08, fin: 30/09, optimal: [01/08, 30/09], libelle: "après récolte, temps sec"}]
periodicite_annees: 1
confiance: verifiee
# prunier : mêmes valeurs, confiance: indicative
```

Aucune optimale plus étroite n'est sourcée : elle est égale à la fenêtre. (Le
« jusqu'au 31/08 » des jeux témoins du plan n'est pas une valeur de bibliothèque.)

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| C1 | SNHF, fiche cerisier, Denis Retournard (sans date) | institutionnel | https://www.snhf.org/fiche-plante/cerisier/ | « Taillez l'arbre uniquement en été ou en début d'automne. » ; « Ne couper que du bois jeune pour éviter la gommose. » (vérifié) |
| C2 | CTIFL, « Le chancre bactérien du cerisier » (PDF, 2014) | technique | https://varietes_cerise.ctifl.fr/varietes_cerise/Media/Rubrique/29/Pseudomonas.pdf | facteur favorisant : « L'époque de taille : taille en hiver, taille pendant une période humide » ; « Ne pas tailler pendant la période automnale pendant laquelle les risques de pénétration de bactéries sont importants, et tailler tardivement au printemps les jeunes arbres ou en été après la récolte pendant une période sèche. » ; pénétration « préférentiellement de novembre à février » (vérifié) |
| C3 | Bruxelles Environnement (= P1) | institutionnel | voir P1 | « Pour les arbres à noyaux, la taille peut également être pratiquée en hiver, mais […] le risque d'une attaque de parasites est alors plus important. Les arbres à noyaux apprécient donc généralement plus une taille durant la récolte. » |
| C4 | PNR Montagne de Reims (= P2) | institutionnel | voir P2 | « Arbres à noyaux : ils peuvent être taillés en fin d'été après la récolte. » |
| C5 | Gerbeaud, X. Gerbeaud, taille fruitière (28/09/2023) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/fp_fruitiers_taille_1.php3 | noyaux taillés « en août ou septembre » |
| C6 | Au Jardin, fiche cerisier (03/07/2011) | vulgarisation | https://www.aujardin.info/plantes/cerisier.php | « Intervenez fin août, début septembre » ; « la moindre coupe provoque l'écoulement de gomme » |

Prunier, en plus de C3 et C4 (qui parlent des « noyaux » en général) :
- SNHF, fiche prunier, Claude Bureaux — https://www.snhf.org/fiche-plante/prunier/ —
  « Il craint la taille, génératrice de «gomme». » ; « uniquement en formes libres,
  dites de plein vent ». Aucune période.
- Au Jardin, tailler le prunier (21/12/2014) — https://www.aujardin.info/fiches/tailler-prunier.php —
  « officiellement entre novembre et mars » ; « il est toujours préférable de tailler
  en début d'automne » ; basses-tiges « plutôt en mars » ; « un élagage tous les
  trois ans ».
- Jardiner Malin, prunier (27/09/2026) — https://www.jardiner-malin.fr/fiche/prunier.html —
  « pas en hiver : intervenez plutôt au début du printemps ou juste après la
  récolte » (la page dit ailleurs « tout au long de l'hiver, mais hors période de
  gel »).

**Désaccords et arbitrage.**
- Été après récolte : C1, C2, C3, C4 (rang institutionnel et technique) concordent.
  Les dates 01/08 → 30/09 sont une **(transcription)** de « été après récolte /
  fin d'été », que C5 et C6 chiffrent en août–septembre.
- Contre : Au Jardin (tailler-cerisier, 20/12/2014, « l'époque officielle de taille
  se prolonge jusqu'en février »), Gerbeaud (quand-tailler, « fin octobre / début
  novembre » pour les noyaux), Jardiner Malin cerisier (23/09/2025, « automne-hiver »).
  Vulgarisation contre quatre sources de rang supérieur : **B3 tranche pour
  l'été** (convention 2). Le CTIFL déconseille explicitement l'automne : la borne
  30/09 est la limite défendable, rien au-delà.
- Le CTIFL taille dès décembre ou à partir du 15/02 **en verger professionnel**
  (« La taille des cerisiers », 2014,
  https://varietes_cerise.ctifl.fr/varietes_cerise/Media/Rubrique/55/Taille web.pdf,
  plaines du Sud-Est), en échelonnant selon la sensibilité variétale. Ce n'est pas
  transposable au jardin.
- « Reprise sévère étalée sur 3 ans » (annexe actuelle) : **aucune source ouverte**.
  À retirer. Au Jardin parle seulement d'une restauration « tous les 3 ou 4 ans ».
- **Butoir de plausibilité proposé (optionnel, `indicative`)** : butoir de fin au
  30/09, motif C2 (« Ne pas tailler pendant la période automnale »). Sans lui, le
  profil montagne (+21 j) projette la fenêtre jusqu'au 21/10.
- Périodicité 1 : lecture du calendrier (convention 4) ; la fréquence
  pluriannuelle d'Au Jardin concerne la restauration, pas l'entretien.

**Statut proposé.** Cerisier : **`verifiee`**. Prunier : **`indicative`** — les
sources institutionnelles qui donnent l'été (C3, C4) parlent des noyaux en
général ; la seule fiche institutionnelle propre au prunier (SNHF) ne donne
aucune période et déconseille presque la taille ; la vulgarisation spécifique
diverge (automne, mars, printemps). B-T1 : satisfait par les deux.

---

## Haie persistante — taille

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 16/08, fin: 30/09, optimal: [16/08, 30/09], butoirs: [16/08, 15/03], libelle: "taille de fin d'été"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| H1 | SNHF, fiche « Ifs » (23/09/2020) | institutionnel | https://www.snhf.org/fiche-plante/ifs/ | « La taille annuelle densifie les ramures et le feuillage. Elle est généralement pratiquée au cours de l'été (août). » |
| H2 | SNHF, fiche « Pyracantha » (03/11/2020) — même éditeur que H1 | institutionnel | https://www.snhf.org/fiche-plante/pyracantha/ | « Pour la forme haie, tailler en juin et fin août. » |
| H3 | Au Jardin, « Tailler le thuya » (02/12/2015) [WF] | vulgarisation | https://www.aujardin.info/fiches/tailler-thuya.php | « doit être taillé en début de printemps puis en août » |
| H4 | Gerbeaud, X. Gerbeaud, « Quand tailler les haies » (12/06/2025) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/fp_haie_taille.php3 | « En général, 2 tailles suffisent » ; « entre mai et juin, de fin août à début octobre. » |

Compléments du même éditeur que H4 [WF] : haies de persistants (10/05/2021),
https://www.gerbeaud.com/jardin/fiches/haie-persistant-tailler.php — « vers le mois
d'avril » puis « En août » ; thuyas et Leyland « 2 à 3 tailles par an » ;
calendrier des arbustes, https://www.gerbeaud.com/jardin/fiches/fp_arbustes_calendrier_taille.php3 —
troène, laurier-cerise, thuya en mai et septembre. Règles professionnelles UNEP
P.E.2-R0 (2013), https://documents.lesentreprisesdupaysage.fr/pub/documents/p-e-2-r0-regles-pro-entretien-des-arbustes-28-04-16.pdf —
seulement la réduction sévère « en fin d'hiver », pas l'entretien courant.

**Désaccords et arbitrage.**
- Les sources donnent **deux** tailles : printemps (avril, mai–juin, juin) et fin
  d'été (août, fin août–début octobre). La première tombe dans la période
  16/03 → 15/08 : elle est **exclue par les butoirs**, pas par les sources.
- **« Fin d'hiver ~mars » (annexe actuelle) : aucune source** pour l'entretien
  courant. Retiré.
- Fenêtre retenue : du butoir 16/08 au 30/09. « Août » (H1, H3), « fin août »
  (H2, H4). Fin au 30/09 : **(transcription)** de « septembre » (calendrier
  Gerbeaud) et en deçà du « début octobre » de H4.
- La LPO (jusqu'au 31/08) rognerait encore la fenêtre ; non retenue (voir butoirs).
- Indépendance : H1 et H2 = une seule source (SNHF), portant sur des espèces
  particulières. H3 et H4 concordent sur août. B2 satisfait (SNHF + Au Jardin, sans
  Gerbeaud). Si Gerbeaud était exclu : inchangé.

**Statut proposé : `verifiee`**, en **une** fenêtre au lieu de deux. Climat : non
précisé.

---

## Haie caduque — taille

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 01/11, fin: 15/03, optimal: [01/11, 15/03], butoirs: [16/08, 15/03], libelle: "repos végétatif"}]
periodicite_annees: 1        # à trancher : 1 (haie taillée de jardin, non sourcé) ou 2 à 3 (haie bocagère, sourcé)
confiance: indicative
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| K1 | Département des Côtes-d'Armor, « Entretien du bocage » (PDF, 2010) | institutionnel (Bretagne) | https://cotesdarmor.fr/sites/default/files/2021-04/entretien_bocage_0.pdf | « Intervenir en période de repos végétatif de fin octobre au 15 mars » |
| K2 | Préfecture/DDT de la Haute-Marne, plaquette « Haies » (PDF, 2019) | institutionnel | voir section butoirs | « La taille des haies se fait tous les 2 ou 3 ans. » ; réduction d'emprise « entre mi-novembre et fin mars » ; recépage « entre mi-novembre et mi-mars » |
| K3 | LPO Tarn (PDF, 2024) | institutionnel (association) | https://tarn.lpo.fr/wp-content/uploads/2024/02/Lpo-Taille-hivernale.pdf | « La bonne saison pour les arbres à feuilles caduques se situe en novembre, décembre et janvier. » ; « Il ne faut pas non plus attendre la fin de l'hiver pour tailler » |
| K4 | Collectivité européenne d'Alsace (PDF, 2011) | institutionnel | https://www.alsace.eu/media/6837/cea-plaquette-savoir-faire-haies.pdf | « Des tailles en vert (août-septembre) facilitent la cicatrisation » ; recépage « en période de repos (hiver) » |

Vulgarisation : Gerbeaud, calendrier [WF] — charme en février, haie champêtre en
mai et septembre ; Gerbeaud « Quand tailler les haies » — mai–juin puis fin
août–début octobre, caducs et persistants confondus. SNHF hêtre (25/05/2020),
https://www.snhf.org/fiche-plante/hetre-deurope-arbre-de-lumiere/, et Au Jardin
charme, https://www.aujardin.info/plantes/carpinus-betulus.php : aucune période.

**Désaccords et arbitrage.**
- Repos végétatif : K1 (fin octobre → 15/03) et K2 (mi-novembre → mi/fin mars)
  concordent ; K3 resserre à novembre–janvier et déconseille la fin d'hiver.
  Fenêtre 01/11 → 15/03 : début **(transcription)** entre « fin octobre » et
  « mi-novembre », fin fixée par K1 et par le butoir.
- **Ces sources traitent de haies bocagères ou champêtres**, pas d'une charmille
  taillée de jardin. Pour celle-ci, aucune source de rang supérieur ne donne de
  période ; la vulgarisation donne d'autres dates (février, ou deux tailles en
  végétation dont une en nidification).
- Périodicité : K2 dit 2 à 3 ans (bocage) ; le « 1×/an » de l'annexe actuelle
  n'est porté par aucune source. Les deux choix sont donnés à Léon ; la
  proposition est 1 an marqué `indicative`, parce que la cadence bocagère
  appliquée à une haie de jardin taillée serait un emploi de la source hors de son
  objet.

**Statut proposé : `indicative`.** Climat : Bretagne (K1), Haute-Marne (K2), Tarn
(K3).

---

## Arbuste à floraison printanière — taille (transposition R2)

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 01/04, fin: 31/07, optimal: [01/05, 30/06], libelle: "juste après la floraison, selon l'espèce"}]
periodicite_annees: 1
confiance: indicative
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| A1 | SNHF, « La taille "douce" des arbustes », Nicole Sallé (24/03/2017) | institutionnel | https://www.snhf.org/taille-douce-arbustes/ | « plantes fleurissant sur les bois d'1 an ou plus : tailler après la floraison. Exemples : […] Deutzia, […] Forsythia, […] spirées de printemps, Syringa, Weigelia » |
| A2 | FREDON France, fiche 5 « Mieux tailler les arbres et les arbustes » (PDF, date non visible) | technique | https://fredon.fr/sites/default/files/Guide%20d%C3%A9chets%20verts/Fiches%20particuliers%20d%C3%A9chets%20verts/Fiche_5_mieux_tailler_imprim.pdf | « Tailler juste après la floraison. Exemple : - Spirée à floraison printanière. » ; forsythia : « Tailler après la floraison. » |
| A3 | UNEP et al., règles professionnelles P.E.2-R0 (PDF, 2013) | technique | voir haie persistante | « la taille après floraison est à préférer à une taille hivernale, qui supprime la floraison de l'année » ; la saison « est un repère général et dont l'expression varie selon les régions » |
| A4 | Au Jardin, Iris Makoto (20/03/2023) [WF] et (05/07/2025) | vulgarisation | https://www.aujardin.info/fiches/taille-arbustes-floraison-printaniere.php ; https://www.aujardin.info/fiches/bien-tailler-pour-jardin-toujours-fleurs.php | « après leur floraison en fin de printemps ou en tout début d'été » ; « en fin de printemps, quand leurs fleurs se fanent » ; weigela, https://www.aujardin.info/plantes/weigelia.php : « Après la floraison (au mois de juillet) » |
| A5 | Gerbeaud, calendrier des arbustes (10/05/2021) [WF] | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/fp_arbustes_calendrier_taille.php3 | forsythia en avril ; lilas et spirée de printemps en mai–juin ; weigela en juin ; deutzia en juin–juillet |

**Désaccords et arbitrage.**
- Les sources de rang supérieur (A1, A2, A3) ne donnent **qu'un événement**,
  « après la floraison », jamais un mois. A3 rappelle que c'est le stade, pas la
  saison, qui compte : **R4 reste la bonne forme**.
- Les mois ne viennent que de la vulgarisation : A5 (avril → juillet selon
  l'espèce) et A4 (fin de printemps, début d'été, juillet pour le weigela).
  Fenêtre = étendue d'A5 ; optimale = recouvrement d'A4 et d'A5 (mai–juin),
  **(transcription)**.
- Conflit à signaler : la LPO (K3) déconseille de tailler arbres et arbustes du
  16/03 au 31/08. B-T2 ne s'applique qu'aux haies, mais un arbuste isolé taillé
  en mai est concerné par la même recommandation.
- B-T3 (pas de taille avant la floraison) : une fenêtre ouverte au 01/04 précède la
  floraison des lilas et deutzias. Une fenêtre unique pour tout le groupe est une
  approximation ; des règles par espèce (forsythia, lilas, weigela…) seraient plus
  justes, mais leurs mois ne reposent que sur A5.
- Si Gerbeaud était exclu : il ne resterait qu'A4, une source, statut inchangé
  (`indicative`), fenêtre réduite à mai → juillet.

**Statut proposé : `indicative`.** Climat : non précisé ; A3 rappelle la variation
régionale.

---

## Arbuste à floraison estivale — taille

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 01/02, fin: 31/03, optimal: [01/03, 31/03], libelle: "fin d'hiver, hors gel"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| E1 | SNHF, taille douce (= A1) | institutionnel | voir A1 | « estivale : prévoir une taille hivernale. » ; « plantes fleurissant sur les bois de l'année : tailler en fin d'hiver. Exemples : […] Caryopteris, […] Hibiscus, […] lavatère, […] Perovskia, […] rosiers remontants, spirées d'été » |
| E2 | FREDON, fiche 5 (= A2) | technique | voir A2 | « Tailler en hiver, hors période de gel (février, mars). Exemples : - Spirée à floraison estivale, - Pérovskia. » |
| E3 | UNEP 2013 (= A3) | technique | voir A3 | floraison sur les pousses de l'année : « La taille hivernale est possible et ne remet pas en cause la floraison à venir. » |
| E4 | Gerbeaud, « Tailler les arbustes à floraison d'été » (29/07/2022) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/taille-arbustes-floraison-ete,1525.html | « en fin d'hiver, de la fin février à la fin mars selon les régions, mais toujours en dehors des périodes de gel. » |
| E5 | Au Jardin, arbre à papillons (09/05/2016) | vulgarisation | https://www.aujardin.info/fiches/tailler-entretenir-arbre-papillons.php | « Elle se pratique en mars juste avant le débourrement. » |

**Désaccords et arbitrage.**
- Fenêtre février–mars : E2 la chiffre, E1 et E3 disent « fin d'hiver » ou
  « hivernale », E4 la confirme. Concordant.
- Optimale mars : E5 et la seconde moitié d'E4. Au Jardin, fiche buddleia 2012
  (https://www.aujardin.info/plantes/buddleia.php), élargit à « entre novembre et
  mars » : vulgarisation isolée, non retenue.
- Dépendance possible : Pascal Prieur signe des schémas de la SNHF et figure dans
  les figures de l'UNEP. Même ainsi, E2 reste indépendante.
- Périodicité 1 : lecture du calendrier (convention 4).

**Statut proposé : `verifiee`.** Climat : « selon les régions » (E4).

---

## Rosier — taille

**Valeurs proposées (rosier buisson remontant).**

```yaml
type_regle: R2
fenetres: [{debut: 01/02, fin: 31/03, optimal: [01/02, 31/03], libelle: "hors gel, selon la région"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| O1 | SNHF, « Taillons nos rosiers », Claude Jourdan, section Roses (17/05/2013, mod. 31/08/2017) | institutionnel | https://www.snhf.org/taillons-nos-rosiers/ | « pas seulement le rabattage à l'automne, mais la taille définitive au printemps (février, mars, suivant les régions). » ; grimpants non remontants : « dans le courant de juillet-août au plus tard » |
| O2 | SNHF, taille douce (= A1) — même éditeur que O1 | institutionnel | voir A1 | « rosiers remontants » classés parmi les plantes à « tailler en fin d'hiver » |
| O3 | Au Jardin, bases de la taille des rosiers (02/08/2011) et « Comment tailler les rosiers ? » (05/03/2017) | vulgarisation | https://www.aujardin.info/fiches/taille_rosiers.php ; https://www.aujardin.info/fiches/comment-tailler-rosiers.php | « Mars est la meilleure période pour tailler vos rosiers buissons » ; « Taillez les rosiers de février à avril hors période de gel. » ; automne : « une quarantaine de centimètres […] simplement d'une taille esthétique » |
| O4 | Gerbeaud, rosiers grimpants, X. Gerbeaud (16/01/2024) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/rosier-grimpant-taille.php | remontants : « de la mi-février à la mi-mars » ; non remontants : « juste après la floraison, soit vers la fin du mois de juillet. » |

Complément : SNHF, « Rosiers buissons », Daniel Lejeune (09/11/2020),
https://www.snhf.org/fiche-plante/rosiers-buissons/ — « une taille haute en
automne et enfin, appliquer au printemps, lorsque les gelées ne sont plus à
redouter (quand refleurit le lilas), une taille de simplification ».

**Désaccords et arbitrage.**
- Taille principale : février–mars (O1, O2), mars ou février–avril (O3), mi-février
  à mi-mars (O4). Fenêtre 01/02 → 31/03 portée par O1 et O3 (indépendantes).
- Repère phénologique : la SNHF dit « quand refleurit le lilas », plus tardif
  (avril). Le repère « forsythias » ne figure dans aucune source ouverte.
- **« Rosiers jusqu'en novembre » (annexe actuelle) : non confirmé.** Le rabattage
  ou la taille haute d'automne existe (O1, O3, Lejeune) mais aucune source ne lui
  donne de mois. Retiré ; une seconde fenêtre d'automne ne peut pas être écrite.
- Rosier grimpant non remontant (espèce distincte, optionnelle) : juillet–août
  (O1, O4) ; mais Au Jardin dit « en automne » (2011) puis « au printemps » (2017).
  Si ajouté : 01/07 → 31/08, `indicative` (O1 seule de rang supérieur).
- Optimale : seule O3 dit « mars » ; non retenue, optimale = fenêtre.

**Statut proposé : `verifiee`** pour le buisson remontant. Climat : « suivant les
régions » (O1).

---

## Pelouse — tonte

**Valeurs proposées.**

```yaml
type_regle: R1
intervalles:
  - {mois_debut: 1,  mois_fin: 2,  jours: 0}    # hors saison
  - {mois_debut: 3,  mois_fin: 5,  jours: 7}    # reprise mi-mars à avril selon les sources
  - {mois_debut: 6,  mois_fin: 8,  jours: 14}   # « été » (transcription : saison météorologique)
  - {mois_debut: 9,  mois_fin: 10, jours: 7}
  - {mois_debut: 11, mois_fin: 12, jours: 0}
confiance: indicative
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| G1 | SEMAE, « Petit guide du tapis vert » (PDF, 30/05/2023) | institutionnel (interprofession) | https://www.semae.fr/uploads/semae-fr_publication_202305_D1763_PETIT-GUIDE-DU-TAPIS-VERT.pdf | « Au printemps et en automne, périodes de pousse active, tondez 1 à 2 fois par semaine » ; « conservez toujours 2/3 de sa hauteur » |
| G2 | SNHF, fiches ray-grass, fétuque, agrostide, Michel Rocher (sans date) | institutionnel | https://www.snhf.org/fiche-plante/ray-grass/ | « Diminuez leur fréquence en été et augmentez-la au printemps et en automne » ; délais « de 5 à 30 jours » ; « Ne coupez que le tiers du brin d'herbe » |
| G3 | Au Jardin, FAQ pelouse (02/08/2011) et articles de Julien (02/12/2025, 12/11/2025) | vulgarisation | https://www.aujardin.info/fiches/pelouse-faq-1.php ; https://www.aujardin.info/actualites/tondre-hiver-bonne-mauvaise-idee-simple-reflexe-peut-sauver-ruiner-votre-pelouse-printemps.php ; https://www.aujardin.info/actualites/tondre-pelouse-automne-bons-gestes-pour-gazon-pleine-forme-printemps.php | « D'avril à octobre, il faut tondre en moyenne une fois par semaine » ; printemps pluvieux « tous les 4 à 5 jours » ; été chaud « tous les 10 à 15 jours » ; septembre « une fois par semaine » ; « Dernière tonte vers le 10 ou 15 du mois » (octobre) ; « on évite toute tonte en plein cœur de l'hiver, entre décembre et février » ; reprise « autour de la mi-mars » ; Nord ou altitude : dernière tonte « fin octobre » ; Sud : « fin novembre, voire début décembre » |
| G4 | Gerbeaud, C. Desfemmes (19/10/2026) et fiche gazon (02/03/2020) | vulgarisation (boutique) | https://www.gerbeaud.com/reponses-experts/a-quelle-frequence-tondre-sa-pelouse,75.html ; https://www.gerbeaud.com/jardin/fiches/gazon-bien-tondu-conseils,2308.html | « une fois par semaine au printemps, et une fois tous les 15 jours en été » ; « La dernière tonte de l'année s'effectue en octobre, avant les gelées. » ; saison « entre les mois de février-mars et octobre-novembre » |
| G5 | Jardiner Malin (12/05/2026) | vulgarisation (boutique) | https://www.jardiner-malin.fr/fiche/a-quelle-frequence-et-a-quelle-hauteur-faut-il-vraiment-tondre.html | « une fois par semaine pendant les mois de croissance rapide (généralement le printemps et l'automne) » ; « une fois toutes les deux semaines pendant les périodes de croissance plus lente (l'été et l'hiver) » |

**Désaccords et arbitrage.**
- Printemps 7 j : G1, G3, G4, G5 concordent (G1 et G3 descendent à 3,5–5 j par
  forte pousse). Retenu.
- Été 14 j : G3 (10–15), G4 (15), G5 (14) ; G2 donne seulement le sens (diminuer).
  Valeur chiffrée par la vulgarisation seule.
- Automne : **7–10 j (annexe actuelle) infirmé** : G1, G3, G5 disent une fois par
  semaine ou plus. Retenu 7. « Mi-septembre » : aucune source.
- Hiver : 0 de décembre à février (G3) et dernière tonte en octobre (G3, G4), mais
  G5 dit « toutes les deux semaines » en hiver. Désaccord entre vulgarisations.
- Bornes de saison : reprise « mi-mars » (G3 2025), « avril » (G3 2011),
  « février-mars » (G4). R1 se découpe au mois : mars à 7 j est une approximation
  du début de saison. Novembre à 0 : G3 (10–15 octobre en plaine), G4 (« en
  octobre »).
- Seule valeur institutionnelle chiffrée : 5 à 30 jours (G2), sans découpage.
- B-T5 : 7 et 14 dans [5, 21]. RG-25 : les bornes de mois sont décalées par le
  profil, les intervalles non.
- Si Gerbeaud et Jardiner Malin étaient exclus : l'été ne reposerait plus que sur
  G3.

**Statut proposé : `indicative`.** Intervalles saison par saison chiffrés par la
vulgarisation seule, hiver et bornes de saison en désaccord. Climat : G3 donne
Nord/altitude et Sud ; la plaine n'est pas nommée.

---

## Pêcher — taille de fructification

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 01/02, fin: 31/03, optimal: [01/03, 31/03], libelle: "fin d'hiver, au plus tard au début de la floraison"}]
periodicite_annees: 1
confiance: indicative
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| Q1 | SNHF, « Pêchers et nectariniers », Claude Bureaux (sans date) | institutionnel | https://www.snhf.org/fiche-plante/pechers-et-nectariniers/ | « Pour une bonne production, une taille fruitière s'effectue en mars. » ; complétée par « une taille en vert aussitôt après récolte » ; régions « tempérées chaudes » |
| Q2 | Jardins de France n° 629 (SNHF), D. Veschambre (PDF, mai-juin 2014) — même éditeur que Q1 | institutionnel | https://www.jardinsdefrance.org/wp-content/uploads/jdf-medias/images//HS_Nos_conseils_pratiques/JdF629_9_Nos-conseils-pratiques.pdf | « ne pas hésiter à tailler, tard en tout début de floraison, ce qui permet de bien visualiser les boutons floraux » |
| Q3 | Jardiner Malin, pêcher (14/08/2026) | vulgarisation (boutique) | https://www.jardiner-malin.fr/fiche/pecher.html | « Chaque année, il est important de tailler votre arbre à la fin de l'hiver, » « ou au tout début du printemps » |
| Q4 | Gerbeaud, taille de fructification (= P4) | vulgarisation (boutique) | voir P4 | « Pour pommier, poirier, pêcher ou abricotier, la première quinzaine de février est généralement bien adaptée. » |
| Q5 | Au Jardin, M.-H. Doyon (pépinières Ripaud), 07/01/2024 | vulgarisation, **auteur pépiniériste** | https://www.aujardin.info/fiches/pourquoi-essentiel-tailler-pecher.php | « La taille sera réalisée en janvier/février afin de retarder le départ de la végétation. » |

Taille en vert (intervention distincte) : Q1 « aussitôt après récolte » ; Jardiner
Malin, taille en vert, « Mi-juin à fin août ». Non proposée.

**Désaccords et arbitrage.**
- Mois : mars (Q1), début de floraison (Q2), fin d'hiver/début de printemps (Q3),
  première quinzaine de février (Q4), janvier–février (Q5). Une seule famille
  institutionnelle (SNHF) ; la vulgarisation diverge entre elle : **`indicative`**
  (convention 2).
- Fenêtre 01/02 → 31/03 couvre Q1, Q3, Q4 ; optimale mars (Q1, Q3) ;
  janvier (Q5) laissé hors fenêtre, auteur pépiniériste.
- Périodicité 1 : « Chaque année » (Q3) et calendrier de Q1.
- **Conflit avec B-T1** : le pêcher est un fruitier à noyau, et toute fenêtre
  sourcée recouvre février. Même la seule optimale de mars passe en février au
  profil −15 j. Voir « Conséquences », point 4.

**Statut proposé : `indicative`**, sous réserve de la décision sur B-T1 ; sans
cette décision, le pêcher doit rester **écarté de la liste de départ**. Climat :
Q1 parle de régions « tempérées chaudes ».

---

## Vigne — taille d'hiver

**Valeurs proposées.**

```yaml
type_regle: R2
fenetres: [{debut: 01/12, fin: 31/03, optimal: [01/02, 31/03], libelle: "repos végétatif, avant débourrement"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| V1 | Vignevin Charentes, guide pratique viticulture durable (PDF, 2024) | technique | https://vignevin-charentes.com/wp-content/uploads/2024/01/5-Conduite-de-la-vigne-preparation-des-vendanges-et-aleas-climatiques-part-2.pdf | « La taille peut s'effectuer durant toute la période de repos végétatif de la vigne, à savoir de la chute des feuilles jusqu'au débourrement. » ; « Préférer une taille tardive » pour « Retarder le débourrement et réduire le risque de gelée printanière » |
| V2 | Jardiner Malin, taille de la vigne (mise à jour 09/09/2026) | vulgarisation (boutique) | https://www.jardiner-malin.fr/fiche/taille-de-la-vigne.html | « Février et mars : la bonne période. » ; « Décembre et janvier : possible mais risqué. » ; « Avril : seulement si les bourgeons dorment encore. » ; zone continentale ou altitude : « mieux vaut attendre la mi-mars » ; treille : « chaque hiver » |
| V3 | Gerbeaud, I. Makoto, cep de vigne (14/06/2021) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/cep-de-vigne-entretien,2652.html | « En mars (ou avant le débourrement) » |
| V4 | Au Jardin, V. Macrelle, vigne (31/08/2026) | vulgarisation | https://www.aujardin.info/plantes/vigne.php | « On taille en fin d'hiver (pas trop tard sinon la vigne « pleure », pas trop tôt sinon les bourgeons gèlent). » |

Contexte : IFV Occitanie, A. Deloire et A. Pellegrino (18/02/2025),
https://www.vignevin-occitanie.com/fondamentaux/taille-tardive/ — endodormance
« en général de novembre à janvier », écodormance « en général de janvier à
mars » ; une taille pendant les pleurs « retarde le débourrement en moyenne de
6 jours ». SNHF, fiche vigne, https://www.snhf.org/fiche-plante/vigne/ — « La vigne
fructifie exclusivement sur les rameaux de l'année […] nés eux-mêmes sur le bois
de l'année précédente. » (fonde le caractère annuel, sans mois).

**Désaccords et arbitrage.**
- Fenêtre : V1 (chute des feuilles → débourrement) et V2 (décembre à mars, avril
  sous condition). 01/12 → 31/03 : **(transcription)** de V1 bornée par V2.
- Optimale février–mars : V2, V3, V4, et V1 (« taille tardive »). Concordant.
- Novembre–décembre : « idéal » pour la Cité des Climats et Vins de Bourgogne,
  « risqué » pour V2. La Cité est **exclue** (sans auteur ni date, B3).
- Viticulture (V1, IFV) : la taille tardive protège du gel ; vulgarisation : gare
  aux pleurs. Les pleurs ne nuisent pas (IFV : retard de 6 j).
- Périodicité 1 : V2 (« chaque hiver ») et la biologie décrite par la SNHF.
- Si Gerbeaud et Jardiner Malin étaient exclus : la fenêtre ne reposerait plus que
  sur V1 et V4 — statut `verifiee` maintenu pour l'optimale, début de fenêtre
  porté par V1 seule.

**Statut proposé : `verifiee`.** Climat : Charentes (V1), Occitanie (IFV) ; V2
décale à mi-mars en continental ou altitude, ce que les profils +7 et +21 font.

---

## Framboisier — taille

Deux espèces de bibliothèque proposées (B-T7).

**Valeurs proposées.**

```yaml
# framboisier non remontant
type_regle: R2
fenetres: [{debut: 01/02, fin: 31/03, optimal: [01/02, 31/03], libelle: "couper les cannes qui ont fructifié"}]
periodicite_annees: 1
confiance: verifiee
# framboisier remontant (conduit pour la récolte d'automne)
type_regle: R2
fenetres: [{debut: 01/11, fin: 28/02, optimal: [01/11, 28/02], libelle: "couper toutes les cannes au ras du sol"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| F1 | Chambre d'agriculture de la Corrèze, DEPHY, poster framboisiers non remontants, K. Barrière (2018) | technique | https://ecophytopic.fr/sites/default/files/2024-01/Poster%20Produire%20des%20fraboises%20NR.pdf | « Pour les variétés non remontantes, la taille s'effectue généralement en hiver. » ; « couper au ras du sol les cannes ayant fructifié pendant l'été » |
| F2 | Chambres d'agriculture, fiche « Le framboisier en agriculture biologique », C. Chevalier, Rhône (2013) | technique | https://opera-connaissances.chambres-agriculture.fr/doc_num.php?explnum_id=146758 | non remontantes : « en fin d'hiver (février/mars) couper toutes les cannes qui ont déjà produit » ; « il est parfois souhaitable de couper les cannes qui ont produit dès la fin de la récolte » ; remontantes : « la taille est simple en hiver : couper toutes les cannes au ras du sol. » |
| F3 | SNHF, fiche framboisier (sans date) | institutionnel | https://www.snhf.org/fiche-plante/framboisier/ | non remontants : « juste après la récolte » ; remontants : « après la récolte d'automne, coupez la partie supérieure des pousses qui ont fructifié » ; « après la cueillette d'été, supprimez les cannes qui ont fructifié. » (vérifié) |
| F4 | Bruxelles Environnement, info-fiche potager n° 7 (23/05/2012) | institutionnel | https://document.environnement.brussels/opac_css/elecfile/IF Potager 07 Arbustes petits fruits FR | variétés d'été : « Après la récolte, taillez les tiges […] au ras du sol. » ; variétés d'automne : « En novembre, coupez tout au ras du sol. » (étiquettes remontant/non remontant **inversées** par rapport à l'usage français) |
| F5 | Gerbeaud, X. Gerbeaud (03/06/2025) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/fp_framboisiers_taille.php3 | « Qu'ils soient remontants ou non, février s'avère le bon mois pour tailler » |
| F6 | Au Jardin, V. Macrelle (31/08/2025) et fiche 2013 | vulgarisation | https://www.aujardin.info/fiches/tailler-entretenir-framboisiers.php ; https://www.aujardin.info/fiches/taille-framboisiers.php | « possible d'octobre à mars » ; non remontants : « entre janvier et mars » ; remontants : « A partir des premières gelées et jusqu'en février » ; non remontants, alternative « Juste après la dernière récolte fin juillet/début août » |

**Désaccords et arbitrage.**
- Non remontant : deux écoles. Hiver (F1, F2, F5, F6 2025) contre après récolte
  (F3, F4, F5, F6 2013). Les deux chambres d'agriculture (premier rang B3)
  donnent l'hiver, et F2 admet l'autre pratique. Fenêtre février–mars : F2, F5,
  F6. **`verifiee`**. Alternative à mentionner dans l'explication : après récolte,
  août (SNHF, Bruxelles).
- **Annexe A / consigne implicite « couper après la récolte, l'été » : contredite
  par les chambres d'agriculture.**
- Remontant conduit pour une récolte : tout couper en hiver. F2 (hiver), F4
  (novembre), F6 (premières gelées → février), F5 (février). Fenêtre
  01/11 → 28/02 **(transcription)** de ces bornes. **`verifiee`**.
- Remontant conduit pour deux récoltes : seconde taille après la récolte d'été
  (F3 ; F6 « en septembre »). Seconde fenêtre possible (RG-36), `indicative`,
  non proposée au départ.
- Périodicité 1 : chaque canne ne fructifie qu'une fois ; lecture du calendrier
  (convention 4).

**Statut proposé : `verifiee`** pour les deux. Climat : Corrèze (F1), Rhône,
montagne et semi-méditerranéen pour le palissage (F2), Bruxelles (F4).

---

## Groseillier — taille

**Valeurs proposées (groseillier à grappes).**

```yaml
type_regle: R2
fenetres: [{debut: 01/01, fin: 15/03, optimal: [01/02, 28/02], libelle: "hors gel ; supprimer le bois de plus de 3–4 ans"}]
periodicite_annees: 1
confiance: verifiee
```

**Sources.**

| # | Source | Type | URL | Ce qui porte la valeur |
|---|---|---|---|---|
| R1s | Bruxelles Environnement (= F4) | institutionnel | voir F4 | « Taillez en février ou au début mars, en dehors des périodes de gel. » ; « Supprimez totalement les branches charpentières trop anciennes […] (plus de 4 ans) » |
| R2s | Au Jardin, groseillier à grappes (03/07/2011) | vulgarisation | https://www.aujardin.info/plantes/groseillier_a_grappes.php | « Dans le courant février, éliminez les branches de plus de trois ans. » ; « Coupez les plus vieux rameaux à ras du sol en mars » |
| R3s | Gerbeaud, X. Gerbeaud, arbustes à petits fruits (15/11/2023) | vulgarisation (boutique) | https://www.gerbeaud.com/jardin/fiches/fp_taille_cassis.php3 | « pendant l'hiver, en janvier ou février, si le temps est doux et qu'il ne gèle pas » ; « Les rameaux anciens (de 4 ans et plus) » |

**Désaccords et arbitrage.**
- Fenêtre : janvier (R3s) → début mars (R1s, R2s). 15/03 est une
  **(transcription)** de « début mars ».
- Optimale février : les trois sources la contiennent.
- Aucune source ne donne une taille après récolte.
- Âge du bois à supprimer : 3 ans (R2s) ou 4 ans (R1s, R3s) — descriptif, pas une
  échéance.
- B2 satisfait par R1s et R2s sans Gerbeaud.

**Statut proposé : `verifiee`.** Climat : Bruxelles (R1s).

**Cassissier (espèce distincte, hors liste demandée).** Chambres d'agriculture,
fiche cassis AB, Isère (2012), https://opera-connaissances.chambres-agriculture.fr/doc_num.php?explnum_id=145267 —
« Taille d'hiver » en décembre–janvier dans le calendrier (lecture de la mise en
page incertaine), vieux bois « 3 ans et plus » ; SNHF cassissier,
https://www.snhf.org/fiche-plante/cassissier/ — « renouvelée tous les ans par
quart » (périodicité 1, sans mois) ; Au Jardin (15/08/2015),
https://www.aujardin.info/fiches/planter-tailler-entretenir-cassissier.php —
« En novembre et en février, supprimez le vieux bois » ; Gerbeaud (R3s) —
janvier–février. Proposition : 01/11 → 28/02, 1 an, **`verifiee`**.

**Groseillier à maquereau : écarté de la liste de départ.** Une seule source
(Gerbeaud, R3s). Aucune autre trouvée.

---

## Sources exclues

| Source | URL | Motif |
|---|---|---|
| Pépins & Cie | https://pepinsetcie.fr/quand-tailler-un-arbre-fruitier-calendrier-pratique-et-erreurs-a-eviter/ | **Site marchand** (B3) : « Pépinière d'arbres fruitiers et producteur de fruits bio », « Les commandes sont ouvertes ! », vente en ligne, compte client, CGV (vérifié). Article de Rudy Magne, 16/01/2026, qui se contredit (« décembre à mars » puis « décembre à février ») |
| Salon du Jardin | https://salondujardin.fr/taille-arbres-fruitiers-2026/ | Pseudonyme « webzqsd », éditeur sans SIRET, statistiques sans lien, page de modèle en ligne, entrée hors sujet : contenu vraisemblablement généré (B3) |
| Guide Jardins & Paysages | https://www.guide-jardins-paysage.fr/2025/02/a-quelle-frequence-faut-il-tondre-sa-pelouse-selon-les-saisons/ | Auteur « admin3765 », pas de mentions légales, lien traqué vers un paysagiste, style généré (B3) |
| Cité des Climats et Vins de Bourgogne | https://citeclimatsvins-bourgogne.com/blog/taille-de-la-vigne/ | Sans auteur ni date (B3) |
| Jardiner Malin, taille du cerisier (A. Chéritel, 23/09/2025) | https://www.jardiner-malin.fr/fiche/taille-cerisier-pourquoi-quand-comment.html | Non exclue au sens de B3, mais écartée : contredit SNHF et CTIFL sans motif |
| Commentaire de lecteur Gerbeaud (2013) | https://www.gerbeaud.com/jardin/fiches/fp_haie_taille.php3 | Commentaire, pas contenu éditorial |
| Préfecture de la Haute-Marne, dates de nidification | voir butoirs | Dates périmées (PAC 2015), gardée seulement pour la haie caduque |

Les autres sources de l'annexe A actuelle (Gerbeaud et Au Jardin traitement
d'hiver, Jardiner Malin haies, rotations) n'ont pas été rouvertes : elles portent
sur des règles hors périmètre (R3, R5) ou sont remplacées ici.

---

## Pages qui n'ont pas pu s'ouvrir

Aucun contenu ne leur est attribué.

- OFB, deux communiqués sur la taille des haies (`ofb.gouv.fr/sites/default/files/…/CP_Taille-des-haies_24032023.pdf`,
  `…/CP_PDL_Haies_PDL.pdf`) : blocage anti-robot. **Aucun document de l'OFB n'a été
  lu directement** ; ses dates ne sont connues que par Que Choisir.
- Chambre d'agriculture des Pays de la Loire, préconisation OFB 2022 ; chambres de
  la Drôme et des Hauts-de-France (fiches haies) ; Ariège (framboisier) ;
  Tarn-et-Garonne (groseillier) : 404.
- Légifrance, version actuelle de L.411-1 : non lue (une URL tentée par
  supposition a rendu 404 ; elle n'est pas utilisée).
- Actu-Environnement (instruction du 17/07/2026) : réservé aux abonnés ; lue sur
  le Bulletin officiel.
- Rustica : domaine inaccessible à l'outil, aucune page consultée.
- revuevitiarbohorti.ch (deux PDF), jette.brussels, reussir.fr, domaine-chaumont.fr,
  espacepourlavie.ca : 404, 403 ou redirection.
- FiBL cassis (PDF chiffré), CRA-W fiches cerise et livret de taille de l'École du
  paysage (> 10 Mo), guide Plante & Cité terrains de sport (non téléchargeable).
- Société française des roses, roseraies du Val-de-Marne et de Bagatelle : aucune
  page de conseil trouvée.

## Ce qui n'a pas été trouvé

- Aucune fiche INRAE, CTIFL (hors cerisier), FREDON régionale ou Astredhor donnant
  un calendrier de taille des fruitiers à pépins, du pêcher, des petits fruits, ou
  une fréquence de tonte.
- Aucune source de rang supérieur pour la taille d'une **haie caduque de jardin**
  (charmille) ni pour une fréquence de tonte découpée par saison.
- Aucun mois sourcé pour le rabattage d'automne des rosiers.
- Aucun arrêté préfectoral 2027 (régime unique de la haie) pour l'Île-de-France ou
  les départements voisins.
- Aucune source sur la « reprise sévère étalée sur 3 ans » des noyaux.
