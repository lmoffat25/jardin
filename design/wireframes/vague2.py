"""Wireframes de la vague 2 (bureau : B2 et B3, parcours P0) et leur prototype.

Mise en route d'un jardin : le nom et le climat (B2), puis l'inventaire rapide,
les zones d'abord, les plantes ensuite (B3). C'est le parcours où un
utilisateur abandonne (CDC §7, P0, RG-27) : chaque écran doit pouvoir être
quitté sans rien perdre. Données fictives uniquement.
"""
from __future__ import annotations

from vague1 import (BLANC, ECRANS, FOND, FORT, SECOND, SURFACE, TEXTE, TRAIT, d, ids_pages,
                    texte_flux)

BW, BH = 1440, 900
COL = 760                       # colonne des formulaires, centrée
CX = (BW - COL) // 2
X = [0]

d.cmd("page.select", id=ids_pages["4 · Wireframes bureau"])


def ecran(nom: str, hauteur: int = BH) -> int:
    with d.surcharge({("fill", FOND): "fond/page"}):
        f = d.cadre(nom, None, X[0], 120, BW, hauteur, fond=FOND)
    d.densite(f, "Bureau")
    X[0] += BW + 120
    ECRANS[nom] = f
    return f


ETAPES = ["Le jardin", "Les zones", "Les plantes"]


def entete(f, etape: int, jardin: str | None = None):
    """Bandeau de mise en route : marque provisoire, étapes, jardin courant (RG-43)."""
    b = d.cadre("En-tête · mise en route", f, 0, 0, BW, 64, fond=FOND)
    d.cadre("Séparateur", b, 0, 63, BW, 1, fond=SURFACE)
    d.logo(b, 32, 16)
    x = 520
    for i, nom in enumerate(ETAPES):
        marque = "✓" if i < etape else str(i + 1)
        courant = i == etape
        d.texte(b, x, 21, 150, f"{marque}  {nom}", 15, gras=courant,
                couleur=TEXTE if courant or i < etape else TRAIT)
        if i < len(ETAPES) - 1:
            d.cadre("Liaison", b, x + 118, 31, 24, 1, fond=TRAIT)
        x += 150
    if jardin:
        d.texte(b, BW - 332, 21, 300, f"Jardin : {jardin}", 15, gras=True, align="right")


def champ(parent, x, y, w, libelle, valeur=None, indication=None, erreur=None, h=48):
    d.texte(parent, x, y, w, libelle, 15, gras=True)
    with d.etat("erreur", fond=False):
        c = d.cadre(f"Champ · {libelle}", parent, x, y + 26, w, h, fond=FOND, rayon=8,
                    trait=FORT if erreur else TRAIT)
    d.texte(c, 14, (h - 20) / 2, w - 28, valeur or indication or "", 16,
            couleur=TEXTE if valeur else TRAIT)
    if erreur:
        with d.etat("erreur"):
            d.texte(parent, x, y + 26 + h + 8, w, f"⚠ {erreur}", 14, gras=True)
    return c


# --- B2 · Le jardin et son climat ---------------------------------------------
PROFILS = [  # libellé, ce que ça change, où (CDC §5.4)
    ("Midi, littoral méditerranéen", "15 jours plus tôt", "Zones 9 à 10"),
    ("Sud-Ouest, vallée du Rhône", "7 jours plus tôt", "Zones 8 à 9"),
    ("Plaine tempérée", "dates de référence", "Bassin parisien, centre"),
    ("Nord, Est, océanique frais", "7 jours plus tard", "Zones 7 à 8"),
    ("Montagne, plus de 600 m", "21 jours plus tard", "Zones 5 à 7"),
]


def b2(nom, choisi: int | None, valeur_nom=None, erreur=False):
    f = ecran(nom)
    entete(f, 0)
    d.texte(f, CX, 112, COL, "Votre jardin", 32, gras=True)
    d.texte(f, CX, 160, COL, "Deux questions, puis on passe à ce qui pousse.", 17, couleur=SECOND)
    champ(f, CX, 216, COL, "Son nom", valeur_nom, "Par exemple : La maison, Chez mes parents")
    d.texte(f, CX, 322, COL, "Son climat", 15, gras=True)
    d.texte(f, CX, 346, COL, "Il cale les dates d'entretien : une même taille se fait environ trois "
            "semaines plus tôt dans le Midi qu'en montagne.", 15, couleur=SECOND)
    cartes = []
    for i, (lib, decal, ou) in enumerate(PROFILS):
        sel = i == choisi
        c = d.cadre(f"Climat · {lib}", f, CX, 396 + i * 62, COL, 54, fond=SURFACE if sel else FOND,
                    rayon=8, trait=FORT if sel or erreur else TRAIT)
        rond = d.cadre("Choix", c, 16, 15, 24, 24, fond=FORT if sel else FOND, rayon=12, trait=FORT)
        if sel:
            d.cadre("Point", rond, 8, 8, 8, 8, fond=BLANC, rayon=4)
        d.texte(c, 56, 16, 330, lib, 16, gras=True)
        d.texte(c, 390, 17, 190, ou, 14, couleur=SECOND)
        d.texte(c, 576, 16, 170, decal, 15, gras=sel, align="right")
        cartes.append(c)
    y = 396 + 5 * 62 + 4
    if erreur:
        with d.etat("erreur"):
            d.texte(f, CX, y, COL, "⚠ Choisissez un climat : sans lui, les dates proposées "
                    "seraient fausses dès le premier jour.", 15, gras=True)
    else:
        d.texte(f, CX, y, COL, "Vous pourrez le changer. Commune, altitude et surface se "
                "renseignent plus tard, si besoin.", 14, couleur=SECOND)
    suite = d.bouton(f, CX + COL - 220, BH - 112, 220, 56, "Continuer", principal=True)
    return f, cartes, suite


b2a, b2a_cartes, b2a_suite = b2("B2 · 01 · Votre jardin", None)
b2b, b2b_cartes, b2b_suite = b2("B2 · 02 · Votre jardin — rempli", 3, valeur_nom="Le Clos")
b2c, b2c_cartes, _ = b2("B2 · 03 · Votre jardin — climat manquant", None, valeur_nom="Le Clos",
                        erreur=True)

# --- B3 · Les zones -----------------------------------------------------------
ZONES = ["Verger haut", "Verger bas", "Haies", "Pelouse", "Massif d'entrée"]

b3z = ecran("B3 · 01 · Les zones")
entete(b3z, 1, "Le Clos")
d.texte(b3z, CX, 112, COL, "Les coins de votre terrain", 32, gras=True)
d.texte(b3z, CX, 160, COL, "Une zone groupe le travail : « ce matin, je fais le verger ». Cinq à dix "
        "suffisent. Leur ordre est celui de votre tour du jardin.", 17, couleur=SECOND)
for i, z in enumerate(ZONES):
    r = d.cadre(f"Zone · {z}", b3z, CX, 236 + i * 58, COL, 50, fond=FOND, rayon=8, trait=TRAIT)
    for k in range(3):  # poignée de glisser
        d.cadre("Poignée", r, 18, 18 + k * 6, 16, 2, fond=TRAIT)
    d.texte(r, 52, 13, 420, z, 17)
    # Monter/descendre : l'équivalent au glisser, exigé par WCAG 2.5.7.
    for j, s in enumerate(["↑", "↓"]):
        d.bouton(r, COL - 194 + j * 42, 7, 36, 36, s)
    d.bouton(r, COL - 104, 7, 96, 36, "Retirer")
nouvelle = d.cadre("Zone · nouvelle", b3z, CX, 236 + 5 * 58, COL, 50, fond=FOND, rayon=8,
                   trait=FORT)
d.texte(nouvelle, 52, 13, 500, "Ajouter une zone, puis Entrée", 17, couleur=TRAIT)
d.texte(b3z, CX, 236 + 6 * 58 + 8, 90, "Idées :", 14, couleur=SECOND)
for s, x, w in [("Potager", 0, 130), ("Bord de la maison", 140, 220), ("Bas du terrain", 370, 190)]:
    d.bouton(b3z, CX + 70 + x, 236 + 6 * 58, w, 40, f"+ {s}")
b3z_suite = d.bouton(b3z, CX + COL - 260, BH - 112, 260, 56, "Continuer : les plantes",
                     principal=True)

# --- B3 · Les plantes ---------------------------------------------------------
TX, TW = 80, BW - 160
COLONNES = [("Nb", 64), ("Nom", 300), ("Espèce", 260), ("Zone", 170), ("Repère (facultatif)", 210),
            ("Ce que l'outil en fait", TW - 64 - 300 - 260 - 170 - 210)]

LIGNES = [  # nb, nom, espèce, zone, repère, résultat, gras ?
    ("1", "Pommier 'Reine des reinettes'", "Pommier", "Verger haut", "3e du rang, côté route",
     "FRU-001 · taille, à faire avant le 31 mars", False),
    ("12", "Pommier", "Pommier", "Verger bas", "",
     "12 plantes : Pommier 1 à Pommier 12 · FRU-002 à FRU-013", True),
    ("1", "Haie de charmilles — côté route", "Charme (charmille)", "Haies", "",
     "HAI-001 · taille, fenêtre du 1er nov. au 15 mars", False),
    ("1", "Pelouse devant la maison", "Pelouse", "Pelouse", "",
     "PEL-001 · tonte, tous les 7 à 10 jours en saison", False),
    ("1", "Figuier", "Figuier — absent de la bibliothèque", "Massif d'entrée", "près du mur",
     "ARB-001 · aucune règle : rien ne sera proposé tant qu'il n'en a pas", True),
]


def tableau(f, y, lignes, saisie=None):
    t = d.noeud("frame", name="Tableau · plantes", parent=f, x=TX, y=y, width=TW, props={
        "fills": [FOND], "layout": "vertical", "sizingV": "hug", "stroke": TRAIT,
        "strokeWeight": 1, "strokeAlign": "inside", "cornerRadius": 8, "clip": True})
    rangs = [[(lib, True) for lib, _ in COLONNES]] + [
        [(c, False) for c in ligne[:6]] for ligne in lignes]
    for i, cellules in enumerate(rangs):
        entete_ = i == 0
        r = d.noeud("frame", name="Ligne" if not entete_ else "Ligne · titres", parent=t, props={
            "fills": [SURFACE] if entete_ else [], "layout": "horizontal", "sizingH": "fill",
            "sizingV": "hug", "stroke": SURFACE, "strokeBottomWeight": 1})
        for j, ((texte_, _), (_, w)) in enumerate(zip(cellules, COLONNES)):
            c = d.noeud("frame", name=COLONNES[j][0], parent=r, width=w, props={
                "fills": [], "layout": "vertical", "padding": [12, 12, 12, 12],
                "sizingH": "fixed", "sizingV": "hug"})
            gras = entete_ or j == 1 or (j == 5 and lignes[i - 1][6])
            couleur = SECOND if entete_ or j in (4, 5) and not gras else TEXTE
            texte_flux(c, texte_ or "—", 13 if entete_ else 15, gras=gras,
                       couleur=TRAIT if not texte_ else couleur)
    if saisie is not None:  # la ligne en cours de saisie, avec la bibliothèque ouverte
        r = d.noeud("frame", name="Ligne · en saisie", parent=t, props={
            "fills": [FOND], "layout": "horizontal", "sizingH": "fill", "sizingV": "hug",
            "padding": [6, 0, 6, 0]})
        for j, (lib, w) in enumerate(COLONNES):
            c = d.noeud("frame", name=lib, parent=r, width=w, props={
                "fills": [], "layout": "vertical", "padding": [4, 6, 4, 6], "sizingH": "fixed",
                "sizingV": "hug"})
            if j == len(COLONNES) - 1:
                texte_flux(c, "s'affiche dès que la ligne est complète", 14, couleur=TRAIT)
                continue
            boite_ = d.noeud("frame", name="Champ", parent=c, props={
                "fills": [FOND], "layout": "vertical", "padding": [8, 8, 8, 8],
                "sizingH": "fill", "sizingV": "hug", "cornerRadius": 6, "stroke":
                FORT if j == 2 else TRAIT, "strokeWeight": 2 if j == 2 else 1,
                "strokeAlign": "inside"})
            texte_flux(boite_, saisie[j] if j < len(saisie) else "", 15,
                       couleur=TEXTE if j < len(saisie) and saisie[j] else TRAIT)
    return t


def barre_plantes(f, compte):
    d.texte(f, TX, 104, 700, "Ce qui pousse", 32, gras=True)
    d.texte(f, TX, 150, 900, "Une ligne = un nom et une espèce : la bibliothèque propose les "
            "règles. Mettez 12 dans « Nb » pour créer 12 pommiers d'un coup.", 17, couleur=SECOND)
    coller = d.bouton(f, BW - 80 - 260, 104, 260, 48, "Coller depuis un tableur")
    if compte:
        d.texte(f, TX, 196, 900, compte, 15, gras=True)
    return coller


def pied_plantes(f):
    d.texte(f, TX, BH - 96, 700, "Chaque ligne est enregistrée dès qu'elle est complète. Vous "
            "pouvez partir et revenir : rien n'oblige à tout saisir aujourd'hui.", 14, couleur=SECOND)
    return d.bouton(f, BW - 80 - 300, BH - 112, 300, 56, "J'ai fini pour aujourd'hui",
                    principal=True)


# Premier passage : le tableau est vide.
b3v = ecran("B3 · 02 · Les plantes — vide")
entete(b3v, 2, "Le Clos")
b3v_coller = barre_plantes(b3v, None)
tableau(b3v, 236, [], saisie=["1", "", "", "Verger haut", "", ""])
d.texte(b3v, TX, 400, TW, "Pas de liste sous la main ? Commencez par les arbres : ce sont eux qui "
        "ont les dates les plus strictes.", 15, couleur=SECOND)
pied_plantes(b3v)

# En cours : la bibliothèque propose une espèce sur la ligne en saisie.
b3p = ecran("B3 · 03 · Les plantes — en cours")
entete(b3p, 2, "Le Clos")
b3p_coller = barre_plantes(b3p, "16 plantes saisies · 15 échéances déjà calculées")
tableau(b3p, 236, LIGNES, saisie=["1", "Cerisier du fond", "ceri", "Verger bas", "", ""])
liste = d.cadre("Bibliothèque · suggestions", b3p, TX + 64 + 300 + 6, 236 + 404, 300, 152,
                fond=FOND, rayon=8, trait=FORT)
for i, (s, n) in enumerate([("Cerisier", "Prunus avium"), ("Cerisier acide (griottier)",
                            "Prunus cerasus"), ("Sans espèce", "aucune règle proposée")]):
    o = d.cadre(f"Suggestion · {s}", liste, 1, 1 + i * 50, 298, 50, fond=SURFACE if i == 0 else FOND)
    d.texte(o, 12, 6, 270, s, 15, gras=True)
    d.texte(o, 12, 27, 270, n, 13, couleur=SECOND)
b3p_fini = pied_plantes(b3p)

# Coller une liste depuis un tableur (superposition).
col = ecran("B3 · 04 · Coller depuis un tableur", 760)
d.texte(col, 48, 40, 900, "Coller depuis un tableur", 28, gras=True)
d.texte(col, 48, 84, 1200, "Copiez les colonnes dans votre tableur, collez-les ici. Une ligne = une "
        "plante.", 16, couleur=SECOND)
zone_texte = d.cadre("Champ · collage", col, 48, 124, 520, 380, fond=SURFACE, rayon=8, trait=TRAIT)
d.texte(zone_texte, 16, 14, 490, "Prunier n°1\tPrunier\tVerger haut\nPrunier n°2\tPrunier\tVerger "
        "haut\nPrunier n°3\tPrunier\tVerger haut\nCognassier\tCognassier\tFond du jardin\nRosier "
        "'Pierre de Ronsard'\tRosier\tMassif d'entrée\nNéflier\tNéflier\tVerger bas\n…", 14,
        couleur=TEXTE)
d.texte(col, 608, 124, 600, "Ce que nous avons compris", 15, gras=True)
for j, (c, v) in enumerate([("Colonne 1", "Nom"), ("Colonne 2", "Espèce"), ("Colonne 3", "Zone")]):
    d.texte(col, 608 + j * 200, 158, 190, c, 13, couleur=SECOND)
    s = d.cadre(f"Choix · {c}", col, 608 + j * 200, 180, 184, 40, fond=FOND, rayon=6, trait=TRAIT)
    d.texte(s, 12, 10, 160, f"{v}  ↓", 15, gras=True)
VERDICTS = [("✓ 9 lignes prêtes", None, False),
            ("⚠ Ligne 4 : la zone « Fond du jardin » n'existe pas", "Créer la zone", True),
            ("⚠ Ligne 6 : « Néflier » n'est pas dans la bibliothèque — importé sans règle", None, True)]
for i, (v, action, fort) in enumerate(VERDICTS):
    with d.etat("attention" if fort else "fait", fond=fort):
        r = d.cadre("Verdict", col, 608, 248 + i * 64, 784, 56, fond=FOND, rayon=8,
                    trait=FORT if fort else TRAIT)
        d.texte(r, 14, 17, 560 if action else 760, v, 15, gras=fort)
    if action:
        d.bouton(r, 784 - 170, 6, 160, 44, action)
col_annuler = d.bouton(col, BW - 48 - 440, 760 - 96, 200, 56, "Annuler")
col_importer = d.bouton(col, BW - 48 - 220, 760 - 96, 220, 56, "Importer 11 plantes",
                        principal=True)

# Pause : on s'arrête, l'outil sert déjà (RG-27).
pause = ecran("B3 · 05 · On s'arrête là pour aujourd'hui")
entete(pause, 2, "Le Clos")
d.texte(pause, CX, 200, COL, "27 plantes saisies, 24 échéances déjà calculées.", 32, gras=True,
        align="center")
d.texte(pause, CX, 300, COL, "L'outil sert dès maintenant, même si l'inventaire n'est pas complet. "
        "Vous reprendrez la saisie ici quand vous voudrez : Inventaire → Ajouter des plantes.",
        17, couleur=SECOND, align="center")
voir = d.bouton(pause, CX + (COL - 320) // 2, 400, 320, 56, "Voir ce qu'il y a à faire",
                principal=True)
reprendre = d.bouton(pause, CX + (COL - 320) // 2, 468, 320, 56, "Continuer la saisie")
d.texte(pause, CX, 560, COL, "Placer ces plantes sur le plan du terrain : se dessine en vague 6 "
        "(B13).", 14, couleur=TRAIT, align="center")

# Le cadre de B4 naît ici pour que le parcours P0 y aboutisse ; vague4.py le remplit.
b4 = ecran("B4 · 01 · À faire")

# --- Prototype : P0 ------------------------------------------------------------
for c in b2a_cartes:
    d.lien(c, b2b, "instant")
for c in b2c_cartes:
    d.lien(c, b2b, "instant")
d.lien(b2a_suite, b2c, "instant")
d.lien(b2b_suite, b3z)
d.lien(b3z_suite, b3v)
for bouton in (b3v_coller, b3p_coller):
    d.cmd("proto.interaction.add", id=bouton, trigger={"type": "onClick"}, action={
        "type": "openOverlay", "dest": col, "position": "center", "close_on_outside": True,
        "background": "#00000066"}, transition={"type": "dissolve"}, durationMs=180)
d.cmd("proto.interaction.add", id=col_annuler, trigger={"type": "onClick"},
      action={"type": "closeOverlay"})
d.cmd("proto.interaction.add", id=col_importer, trigger={"type": "onClick"},
      action={"type": "navigate", "dest": b3p}, transition={"type": "dissolve"}, durationMs=180)
d.lien(b3p_fini, pause, "dissolve")
d.lien(voir, b4, "dissolve")
d.lien(reprendre, b3p, "dissolve")
d.cmd("proto.flow.add", start=b2a, name="Vague 2 — mise en route (P0)")
d.cmd("proto.settings", device="Custom size", width=BW, height=BH)

d.noeud("text", x=-680, y=120, text=(
    "VAGUE 2 — MISE EN ROUTE (P0)   [à valider]\n\n"
    "B2 · Deux questions : le nom et le climat. Le climat n'est pas présélectionné : un choix par "
    "défaut passerait sans être lu, et donnerait des dates fausses sans que rien ne proteste "
    "(RG-23). Chaque climat dit ce qu'il change (« 7 jours plus tard »), pas un chiffre brut.\n\n"
    "B3 · Les zones d'abord (5 à 10 lignes), puis les plantes dans un tableau : une ligne = un "
    "nom et une espèce. La colonne « Nb » fait la duplication (12 pommiers numérotés d'un coup). "
    "La dernière colonne montre ce que l'outil en tire tout de suite : c'est la preuve que la "
    "saisie sert (RG-27).\n\n"
    "Coller depuis un tableur : l'outil devine les colonnes, et signale ce qui ne passe pas sans "
    "bloquer le reste.\n\n"
    "Pas de bouton « Enregistrer » : chaque ligne complète est gardée. « J'ai fini pour "
    "aujourd'hui » n'est qu'une sortie.\n\n"
    "Plus tard : placer les plantes sur le plan (vague 6), B4 (vague 4).\n\n"
    "Prototype : cliquez « Continuer » sans climat pour voir l'erreur. Données fictives."),
    name="Notes · vague 2", props={"width": 560, "textAutoResize": "height", "fontFamily": "Inter",
                                   "fontSize": 16, "fills": [TEXTE]})
