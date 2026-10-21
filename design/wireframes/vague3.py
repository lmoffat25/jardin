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
    nav = d.cadre("Navigation", f, 0, 0, NAV, 1400, fond=SURFACE)   # rognée par l'écran
    d.logo(nav, 24, 20)
    d.texte(nav, 24, 76, 200, "JARDIN", 12, gras=True, couleur=SECOND)
    choix = d.cadre("Jardin courant", nav, 24, 96, NAV - 48, 44, fond=FOND, rayon=8, trait=TRAIT)
    d.texte(choix, 12, 12, NAV - 88, "Le Clos", 16, gras=True)
    d.icone(choix, "chevron-down", NAV - 48 - 36, 10, libelle="Changer de jardin")
    # Trois groupes, séparés par un filet : le travail du jour ; le jardin ; les réglages et
    # ce qui sert une fois par saison (Léon, 20/10/2026).
    y = 168
    for g, groupe in enumerate([["À faire"], ["Plantes", "Zones", "Carte"],
                                ["Types d'intervention", "Étiquettes", "Export"]]):
        if g:
            d.cadre("Séparateur", nav, 24, y + 7, NAV - 48, 1, fond=TRAIT)
            y += 16
        for nom in groupe:
            _nav_lien(nav, nom, actif, y)
            y += 46


def _nav_lien(nav, nom, actif, y):
    sel = nom == actif
    b = d.cadre(f"Navigation · {nom}", nav, 16, y, NAV - 32, 40, fond=FOND if sel else None,
                rayon=8)
    d.texte(b, 12, 10, NAV - 56, nom, 16, gras=sel, couleur=TEXTE if sel else SECOND)
    LIENS_NAV.append((b, nom))


# Le mot d'une pastille est l'état qu'elle dit : il en choisit la couleur (passe interface).
ETATS = {"urgent": "en-retard", "urgente": "en-retard", "en retard": "en-retard",
         "manquée": "en-retard", "ouverte": "a-faire", "à faire": "a-faire", "soldée": "fait",
         "fait": "fait", "faite": "fait", "hors saison": "hors-saison", "non envoyé": "non-envoye"}


def pastille(parent, x, y, texte, fort=False):
    w = 14 + 8 * len(texte)
    nom = ETATS.get(texte.lower())
    if vague1.INTERFACE:
        d.instance("Pastille", parent, x, y, w, 26, {"etat": nom or "neutre",
                   "forte": "oui" if fort else "non", "libelle": texte}, calque=f"État · {texte}")
        return w
    with d.etat(nom) if nom else d.surcharge({}):
        p = d.cadre(f"État · {texte}", parent, x, y, w, 26, fond=FORT if fort else FOND, rayon=13,
                    trait=FORT)
        d.texte(p, 0, 4, w, texte, 13, gras=True, couleur=BLANC if fort else TEXTE, align="center")
    return w


def photo(parent, x, y, w, h, legende="Photo", plein_cadre=False):
    """`plein_cadre` : bord à bord, sans coin ni contour (la couverture de T5)."""
    if plein_cadre:
        c = d.cadre(f"Photo · {legende}", parent, x, y, w, h, fond=SURFACE)
    else:
        c = d.cadre(f"Photo · {legende}", parent, x, y, w, h, fond=SURFACE, rayon=6, trait=TRAIT)
    d.cadre("Diagonale", c, 0, h / 2, w, 1, fond=TRAIT)
    d.texte(c, 4, h / 2 - 22, w - 8, legende, 12, couleur=SECOND, align="center")
    return c


def radio(parent, x, y, choisi=False):
    """Bouton radio : rond, point plein quand il est choisi."""
    r = d.cadre("Choix unique", parent, x, y, 20, 20, fond=FOND, rayon=10, trait=FORT)
    if choisi:
        d.cadre("Point", r, 5, 5, 10, 10, fond=FORT, rayon=5)
    return r


def case(parent, x, y, cochee=False):
    if vague1.INTERFACE:
        return d.instance("Case", parent, x, y, proprietes={"etat": "cochee" if cochee else "vide"})
    c = d.cadre("Case", parent, x, y, 20, 20, fond=FORT if cochee else FOND, rayon=4, trait=FORT)
    if cochee:
        d.texte(c, 0, 1, 20, "✓", 14, gras=True, couleur=BLANC, align="center")
    return c


# --- B5 · Liste des plantes ----------------------------------------------------
PLANTES = [  # nom, code, espèce, zone, prochaine échéance (la plus proche), en retard ?
    ("Pommier 'Reine des reinettes'", "FRU-001", "Pommier", "Verger haut",
     "Traitement cuivre · avant le 15 nov.", False),
    ("Pommier 'Belle de Boskoop'", "FRU-014", "Pommier", "Verger haut",
     "Traitement cuivre · avant le 15 nov.", False),
    ("Prunier n°3", "FRU-021", "Prunier", "Verger haut",
     "⚠ Traitement cuivre · avant le 24 oct.", True),
    ("Cerisier 'Burlat'", "FRU-022", "Cerisier", "Verger bas", "Taille · à venir, août", False),
    ("Haie de charmilles — côté route", "HAI-001", "Charme (charmille)", "Haies",
     "Taille · à venir, nov.", False),
    ("Pelouse devant la maison", "PEL-001", "Pelouse", "Pelouse", "Tonte · ven. 24 oct.", False),
    ("Rosier 'Pierre de Ronsard'", "ARB-004", "Rosier", "Massif d'entrée",
     "Taille · à venir, février", False),
    ("Figuier", "ARB-001", "—", "Massif d'entrée", "Aucune règle", True),
]
# La colonne du tri porte sa flèche (audit n° 38) : par zone, puis par nom.
COLS_B5 = [("", 44), ("", 72), ("Nom", 300), ("Code", 96), ("Espèce", 176), ("Zone ↑", 150),
           ("Prochaine échéance", CW - 44 - 72 - 300 - 96 - 176 - 150)]


def liste_plantes(nom, cochees=0, recherche=None, vide=False, filtres=()):
    """`filtres` : ce qui est appliqué, en pastilles retirables, avec « Tout effacer »
    (audit n° 22)."""
    f = ecran(nom)
    coquille(f, "Plantes")
    d.texte(f, CX0, 40, 500, "Plantes", 32, gras=True)
    d.texte(f, CX0, 86, 600, "142 plantes actives · 6 zones", 16, couleur=SECOND)
    lot = d.bouton(f, CX0 + CW - 440, 40, 200, 48, "Ajouter en lot")
    nouvelle = d.bouton(f, CX0 + CW - 228, 40, 228, 48, "Nouvelle plante", principal=True)
    if vague1.INTERFACE:
        d.instance("Champ", f, CX0, 128, 360, 44, {"etat": "rempli" if recherche else "vide",
                   "valeur": recherche or "Nom ou code (FRU-014)"}, calque="Champ · recherche")
    else:
        champ_r = d.cadre("Champ · recherche", f, CX0, 128, 360, 44, fond=FOND, rayon=8,
                          trait=FORT if recherche else TRAIT)
        d.texte(champ_r, 14, 12, 330, recherche or "Nom ou code (FRU-014)", 15,
                couleur=TEXTE if recherche else TRAIT)
    x = CX0 + 376
    for filtre in ["Zone : toutes", "Catégorie : toutes", "Actives", "À faire : tout"]:
        w = 48 + 8 * len(filtre)
        b = d.cadre(f"Filtre · {filtre}", f, x, 128, w, 44, fond=FOND, rayon=8, trait=TRAIT)
        d.texte(b, 12, 12, w - 44, filtre, 14, gras=True)
        d.icone(b, "chevron-down", w - 32, 10)
        x += w + 8
    rangs = []
    if filtres:
        x = CX0
        for filtre in filtres:
            w = 52 + 8 * len(filtre)
            b = d.cadre(f"Filtre appliqué · {filtre}", f, x, 184, w, 40, fond=SURFACE, rayon=20,
                        trait=FORT)
            d.texte(b, 14, 10, w - 52, filtre, 14, gras=True)
            d.bouton_icone(b, w - 44, -4, 48, 48, "x", f"Retirer le filtre {filtre}")
            x += w + 8
        d.bouton(f, x + 8, 184, 140, 40, "Tout effacer")
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
                with d.etat("en-retard") if j == 6 and alerte else d.surcharge({}):
                    d.texte(c, 12, 20, w - 24, val, 15, gras=j == 2 or (j == 6 and alerte),
                            couleur=SECOND if j in (3, 4) else TEXTE)
        rangs.append(r)
    d.texte(f, CX0, 752, 600, "1 à 8 sur 142 · triées par zone, puis par nom",
            14, couleur=SECOND)
    d.bouton(f, CX0 + CW - 232, 744, 112, 40, "‹ Préc.", inactif=True)
    d.bouton(f, CX0 + CW - 112, 744, 112, 40, "Suiv. ›")
    if cochees:
        barre_selection(f, CX0, BH - 88, CW, f"{cochees} sélectionnées", 142,
                        [("Ajouter ce que j'ai fait", 230), ("Changer de zone", 176)])
    return f, rangs, nouvelle, lot


def barre_selection(f, x, y, w, compte, total, actions, autres=True):
    """Le compteur, tout / aucune (audit n° 10), les actions fréquentes, le reste sous « … ».
    Rend les boutons d'action par libellé."""
    barre = d.cadre("Actions groupées", f, x, y, w, 72, fond=FORT, rayon=12)
    d.texte(barre, 24, 25, 170, compte, 17, gras=True, couleur=BLANC)
    d.bouton(barre, 200, 12, 180, 48, f"Tout ({total})")
    d.bouton(barre, 388, 12, 150, 48, "Aucune")
    boutons, xb = {}, 562
    for lib, lw in actions:
        boutons[lib] = d.bouton(barre, xb, 12, lw, 48, lib)
        xb += lw + 10
    if autres:
        d.bouton_icone(barre, xb, 12, 48, 48, "ellipsis", "Autres actions")
    return boutons


b5, b5_rangs, b5_nouvelle, b5_lot = liste_plantes("B5 · 01 · Plantes")
b5s, b5s_rangs, _, _ = liste_plantes("B5 · 02 · Plantes — 3 sélectionnées", cochees=3)
b5v, _, _, _ = liste_plantes("B5 · 03 · Plantes — aucun résultat", recherche="Néflier", vide=True,
                             filtres=["Recherche : Néflier", "Actives"])

# --- B6 · Fiche plante (bureau) -----------------------------------------------
GAUCHE, DROITE = 680, CW - 680 - 32
XD = CX0 + GAUCHE + 32


def carte_echeance(parent, x, y, w, titre, etat, fort, quand, explication, actions,
                   principal=False):
    """Un seul bouton plein par écran : `principal` le donne à « Fait » de la carte la plus
    urgente ; le geste rare passe sous « … » (audit n° 23)."""
    c = d.cadre(f"Échéance · {titre}", parent, x, y, w, 172, fond=FOND, rayon=10, trait=TRAIT)
    d.texte(c, 20, 18, 420, titre, 18, gras=True)
    pastille(c, w - 20 - (14 + 8 * len(etat)), 16, etat, fort)
    d.texte(c, 20, 46, w - 40, quand, 15)
    d.texte(c, 20, 70, w - 40, explication, 14, couleur=SECOND)
    boutons = {}
    x_b = 20
    for i, (lib, lw) in enumerate(actions):
        boutons[lib] = d.bouton(c, x_b, 118, lw, 40, lib, principal=principal and i == 0)
        x_b += lw + 8
    boutons["…"] = d.bouton_icone(c, x_b, 114, 48, 48, "ellipsis", f"Autres actions · {titre}")
    return c, boutons


b6 = ecran("B6 · 01 · Fiche plante")
coquille(b6, "Plantes")
d.texte(b6, CX0, 24, 600, "Plantes  ›  Verger haut", 14, couleur=SECOND)
photo(b6, CX0, 56, 200, 150, "Photo de couverture")
d.texte(b6, CX0 + 224, 60, 760, "Pommier 'Reine des reinettes'", 30, gras=True)
d.texte(b6, CX0 + 224, 104, 760, "FRU-001 · Pommier · Verger haut · 3e du rang, côté route · "
        "planté vers 2019", 15, couleur=SECOND)
b6_saisir = d.bouton(b6, CX0 + 224, 152, 240, 48, "Ajouter ce que j'ai fait")   # Q-UX-26
d.bouton(b6, CX0 + 224 + 252, 152, 120, 48, "Modifier")
d.bouton_icone(b6, CX0 + 224 + 384, 152, 48, 48, "ellipsis", "Autres actions (étiquette, archiver)")

d.texte(b6, CX0, 236, 300, "À faire", 20, gras=True)
_, b6_btn = carte_echeance(
    b6, CX0, 270, GAUCHE, "Taille d'entretien", "à faire", False,
    "Avant le 7 avril 2027 · moment idéal du 8 février au 7 avril",
    "Règle de la bibliothèque : chaque hiver, fenêtre déc.–mars, décalée de 7 jours pour ton "
    "climat (Frais).",
    [("Fait", 88), ("Reporter", 116), ("Modifier la règle", 176)], principal=True)
carte_echeance(
    b6, CX0, 454, GAUCHE, "Ramassage des fruits tombés", "à venir", False,
    "Prochaine : 1er août 2027",
    "Règle que tu as définie : tous les 7 jours, en août et septembre.",
    [("Fait", 88), ("Reporter", 116), ("Modifier la règle", 176)])

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
                              ("Ramassage · tous les 7 jours, août–sept.", "définie par toi")]):
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
                          ("espece", "Espèce (facultatif)", "Cherche dans la bibliothèque"),
                          ("zone", "Zone", "Choisis une zone  ↓"),
                          ("repere", "Repère (facultatif)", "3e du rang, derrière le cabanon…")]:
        champs[cle] = champ(f, CX0, y, FG, lib, valeurs.get(cle), ind,
                            erreur=erreur_nom if cle == "nom" else None)
        y += 104 if not (cle == "nom" and erreur_nom) else 132
    d.texte(f, CX0, y, FG, "Photo de couverture (facultatif)", 15, gras=True)
    zone_p = d.cadre("Dépôt · photo", f, CX0, y + 26, FG, 96, fond=SURFACE, rayon=8, trait=TRAIT,
                     pointille=True)
    d.texte(zone_p, 0, 36, FG, "Glisse une photo ici, ou choisis un fichier", 15,
            couleur=SECOND, align="center")
    d.texte(f, CX0, y + 140, FG, "Le code court (FRU-031) est attribué à l'enregistrement.", 14,
            couleur=SECOND)

    pan = d.cadre("Règles proposées", f, XR, 120, FD, 560, fond=SURFACE, rayon=12)
    d.texte(pan, 24, 20, FD - 48, "Ce que l'outil en fera", 18, gras=True)
    if panneau == "vide":
        d.texte(pan, 24, 60, FD - 48, "Choisis une espèce : la bibliothèque proposera ses "
                "règles d'entretien, déjà calées sur ton climat.\n\nSans espèce, la plante est "
                "créée sans règle : tu en ajouteras une si tu veux qu'elle te soit "
                "rappelée.", 15, couleur=SECOND)
    elif panneau == "pommier":
        d.texte(pan, 24, 56, FD - 48, "La bibliothèque propose 1 règle pour le pommier :", 15,
                couleur=SECOND)
        r = d.cadre("Règle · taille", pan, 24, 92, FD - 48, 150, fond=FOND, rayon=10, trait=TRAIT)
        case(r, 16, 18, True)
        d.texte(r, 48, 16, FD - 120, "Taille d'entretien · chaque hiver", 16, gras=True)
        d.texte(r, 48, 42, FD - 120, "Fenêtre du 1er déc. au 31 mars, moment idéal du 1er févr. "
                "au 31 mars. Décalée de 7 jours pour ton climat.", 14, couleur=SECOND)
        d.bouton(r, 48, 94, 120, 40, "Ajuster")
        d.texte(r, 180, 104, 300, "Règle indicative", 13, couleur=SECOND)
        d.bouton(pan, 24, 258, 220, 44, "+ Ajouter une règle")
        res = d.cadre("Résultat", pan, 24, 470, FD - 48, 66, fond=FOND, rayon=10)
        d.texte(res, 16, 12, FD - 80, "Première échéance : taille, avant le 7 avril 2027.", 15,
                gras=True)
        d.texte(res, 16, 36, FD - 80, "Elle apparaîtra dans « À faire » dès le 8 décembre.", 13,
                couleur=SECOND)
    elif panneau == "framboisier":   # espèce à variantes de conduite (RG-51, Q-UX-14)
        d.texte(pan, 24, 56, FD - 48, "Comment ton framboisier donne-t-il ?", 15, gras=True)
        for k, (titre, aide, choisie) in enumerate([
                ("Non remontant", "Une récolte, en été", False),
                ("Remontant", "Une récolte en été, une autre à l'automne", True)]):
            v = d.cadre(f"Variante · {titre}", pan, 24 + k * ((FD - 48) // 2 + 8), 86,
                        (FD - 48) // 2 - 8, 76, fond=FOND, rayon=10,
                        trait=FORT if choisie else TRAIT)
            radio(v, 14, 16, choisie)   # un seul choix possible (audit n° 40)
            d.texte(v, 44, 14, (FD - 48) // 2 - 64, titre, 16, gras=True)
            d.texte(v, 44, 40, (FD - 48) // 2 - 64, aide, 13, couleur=SECOND)
        d.texte(pan, 24, 182, FD - 48, "La bibliothèque propose 1 règle pour le framboisier "
                "remontant :", 15, couleur=SECOND)
        r = d.cadre("Règle · taille", pan, 24, 214, FD - 48, 150, fond=FOND, rayon=10, trait=TRAIT)
        case(r, 16, 18, True)
        d.texte(r, 48, 16, FD - 120, "Taille · chaque année", 16, gras=True)
        d.texte(r, 48, 42, FD - 120, "Fenêtre du 1er nov. au 28 févr. Décalée de 7 jours pour "
                "ton climat.", 14, couleur=SECOND)
        d.bouton(r, 48, 94, 120, 40, "Ajuster")
        res = d.cadre("Résultat", pan, 24, 470, FD - 48, 66, fond=FOND, rayon=10)
        d.texte(res, 16, 12, FD - 80, "Première échéance : taille, avant le 7 mars 2027.", 15,
                gras=True)
        d.texte(res, 16, 36, FD - 80, "Elle apparaîtra dans « À faire » dès le 8 novembre.", 13,
                couleur=SECOND)
    else:  # espèce absente de la bibliothèque
        d.texte(pan, 24, 56, FD - 48, "« Néflier » n'est pas dans la bibliothèque. La plante "
                "sera créée sans règle : rien ne te sera rappelé tant que tu n'en ajoutes pas "
                "une.", 15, gras=True)
        d.texte(pan, 24, 140, FD - 48, "Ajouter une règle", 15, gras=True)
        for k, (lib, val) in enumerate([("Geste", "Taille  ↓"), ("Rythme", "Chaque année  ↓"),
                                        ("Du", "1er déc."), ("Au", "28 févr.")]):
            xk, yk = 24 + (k % 2) * ((FD - 48) // 2 + 8), 172 + (k // 2) * 80
            champ(pan, xk, yk, (FD - 48) // 2 - 8, lib, val)
        d.texte(pan, 24, 340, FD - 48, "Tu pourras la corriger à l'usage : chaque correction est "
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
                 "Change le nom ou la zone.")
creer("B7 · 05 · Nouvelle plante — espèce à variantes",
      {"nom": "Framboisiers du potager", "espece": "Framboisier", "zone": "Potager"},
      "framboisier")

# --- T5 · Fiche plante (terrain) ----------------------------------------------
d.cmd("page.select", id=ids_pages["3 · Wireframes terrain"])
W = vague1.W
H_COUV = 300   # la couverture monte jusqu'en haut, sous la barre d'état (retour 3)
HISTORIQUE = [("sept. 2026", "Ramassage · 4 fois"), ("avril 2026", "⚠ Taille manquée"),
              ("12 févr. 2025", "Taille · 2 photos")]


def fiche_terrain(nom, couverture=True, enregistre=False):
    """T5 : la couverture pleine largeur (toujours là : vide, elle propose d'en ajouter une),
    l'échéance due, l'historique, et « Je viens de faire… » en bas. Rend un dict des boutons."""
    f = vague1.ecran(nom, barre=False)
    b = {}
    if couverture:
        photo(f, 0, 0, W, H_COUV, "Photo de couverture — pour reconnaître l'arbre", plein_cadre=True)
    else:   # l'encart reste, vide : un seul appui pour poser la couverture (retour 2)
        vide = d.cadre("Couverture · vide", f, 0, 0, W, H_COUV, fond=SURFACE)
        b["couverture"] = d.bouton(vide, (W - 330) // 2, 150, 330, 56,
                                   "+ Ajouter une photo de couverture")
    vague1.barre_etat(f)
    b["retour"] = vague1.retour_rond(f)
    d.texte(f, M, H_COUV + 16, W - 2 * M, "Pommier 'Reine des reinettes'", 24, gras=True)
    d.texte(f, M, H_COUV + 52, W - 2 * M, "FRU-001 · Verger haut · 3e du rang, côté route", 15,
            couleur=SECOND)
    y = H_COUV + 100
    historique = HISTORIQUE
    if enregistre:
        b["annuler"], b["photo"] = vague1.bandeau_enregistre(f, M, y, W - 2 * M,
                                                             "Taille d'entretien · aujourd'hui")
        historique = [("aujourd'hui", "Taille d'entretien")] + HISTORIQUE[:2]
    else:
        a_faire = d.cadre("Échéance · taille", f, M, y, W - 2 * M, 136, fond=FOND, rayon=10,
                          trait=TRAIT)
        d.texte(a_faire, 16, 14, 230, "Taille d'entretien", 17, gras=True)
        d.texte(a_faire, 16, 40, 230, "Avant le 7 avril · idéal du 8 févr. au 7 avril", 14,
                couleur=SECOND)
        b["fait"] = d.bouton(a_faire, W - 2 * M - 104, 14, 88, 48, "Fait")
        d.texte(a_faire, 16, 84, W - 2 * M - 32, "Bibliothèque · décalée de 7 jours pour ton climat",
                13, couleur=SECOND)
    y += 152
    d.texte(f, M, y, W - 2 * M, "HISTORIQUE", 13, gras=True, couleur=SECOND)   # comme B6 (retour 4)
    for k, (q, quoi) in enumerate(historique):
        d.texte(f, M, y + 28 + k * 30, 120, q, 14, couleur=SECOND)
        d.texte(f, M + 120, y + 28 + k * 30, W - 2 * M - 120, quoi, 15, gras="⚠" in quoi)
    b["autre"] = d.bouton(f, M, 732, W - 2 * M, 60, "Je viens de faire…", principal=True)
    return f, b


t5, t5b = fiche_terrain("T5 · 01 · Fiche plante")
t5_vide, t5v = fiche_terrain("T5 · 02 · Fiche plante — sans photo", couverture=False)

# « Je viens de faire… » depuis la fiche : la plante est déjà choisie (retour 1). Le type dû
# en tête, puis les récents ; toucher un type enregistre. 2 gestes.
t5_type = vague1.ecran("T5 · 03 · Je viens de faire — plante choisie")
t5_type_retour = d.bouton_icone(t5_type, 8, 52, 48, 48, "chevron-left", "Retour")
d.texte(t5_type, 64, 60, W - 64 - M, "Quel type ?", 24, gras=True)
d.texte(t5_type, 64, 94, W - 64 - M, "Pommier 'Reine des reinettes'", 15, couleur=SECOND)
t5_types = vague1.liste_types(t5_type, 124, [("Dû maintenant", ["Taille d'entretien"]),
                                             ("Récents", vague1.RECENTS[1:5])], ajouter=False)
t5_tous = d.bouton(t5_type, M, 640, W - 2 * M, 48, "Tous les types ›")
t5_autres = d.bouton(t5_type, M, 700, W - 2 * M, 48, "+ d'autres plantes")

t5_ok, t5o = fiche_terrain("T5 · 04 · Fiche plante — enregistré", enregistre=True)

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
for fb in (t5b, t5v, t5o):
    d.retour(fb["retour"])
    d.lien(fb["autre"], t5_type)
d.selon_reseau(t5b["fait"], t5_ok, vague1.t4)   # un geste, sans confirmation (Q-UX-24)
d.selon_reseau(t5v["fait"], t5_ok, vague1.t4)
d.lien(t5v["couverture"], vague1.photo_inter)
d.retour(t5_type_retour)
for r in t5_types:
    d.selon_reseau(r, t5_ok, vague1.t4)
d.lien(t5_tous, vague1.t2)
d.lien(t5_autres, vague1.t3b)   # la plante de la fiche déjà cochée
d.lien(t5o["annuler"], t5, "dissolve")
d.lien(t5o["photo"], vague1.photo_inter)

d.noeud("text", x=-680, y=1160, text=(
    "VAGUE 3 — LA PLANTE   [à revoir]\n\n"
    "Coquille du bureau : la navigation à gauche, le jardin courant en tête, toujours visible "
    "(RG-43).\n\n"
    "B5 · Liste : jamais de liste brute (§8.7) — recherche par nom ou code, filtres, pages de 25. "
    "Cocher des lignes fait apparaître les actions groupées en bas.\n\n"
    "B6 · Fiche : « À faire » d'abord, chaque échéance avec son explication en une phrase "
    "(RG-06) et ses actions (§4.4) : « Fait », « Reporter », « Modifier la règle », et « … » pour "
    "« Sauter cette fois ». Un seul bouton plein par écran. L'historique montre les manquées "
    "(RG-07). Les photos dans l'ordre du temps (§8.3).\n\n"
    "B7 · Créer : 2 champs obligatoires (nom, zone). Le panneau de droite montre ce que l'outil "
    "fera de la plante avant de la créer : les règles proposées et la première échéance. Une "
    "espèce absente n'empêche rien.\n\n"
    "T5 · Fiche terrain : la couverture en pleine largeur, jusqu'en haut, pour reconnaître "
    "l'arbre (RG-35) ; sans photo, l'encart reste et propose d'en ajouter une. On y arrive en "
    "touchant une ligne de « Aujourd'hui ». « Je viens de faire… » y garde la plante : on choisit "
    "le type, et c'est enregistré (2 gestes) ; « + d'autres plantes » rouvre le choix. La photo "
    "de l'intervention se propose après, dans le bandeau « Enregistré » (RG-38).\n\n"
    "Les fenêtres affichées viennent des exemples du CDC, pas de la bibliothèque. Données "
    "fictives."), name="Notes · vague 3", props={
        "width": 560, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 16,
        "fills": [TEXTE]})
