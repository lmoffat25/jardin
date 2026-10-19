"""Wireframes de la vague 3 : la plante, sur ses deux contextes (B5, B6, B7, T5).

La liste des plantes, la fiche au bureau, la création d'une plante (P4) et la
fiche sur le terrain (P6). C'est ici que naît la coquille du bureau : la
navigation et le jardin courant toujours visible (RG-43). Données fictives ; les
fenêtres affichées sont celles des exemples du CDC (§3.9), pas des valeurs de
la bibliothèque, qui se sourcent à part.
"""
from __future__ import annotations

import vague1
from vague1 import BLANC, ECRANS, FOND, FORT, M, SECOND, SURFACE, TEXTE, TRAIT, d, ids_pages
from vague2 import BH, BW, b4, champ, ecran

NAV = 248
CX0, CW = NAV + 48, BW - NAV - 96          # zone de contenu
LIENS_NAV: list[tuple[int, str]] = []       # (bouton, destination) à relier en fin de vague


def coquille(f, actif: str):
    """Navigation du bureau. Le jardin courant est en tête, toujours (RG-43)."""
    nav = d.cadre("Navigation", f, 0, 0, NAV, BH, fond=SURFACE)
    logo = d.cadre("Marque/Logo (provisoire)", nav, 24, 20, 112, 32, rayon=6, trait=TRAIT,
                   pointille=True)
    d.texte(logo, 0, 7, 112, "Logo", 13, couleur=SECOND, align="center")
    d.texte(nav, 24, 76, 200, "JARDIN", 12, gras=True, couleur=SECOND)
    choix = d.cadre("Jardin courant", nav, 24, 96, NAV - 48, 44, fond=FOND, rayon=8, trait=TRAIT)
    d.texte(choix, 12, 12, NAV - 72, "Le Clos  ↓", 16, gras=True)
    for i, nom in enumerate(["À faire", "Plantes", "Zones", "Carte", "Étiquettes",
                             "Types d'intervention", "Export"]):
        sel = nom == actif
        b = d.cadre(f"Navigation · {nom}", nav, 16, 168 + i * 46, NAV - 32, 40,
                    fond=FOND if sel else None, rayon=8)
        d.texte(b, 12, 10, NAV - 56, nom, 16, gras=sel, couleur=TEXTE if sel else SECOND)
        LIENS_NAV.append((b, nom))


def pastille(parent, x, y, texte, fort=False):
    w = 14 + 8 * len(texte)
    p = d.cadre(f"État · {texte}", parent, x, y, w, 26, fond=FORT if fort else FOND, rayon=13,
                trait=FORT)
    d.texte(p, 0, 4, w, texte, 13, gras=True, couleur=BLANC if fort else TEXTE, align="center")
    return w


def photo(parent, x, y, w, h, legende="Photo"):
    c = d.cadre(f"Photo · {legende}", parent, x, y, w, h, fond=SURFACE, rayon=6, trait=TRAIT)
    d.cadre("Diagonale", c, 0, h / 2, w, 1, fond=TRAIT)
    d.texte(c, 4, h / 2 - 22, w - 8, legende, 12, couleur=SECOND, align="center")
    return c


def case(parent, x, y, cochee=False):
    c = d.cadre("Case", parent, x, y, 20, 20, fond=FORT if cochee else FOND, rayon=4, trait=FORT)
    if cochee:
        d.texte(c, 0, 1, 20, "✓", 14, gras=True, couleur=BLANC, align="center")
    return c


# --- B5 · Liste des plantes ----------------------------------------------------
PLANTES = [  # nom, code, espèce, zone, prochaine échéance, en retard ?
    ("Pommier 'Reine des reinettes'", "FRU-001", "Pommier", "Verger haut",
     "Taille · avant le 7 avril", False),
    ("Pommier 'Belle de Boskoop'", "FRU-014", "Pommier", "Verger haut",
     "Taille · avant le 7 avril", False),
    ("Prunier n°3", "FRU-021", "Prunier", "Verger haut",
     "⚠ Taille manquée en sept. · suivante en août", True),
    ("Cerisier 'Burlat'", "FRU-022", "Cerisier", "Verger bas", "Taille · à venir, août", False),
    ("Haie de charmilles — côté route", "HAI-001", "Charme (charmille)", "Haies",
     "Taille · avant le 31 mars", False),
    ("Pelouse devant la maison", "PEL-001", "Pelouse", "Pelouse", "Tonte · ven. 24 oct.", False),
    ("Rosier 'Pierre de Ronsard'", "ARB-004", "Rosier", "Massif d'entrée",
     "Taille · à venir, février", False),
    ("Figuier", "ARB-001", "—", "Massif d'entrée", "Aucune règle", True),
]
COLS_B5 = [("", 44), ("", 72), ("Nom", 300), ("Code", 96), ("Espèce", 176), ("Zone", 150),
           ("Prochaine échéance", CW - 44 - 72 - 300 - 96 - 176 - 150)]


def liste_plantes(nom, cochees=0, recherche=None, vide=False):
    f = ecran(nom)
    coquille(f, "Plantes")
    d.texte(f, CX0, 40, 500, "Plantes", 32, gras=True)
    d.texte(f, CX0, 86, 600, "142 plantes actives · 6 zones", 16, couleur=SECOND)
    lot = d.bouton(f, CX0 + CW - 440, 40, 200, 48, "Ajouter en lot")
    nouvelle = d.bouton(f, CX0 + CW - 228, 40, 228, 48, "Nouvelle plante", principal=True)
    champ_r = d.cadre("Champ · recherche", f, CX0, 128, 360, 44, fond=FOND, rayon=8,
                      trait=FORT if recherche else TRAIT)
    d.texte(champ_r, 14, 12, 330, recherche or "Nom ou code (FRU-014)", 15,
            couleur=TEXTE if recherche else TRAIT)
    x = CX0 + 376
    for filtre in ["Zone : toutes", "Catégorie : toutes", "Actives", "À faire : tout"]:
        w = 48 + 8 * len(filtre)
        b = d.cadre(f"Filtre · {filtre}", f, x, 128, w, 44, fond=FOND, rayon=8, trait=TRAIT)
        d.texte(b, 12, 12, w - 16, f"{filtre}  ↓", 14, gras=True)
        x += w + 8
    rangs = []
    if vide:
        d.texte(f, CX0, 260, CW, f"Aucune plante active ne correspond à « {recherche} ».", 22,
                gras=True, align="center")
        d.texte(f, CX0, 300, CW, "Elle est peut-être archivée, ou pas encore saisie.", 16,
                couleur=SECOND, align="center")
        d.bouton(f, CX0 + CW / 2 - 290, 352, 280, 48, "Chercher dans les archivées")
        d.bouton(f, CX0 + CW / 2 + 10, 352, 280, 48, "Créer « Néflier »", principal=True)
        return f, rangs, nouvelle, lot
    t = d.noeud("frame", name="Tableau · plantes", parent=f, x=CX0, y=196, width=CW, props={
        "fills": [FOND], "layout": "vertical", "sizingV": "hug", "stroke": TRAIT,
        "strokeWeight": 1, "strokeAlign": "inside", "cornerRadius": 8, "clip": True})
    tete = d.noeud("frame", name="Ligne · titres", parent=t, props={
        "fills": [SURFACE], "layout": "horizontal", "sizingH": "fill", "sizingV": "hug",
        "crossAlign": "center"})
    for lib, w in COLS_B5:
        c = d.noeud("frame", name=lib or "—", parent=tete, width=w, props={
            "fills": [], "layout": "vertical", "padding": [12, 12, 12, 12], "sizingH": "fixed",
            "sizingV": "hug"})
        vague1.texte_flux(c, lib or " ", 13, gras=True, couleur=SECOND)
    for i, (nom_p, code, esp, zone, ech, alerte) in enumerate(PLANTES):
        r = d.noeud("frame", name=f"Plante · {nom_p}", parent=t, props={
            "fills": [SURFACE] if i < cochees else [], "layout": "horizontal", "sizingH": "fill",
            "sizingV": "hug", "crossAlign": "center", "stroke": SURFACE, "strokeBottomWeight": 1})
        cellules = [None, None, nom_p, code, esp, zone, ech]
        for j, ((lib, w), val) in enumerate(zip(COLS_B5, cellules)):
            c = d.noeud("frame", name=lib or "—", parent=r, width=w, height=60, props={
                "fills": [], "sizingH": "fixed", "clip": False})
            if j == 0:
                case(c, 12, 20, i < cochees)
            elif j == 1:
                photo(c, 8, 6, 56, 48, "")
            else:
                d.texte(c, 12, 20, w - 24, val, 15, gras=j == 2 or (j == 6 and alerte),
                        couleur=SECOND if j in (3, 4) else TEXTE)
        rangs.append(r)
    d.texte(f, CX0, 752, 600, "1 à 8 sur 142 · triées par zone, puis par nom",
            14, couleur=SECOND)
    d.bouton(f, CX0 + CW - 232, 744, 112, 40, "‹ Préc.", inactif=True)
    d.bouton(f, CX0 + CW - 112, 744, 112, 40, "Suiv. ›")
    if cochees:
        barre = d.cadre("Actions groupées", f, CX0, BH - 88, CW, 72, fond=FORT, rayon=12)
        d.texte(barre, 24, 25, 260, f"{cochees} plantes sélectionnées", 17, gras=True, couleur=BLANC)
        x = 296
        for lib, w in [("Enregistrer une intervention", 268), ("Changer de zone", 176),
                       ("Ajouter une règle", 184), ("Archiver", 112)]:
            d.bouton(barre, x, 12, w, 48, lib)
            x += w + 10
    return f, rangs, nouvelle, lot


b5, b5_rangs, b5_nouvelle, b5_lot = liste_plantes("B5 · 01 · Plantes")
b5s, b5s_rangs, _, _ = liste_plantes("B5 · 02 · Plantes — 3 sélectionnées", cochees=3)
b5v, _, _, _ = liste_plantes("B5 · 03 · Plantes — aucun résultat", recherche="Néflier", vide=True)

# --- B6 · Fiche plante (bureau) -----------------------------------------------
GAUCHE, DROITE = 680, CW - 680 - 32
XD = CX0 + GAUCHE + 32


def carte_echeance(parent, x, y, w, titre, etat, fort, quand, explication, actions):
    c = d.cadre(f"Échéance · {titre}", parent, x, y, w, 172, fond=FOND, rayon=10, trait=TRAIT)
    d.texte(c, 20, 18, 420, titre, 18, gras=True)
    pastille(c, w - 20 - (14 + 8 * len(etat)), 16, etat, fort)
    d.texte(c, 20, 46, w - 40, quand, 15)
    d.texte(c, 20, 70, w - 40, explication, 14, couleur=SECOND)
    boutons = {}
    x_b = 20
    for i, (lib, lw) in enumerate(actions):
        boutons[lib] = d.bouton(c, x_b, 118, lw, 40, lib, principal=i == 0)
        x_b += lw + 8
    return c, boutons


b6 = ecran("B6 · 01 · Fiche plante")
coquille(b6, "Plantes")
d.texte(b6, CX0, 24, 600, "Plantes  ›  Verger haut", 14, couleur=SECOND)
photo(b6, CX0, 56, 200, 150, "Photo de couverture")
d.texte(b6, CX0 + 224, 60, 760, "Pommier 'Reine des reinettes'", 30, gras=True)
d.texte(b6, CX0 + 224, 104, 760, "FRU-001 · Pommier · Verger haut · 3e du rang, côté route · "
        "planté vers 2019", 15, couleur=SECOND)
b6_saisir = d.bouton(b6, CX0 + 224, 152, 280, 48, "Enregistrer une intervention", principal=True)
for k, (lib, w) in enumerate([("Modifier", 120), ("Étiquette", 120), ("Archiver", 120)]):
    d.bouton(b6, CX0 + 224 + 292 + k * 128, 152, w, 48, lib)

d.texte(b6, CX0, 236, 300, "À faire", 20, gras=True)
_, b6_btn = carte_echeance(
    b6, CX0, 270, GAUCHE, "Taille d'entretien", "ouverte", False,
    "Avant le 7 avril 2027 · moment idéal du 8 février au 22 mars",
    "Règle de la bibliothèque : chaque hiver, fenêtre déc.–mars, décalée de 7 jours pour votre "
    "climat (Nord, Est, océanique frais).",
    [("Fait", 88), ("Reporter", 116), ("Sauter cette fois", 172), ("Modifier la règle", 176)])
carte_echeance(
    b6, CX0, 454, GAUCHE, "Ramassage des fruits tombés", "à venir", False,
    "Prochaine : 1er août 2027",
    "Règle que vous avez définie : tous les 7 jours, en août et septembre.",
    [("Fait", 88), ("Reporter", 116), ("Sauter cette fois", 172), ("Modifier la règle", 176)])

d.texte(b6, CX0, 644, 300, "Historique", 20, gras=True)
frise = d.cadre("Frise", b6, CX0, 676, GAUCHE, 70, fond=FOND)
d.cadre("Axe", frise, 0, 34, GAUCHE, 2, fond=TRAIT)
for k, an in enumerate(["2024", "2025", "2026", "2027"]):
    d.cadre("Repère", frise, k * 210, 28, 2, 14, fond=SECOND)
    d.texte(frise, k * 210 + 6, 48, 60, an, 12, couleur=SECOND)
for x_p, plein in [(22, True), (244, True), (494, False)]:   # tailles faites, taille manquée
    d.cadre("Taille" if plein else "Taille manquée", frise, x_p, 26, 18, 18,
            fond=FORT if plein else FOND, rayon=9, trait=FORT)
for x_p in [600, 614, 628, 642]:
    d.cadre("Ramassage", frise, x_p, 30, 10, 10, fond=SECOND, rayon=5)
d.texte(frise, 0, 0, GAUCHE, "●  fait      ○  manqué      •  ramassage", 12, couleur=SECOND)
HISTO = [("sept. 2026", "Ramassage des fruits tombés · 4 fois", False),
         ("7 avril 2026", "⚠ Taille d'entretien manquée — la fenêtre s'est fermée sans "
          "intervention", True),
         ("12 févr. 2025", "Taille d'entretien · 40 min · « trois branches mortes retirées » · "
          "2 photos", False)]
for k, (quand, quoi, fort) in enumerate(HISTO):
    d.texte(b6, CX0, 758 + k * 40, 120, quand, 14, couleur=SECOND)
    d.texte(b6, CX0 + 128, 758 + k * 40, GAUCHE - 128, quoi, 15, gras=fort)

d.texte(b6, XD, 236, DROITE, "Photos", 20, gras=True)
for k, quand in enumerate(["12/02/2025 avant", "12/02/2025 après", "03/02/2024",
                           "mai 2023", "", ""][:4]):
    photo(b6, XD + (k % 2) * (DROITE // 2 + 4), 270 + (k // 2) * 124, DROITE // 2 - 4, 116, quand)
d.texte(b6, XD, 530, DROITE, "Voir les 9 photos, de la plus ancienne à la plus récente", 14,
        couleur=SECOND)
d.texte(b6, XD, 580, DROITE, "Règles", 20, gras=True)
for k, (r, src) in enumerate([("Taille d'entretien · chaque hiver", "bibliothèque"),
                              ("Ramassage · tous les 7 jours, août–sept.", "définie par vous")]):
    d.texte(b6, XD, 616 + k * 48, DROITE, r, 15, gras=True)
    d.texte(b6, XD, 638 + k * 48, DROITE, src, 13, couleur=SECOND)
d.bouton(b6, XD, 718, 220, 44, "Voir et modifier")
d.texte(b6, XD, 784, DROITE, "Notes", 20, gras=True)
d.texte(b6, XD, 816, DROITE, "Greffé sur porte-greffe vigoureux. Pucerons au printemps 2025.", 14,
        couleur=SECOND)

# --- B7 · Créer une plante (P4) -----------------------------------------------
FG, FD = 520, CW - 520 - 48
XR = CX0 + FG + 48


def creer(nom, valeurs: dict, panneau: str, erreur_nom=None):
    f = ecran(nom)
    coquille(f, "Plantes")
    d.texte(f, CX0, 24, 600, "Plantes  ›  Nouvelle plante", 14, couleur=SECOND)
    d.texte(f, CX0, 52, 600, "Nouvelle plante", 32, gras=True)
    champs = {}
    y = 120
    for cle, lib, ind in [("nom", "Nom", "Par exemple : Pommier du fond"),
                          ("espece", "Espèce (facultatif)", "Cherchez dans la bibliothèque"),
                          ("zone", "Zone", "Choisissez une zone  ↓"),
                          ("repere", "Repère (facultatif)", "3e du rang, derrière le cabanon…")]:
        champs[cle] = champ(f, CX0, y, FG, lib, valeurs.get(cle), ind,
                            erreur=erreur_nom if cle == "nom" else None)
        y += 104 if not (cle == "nom" and erreur_nom) else 132
    d.texte(f, CX0, y, FG, "Photo de couverture (facultatif)", 15, gras=True)
    zone_p = d.cadre("Dépôt · photo", f, CX0, y + 26, FG, 96, fond=SURFACE, rayon=8, trait=TRAIT,
                     pointille=True)
    d.texte(zone_p, 0, 36, FG, "Glissez une photo ici, ou choisissez un fichier", 15,
            couleur=SECOND, align="center")
    d.texte(f, CX0, y + 140, FG, "Le code court (FRU-015) est attribué à l'enregistrement.", 14,
            couleur=SECOND)

    pan = d.cadre("Règles proposées", f, XR, 120, FD, 560, fond=SURFACE, rayon=12)
    d.texte(pan, 24, 20, FD - 48, "Ce que l'outil en fera", 18, gras=True)
    if panneau == "vide":
        d.texte(pan, 24, 60, FD - 48, "Choisissez une espèce : la bibliothèque proposera ses "
                "règles d'entretien, déjà calées sur votre climat.\n\nSans espèce, la plante est "
                "créée sans règle : vous en ajouterez une si vous voulez qu'elle vous soit "
                "rappelée.", 15, couleur=SECOND)
    elif panneau == "pommier":
        d.texte(pan, 24, 56, FD - 48, "La bibliothèque propose 1 règle pour le pommier :", 15,
                couleur=SECOND)
        r = d.cadre("Règle · taille", pan, 24, 92, FD - 48, 150, fond=FOND, rayon=10, trait=TRAIT)
        case(r, 16, 18, True)
        d.texte(r, 48, 16, FD - 120, "Taille d'entretien · chaque hiver", 16, gras=True)
        d.texte(r, 48, 42, FD - 120, "Fenêtre du 1er déc. au 31 mars, moment idéal du 1er févr. "
                "au 15 mars. Décalée de 7 jours pour votre climat.", 14, couleur=SECOND)
        d.bouton(r, 48, 94, 120, 40, "Ajuster")
        d.texte(r, 180, 104, 300, "Règle indicative", 13, couleur=SECOND)
        d.bouton(pan, 24, 258, 220, 44, "+ Ajouter une règle")
        res = d.cadre("Résultat", pan, 24, 470, FD - 48, 66, fond=FOND, rayon=10)
        d.texte(res, 16, 12, FD - 80, "Première échéance : taille, avant le 7 avril 2027.", 15,
                gras=True)
        d.texte(res, 16, 36, FD - 80, "Elle apparaîtra dans « À faire » dès le 8 décembre.", 13,
                couleur=SECOND)
    else:  # espèce absente de la bibliothèque
        d.texte(pan, 24, 56, FD - 48, "« Néflier » n'est pas dans la bibliothèque. La plante "
                "sera créée sans règle : rien ne vous sera rappelé tant que vous n'en ajoutez pas "
                "une.", 15, gras=True)
        d.texte(pan, 24, 140, FD - 48, "Ajouter une règle", 15, gras=True)
        for k, (lib, val) in enumerate([("Geste", "Taille  ↓"), ("Rythme", "Chaque année  ↓"),
                                        ("Du", "1er déc."), ("Au", "28 févr.")]):
            xk, yk = 24 + (k % 2) * ((FD - 48) // 2 + 8), 172 + (k // 2) * 80
            champ(pan, xk, yk, (FD - 48) // 2 - 8, lib, val)
        d.texte(pan, 24, 340, FD - 48, "Vous pourrez la corriger à l'usage : chaque correction est "
                "datée.", 13, couleur=SECOND)
    creer_ = d.bouton(f, XR + FD - 220, BH - 112, 220, 56, "Créer la plante", principal=True)
    d.bouton(f, XR + FD - 220 - 312, BH - 112, 300, 56, "Créer et en ajouter une autre")
    return f, champs, creer_


b7a, b7a_champs, _ = creer("B7 · 01 · Nouvelle plante", {}, "vide")
b7b, _, b7b_creer = creer("B7 · 02 · Nouvelle plante — espèce choisie",
                          {"nom": "Pommier 'Calville blanc'", "espece": "Pommier",
                           "zone": "Verger haut"}, "pommier")
creer("B7 · 03 · Nouvelle plante — espèce absente",
      {"nom": "Néflier", "espece": "Néflier", "zone": "Verger bas"}, "absente")
creer("B7 · 04 · Nouvelle plante — nom déjà pris",
      {"nom": "Pommier 'Belle de Boskoop'", "espece": "Pommier", "zone": "Verger haut"}, "pommier",
      erreur_nom="Un « Pommier 'Belle de Boskoop' » existe déjà dans Verger haut (FRU-014). "
                 "Changez le nom ou la zone.")

# --- T5 · Fiche plante (terrain) ----------------------------------------------
d.cmd("page.select", id=ids_pages["3 · Wireframes terrain"])
W = vague1.W
t5 = vague1.ecran("T5 · 01 · Fiche plante")
t5_retour = d.bouton(t5, 8, 52, 48, 48, "‹")
photo(t5, 0, 108, W, 220, "Photo de couverture — pour reconnaître l'arbre")
d.texte(t5, M, 340, W - 2 * M, "Pommier 'Reine des reinettes'", 24, gras=True)
d.texte(t5, M, 378, W - 2 * M, "FRU-001 · Verger haut · 3e du rang, côté route", 15, couleur=SECOND)
a_faire = d.cadre("Échéance · taille", t5, M, 440, W - 2 * M, 132, fond=FOND, rayon=10, trait=TRAIT)
d.texte(a_faire, 16, 14, 230, "Taille d'entretien", 17, gras=True)
d.texte(a_faire, 16, 40, 230, "Avant le 7 avril · idéal du 8 févr. au 22 mars", 14, couleur=SECOND)
t5_fait = d.bouton(a_faire, W - 2 * M - 104, 14, 88, 48, "Fait")
d.texte(a_faire, 16, 88, W - 2 * M - 32, "Bibliothèque · décalée de 7 jours pour votre climat",
        13, couleur=SECOND)
d.texte(t5, M, 592, W - 2 * M, "DERNIÈRES FOIS", 13, gras=True, couleur=SECOND)
for k, (q, s) in enumerate([("sept. 2026", "Ramassage · 4 fois"),
                            ("avril 2026", "⚠ Taille manquée"),
                            ("12 févr. 2025", "Taille · 2 photos")]):
    d.texte(t5, M, 620 + k * 30, 120, q, 14, couleur=SECOND)
    d.texte(t5, M + 120, 620 + k * 30, W - 2 * M - 120, s, 15, gras=k == 1)
t5_photo = d.bouton(t5, M, 732, 128, 60, "+ Photo")
t5_autre = d.bouton(t5, M + 140, 732, W - 2 * M - 140, 60, "Je viens de faire…", principal=True)

# --- Prototype -----------------------------------------------------------------
d.cmd("page.select", id=ids_pages["4 · Wireframes bureau"])
for r in b5_rangs:
    d.lien(r, b6, "dissolve")
for r in b5s_rangs:
    d.lien(r, b6, "dissolve")
d.lien(b5_nouvelle, b7a, "dissolve")
d.lien(b5_lot, vague1.ECRANS["B3 · 03 · Les plantes — en cours"], "dissolve")
d.lien(b7a_champs["espece"], b7b, "instant")
d.lien(b7b_creer, b6, "dissolve")
for bouton, nom in LIENS_NAV:
    if nom == "Plantes":
        d.lien(bouton, b5, "instant")
    elif nom == "À faire":
        d.lien(bouton, b4, "instant")
d.cmd("proto.flow.add", start=b5, name="Vague 3 — la plante (P4, P6)")

for r in vague1.RANGS_ECHEANCE:
    d.lien(r, t5)
d.retour(t5_retour)
d.cmd("proto.interaction.add", id=t5_fait, trigger={"type": "onClick"}, action={
    "type": "openOverlay", "dest": vague1.conf, "position": "bottomCenter",
    "close_on_outside": True, "background": "#00000066"},
    transition={"type": "moveIn", "direction": "top"}, durationMs=200)
d.lien(t5_autre, vague1.t2)

d.noeud("text", x=-680, y=1160, text=(
    "VAGUE 3 — LA PLANTE   [à revoir]\n\n"
    "Coquille du bureau : la navigation à gauche, le jardin courant en tête, toujours visible "
    "(RG-43).\n\n"
    "B5 · Liste : jamais de liste brute (§8.7) — recherche par nom ou code, filtres, pages de 25. "
    "Cocher des lignes fait apparaître les actions groupées en bas.\n\n"
    "B6 · Fiche : « À faire » d'abord, chaque échéance avec son explication en une phrase "
    "(RG-06) et ses quatre actions (§4.4). L'historique montre les manquées (RG-07). Les photos "
    "dans l'ordre du temps (§8.3).\n\n"
    "B7 · Créer : 2 champs obligatoires (nom, zone). Le panneau de droite montre ce que l'outil "
    "fera de la plante avant de la créer : les règles proposées et la première échéance. Une "
    "espèce absente n'empêche rien.\n\n"
    "T5 · Fiche terrain : la photo en grand, pour reconnaître l'arbre (RG-35). On y arrive en "
    "touchant une ligne de « Aujourd'hui ».\n\n"
    "Les fenêtres affichées viennent des exemples du CDC, pas de la bibliothèque. Données "
    "fictives."), name="Notes · vague 3", props={
        "width": 560, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 16,
        "fills": [TEXTE]})
