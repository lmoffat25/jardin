"""Wireframes de la vague 6 : la carte (B13, T7) et l'étiquette scannée (T6).

Lot 4 du CDC. La carte situe, elle n'identifie pas (RG-13) ; aucune fonction ne
dépend du fond (RG-47). Sur le terrain, la tournée est un panneau fixe sous la
carte, sans glissé : même raison que le bouton « Carte » de T1 (19/10/2026).
"""
from __future__ import annotations

import vague1
import vague3
from vague1 import BLANC, FOND, FORT, M, SECOND, SURFACE, TEXTE, TRAIT, W, d, ids_pages
from vague2 import BH, BW, b4, champ, ecran
from vague3 import NAV, coquille, pastille

N0 = len(vague3.LIENS_NAV)
ARBRES = [  # nom, x, y (en % de la carte), dû aujourd'hui ?, numéro de tournée
    ("Prunier n°3", .30, .30, True, 1), ("Pommier 'Reine des reinettes'", .40, .26, False, 0),
    ("Pommier 1", .48, .30, False, 0), ("Pommier 2", .56, .26, False, 0),
    ("Poirier 'Conférence'", .34, .62, True, 2), ("Cerisier 'Burlat'", .46, .66, False, 0),
]


def fond_carte(parent, x, y, w, h, fond: str):
    """Fond « aérienne », « plan » ou « neutre ». Le neutre garde l'échelle (RG-47)."""
    c = d.cadre(f"Carte · fond {fond}", parent, x, y, w, h, fond=SURFACE if fond != "neutre"
                else FOND)
    if fond == "aérienne":
        for i in range(6):   # parcelles voisines, en aplats
            d.cadre("Aplat", c, (i * 173) % w, (i * 97) % h, 160, 110, fond="#DEDEDE", rayon=4)
        d.texte(c, w - 332, h - 28, 190, "Photographie aérienne · IGN", 11, couleur=SECOND,
                align="right")
    elif fond == "neutre":
        for i in range(1, 12):
            d.cadre("Quadrillage", c, i * w / 12, 0, 1, h, fond=SURFACE)
            d.cadre("Quadrillage", c, 0, i * h / 12, w, 1, fond=SURFACE)
    d.cadre("Échelle", c, w - 132, h - 32, 100, 4, fond=FORT)
    d.texte(c, w - 132, h - 26, 100, "20 m", 11, couleur=SECOND, align="center")
    # Géométries : une haie (ligne), la pelouse et deux zones (surfaces)
    d.cadre("Zone · Verger haut", c, w * .24, h * .16, w * .40, h * .26, rayon=6, trait=SECOND,
            pointille=True)
    d.texte(c, w * .24 + 6, h * .16 + 4, 200, "Verger haut", 11, gras=True, couleur=SECOND)
    d.cadre("Zone · Verger bas", c, w * .26, h * .52, w * .30, h * .24, rayon=6, trait=SECOND,
            pointille=True)
    d.texte(c, w * .26 + 6, h * .52 + 4, 200, "Verger bas", 11, gras=True, couleur=SECOND)
    d.cadre("Pelouse devant la maison", c, w * .70, h * .50, w * .22, h * .32, fond=FOND, rayon=4,
            trait=FORT)
    d.texte(c, w * .70 + 6, h * .50 + 6, 160, "Pelouse", 11, couleur=SECOND)
    d.cadre("Haie de charmilles — côté route", c, w * .08, h * .84, w * .84, 6, fond=FORT,
            rayon=3)
    d.texte(c, w * .08, h * .84 - 18, 300, "Haie de charmilles", 11, couleur=SECOND)
    pions = {}
    for nom, px, py, du, n in ARBRES:
        r = 13 if du else 8
        p = d.cadre(f"Plante · {nom}", c, w * px - r, h * py - r, 2 * r, 2 * r,
                    fond=FORT if du else FOND, rayon=r, trait=FORT)
        if n:
            d.texte(p, 0, 4, 2 * r, str(n), 13, gras=True, couleur=BLANC, align="center")
        pions[nom] = p
    # La pelouse et la haie sont dues aussi : elles portent leur numéro de tournée
    for nom, px, py, n in [("Pelouse devant la maison", .81, .66, 3),
                           ("Haie de charmilles — côté route", .50, .845, 4)]:
        p = d.cadre(f"Plante · {nom}", c, w * px - 13, h * py - 13, 26, 26, fond=FORT, rayon=13,
                    trait=FOND)
        d.texte(p, 0, 4, 26, str(n), 13, gras=True, couleur=BLANC, align="center")
        pions[nom] = p
    return c, pions


# --- B13 · Carte bureau ------------------------------------------------------------
PANNEAU = 340
MW = BW - NAV - PANNEAU


def carte_bureau(nom, fond="aérienne", outil="Sélectionner", selection=None, bandeau=None):
    f = ecran(nom)
    coquille(f, "Carte")
    c, pions = fond_carte(f, NAV, 0, MW, BH, fond)
    barre = d.cadre("Outils", f, NAV + 16, 16, 640, 48, fond=FOND, rayon=10, trait=TRAIT)
    outils = {}
    x = 4
    for o in ["Sélectionner", "Poser une plante", "Tracer une ligne", "Tracer une surface"]:
        w = 24 + 8 * len(o)
        sel = o == outil
        b = d.cadre(f"Outil · {o}", barre, x, 4, w, 40, fond=FORT if sel else FOND, rayon=8)
        d.texte(b, 0, 11, w, o, 14, gras=True, couleur=BLANC if sel else TEXTE, align="center")
        outils[o] = b
        x += w + 4
    fonds = d.cadre("Choix du fond", f, NAV + 16, BH - 64, 300, 48, fond=FOND, rayon=10,
                    trait=TRAIT)
    choix_fond = {}
    for k, lib in enumerate(["Aérienne", "Plan importé", "Neutre"]):
        sel = lib.lower() == fond or (lib == "Plan importé" and fond == "plan")
        b = d.cadre(f"Fond · {lib}", fonds, 4 + k * 98, 4, 94, 40, fond=FORT if sel else FOND,
                    rayon=8)
        d.texte(b, 0, 11, 94, lib, 13, gras=True, couleur=BLANC if sel else TEXTE, align="center")
        choix_fond[lib] = b
    if bandeau:
        bd = d.cadre("Bandeau · fond", f, NAV + 16, 80, MW - 32, 48, fond=FOND, rayon=10,
                     trait=FORT)
        d.texte(bd, 16, 14, MW - 64, bandeau, 14, gras=True)
    pan = d.cadre("Panneau", f, BW - PANNEAU, 0, PANNEAU, BH, fond=FOND)
    d.cadre("Séparateur", pan, 0, 0, 1, BH, fond=TRAIT)
    return f, c, pions, outils, choix_fond, pan


b13, b13_carte, b13_pions, b13_outils, b13_fonds, pan = carte_bureau("B13 · 01 · Carte")
d.texte(pan, 24, 24, PANNEAU - 48, "Aujourd'hui sur la carte", 18, gras=True)
d.texte(pan, 24, 54, PANNEAU - 48, "4 plantes dues, numérotées dans l'ordre de la tournée.", 14,
        couleur=SECOND)
for k, (n, nom, geste) in enumerate([(1, "Prunier n°3", "Traitement cuivre · urgent"),
                                     (2, "Poirier 'Conférence'", "Traitement cuivre"),
                                     (3, "Pelouse devant la maison", "Tonte"),
                                     (4, "Haie de charmilles", "Taille")]):
    p = d.cadre("Numéro", pan, 24, 100 + k * 56, 26, 26, fond=FORT, rayon=13)
    d.texte(p, 0, 4, 26, str(n), 13, gras=True, couleur=BLANC, align="center")
    d.texte(pan, 62, 98 + k * 56, PANNEAU - 86, nom, 15, gras=True)
    d.texte(pan, 62, 120 + k * 56, PANNEAU - 86, geste, 13, couleur=SECOND)
d.cadre("Séparateur", pan, 24, 336, PANNEAU - 48, 1, fond=TRAIT)
d.texte(pan, 24, 356, PANNEAU - 48, "À placer · 12 sans position", 18, gras=True)
d.texte(pan, 24, 386, PANNEAU - 48, "Glissez-les sur la carte, une par une.", 14, couleur=SECOND)
a_placer = []
for k in range(5):
    r = d.cadre(f"À placer · Pommier {k + 4}", pan, 24, 420 + k * 52, PANNEAU - 48, 44,
                fond=SURFACE, rayon=8)
    poignee = d.cadre("Poignée", r, 12, 14, 14, 16)
    for i in range(3):
        d.cadre("Trait", poignee, 0, i * 6, 14, 2, fond=SECOND)
    d.texte(r, 38, 12, 200, f"Pommier {k + 4}", 15, gras=True)
    d.texte(r, PANNEAU - 48 - 96, 13, 84, f"FRU-{k + 5:03d}", 13, couleur=SECOND, align="right")
    a_placer.append(r)
d.texte(pan, 24, 690, PANNEAU - 48, "et 7 autres", 14, couleur=SECOND)
# Plante sélectionnée sur la carte : une bulle, avec l'action « fait » (§6, lot 4)
px, py = MW * .34, BH * .62
bulle = d.cadre("Bulle · Poirier 'Conférence'", b13_carte, px + 20, py - 150, 330, 190,
                fond=FOND, rayon=12, trait=FORT)
d.texte(bulle, 16, 14, 298, "Poirier 'Conférence' · FRU-030", 16, gras=True)
pastille(bulle, 16, 44, "ouverte")
d.texte(bulle, 106, 48, 210, "Traitement cuivre", 14)
d.texte(bulle, 16, 80, 298, "Jusqu'au 15 nov. · règle de la bibliothèque, décalée de 7 jours. "
        "Position : relevé GPS, ± 6 m.", 13, couleur=SECOND)
b13_fait = d.bouton(bulle, 16, 132, 88, 44, "Fait", principal=True)
b13_fiche = d.bouton(bulle, 112, 132, 150, 44, "Ouvrir la fiche")

# B13 · 02 : poser un pommier depuis le panneau
b13p, c_p, _, _, _, pan_p = carte_bureau("B13 · 02 · Carte — poser une plante",
                                          outil="Poser une plante")
d.texte(pan_p, 24, 24, PANNEAU - 48, "Pommier 4 · FRU-005", 18, gras=True)
d.texte(pan_p, 24, 56, PANNEAU - 48, "Cliquez à son emplacement. Sa position prendra la source "
        "« plan » : un relevé GPS ultérieur ne la déplacera pas (RG-48).", 14, couleur=SECOND)
fantome = d.cadre("Plante en cours de pose", c_p, MW * .64 - 10, BH * .30 - 10, 20, 20,
                  fond=FOND, rayon=10, trait=FORT, pointille=True)
d.texte(c_p, MW * .64 + 16, BH * .30 - 8, 200, "Pommier 4", 13, gras=True)
b13p_ok = d.bouton(pan_p, 24, 150, PANNEAU - 48, 52, "Poser et passer au suivant",
                   principal=True)
d.bouton(pan_p, 24, 214, PANNEAU - 48, 52, "Arrêter de poser")
d.texte(pan_p, 24, 290, PANNEAU - 48, "Restent 11 plantes à placer.", 14, couleur=SECOND)

# B13 · 03 : caler un plan importé, en trois points
b13c, c_c, _, _, _, pan_c = carte_bureau("B13 · 03 · Carte — caler un plan", fond="plan")
d.texte(pan_c, 24, 24, PANNEAU - 48, "Caler le plan importé", 18, gras=True)
d.texte(pan_c, 24, 56, PANNEAU - 48, "Désignez trois points que vous retrouvez sur le plan et "
        "sur la photographie aérienne : un angle de la maison, un piquet, un portail.", 14,
        couleur=SECOND)
for k, (lib, fait) in enumerate([("A · Angle de la maison", True), ("B · Portail", True),
                                 ("C · Piquet du fond", False)]):
    r = d.cadre(f"Point {k + 1}", pan_c, 24, 150 + k * 56, PANNEAU - 48, 48, fond=SURFACE,
                rayon=8, trait=FORT if not fait else None)
    d.texte(r, 14, 14, 200, lib, 15, gras=True)
    d.texte(r, PANNEAU - 48 - 120, 15, 106, "placé ✓" if fait else "à placer", 13,
            couleur=SECOND, align="right")
for k, (x_, y_) in enumerate([(.18, .40), (.80, .20), (.60, .86)]):
    p = d.cadre(f"Repère {k + 1}", c_c, MW * x_ - 14, BH * y_ - 14, 28, 28, fond=FOND, rayon=14,
                trait=FORT)
    d.texte(p, 0, 5, 28, "ABC"[k], 13, gras=True, align="center")
d.texte(pan_c, 24, 336, PANNEAU - 48, "L'écart du calage s'affiche au troisième point.", 13,
        couleur=SECOND)
d.bouton(pan_c, 24, BH - 88, PANNEAU - 48, 56, "Valider le calage", principal=True,
         inactif=True)

# B13 · 04 : la photo aérienne ne répond pas (RG-47)
b13n, _, _, _, _, pan_n = carte_bureau(
    "B13 · 04 · Carte — fond indisponible", fond="neutre",
    bandeau="La photographie aérienne ne répond pas : fond neutre. Échéances, saisie et tournée "
            "ne sont pas touchées.")
d.texte(pan_n, 24, 24, PANNEAU - 48, "Aujourd'hui sur la carte", 18, gras=True)
d.texte(pan_n, 24, 54, PANNEAU - 48, "4 plantes dues — le même contenu, sans le fond.", 14,
        couleur=SECOND)

# --- T7 · Carte terrain et tournée -----------------------------------------------
d.cmd("page.select", id=ids_pages["3 · Wireframes terrain"])
t7 = vague1.t7
H_CARTE = 420


def entete_carte(f):
    retour = d.bouton(f, M, 56, 104, 44, "‹ Liste")
    d.texte(f, M + 120, 64, 200, "Carte", 22, gras=True)
    return retour


def tournee(f, y, lignes, faits: dict):
    d.texte(f, M, y, 190, "TOURNÉE · 4 · ~180 m", 13, gras=True, couleur=SECOND)
    modifier = d.bouton(f, W - M - 160, y - 12, 160, 40, "Modifier l'ordre")
    for k, (n, nom, geste) in enumerate(lignes):
        yy = y + 36 + k * 64
        p = d.cadre("Numéro", f, M, yy + 14, 28, 28, fond=FORT, rayon=14)
        d.texte(p, 0, 5, 28, str(n), 13, gras=True, couleur=BLANC, align="center")
        d.texte(f, M + 40, yy + 6, 220, nom, 16, gras=True)
        d.texte(f, M + 40, yy + 30, 220, geste, 13, couleur=SECOND)
        faits[nom] = d.bouton(f, W - M - 88, yy + 4, 88, 48, "Fait")
    return modifier


TOURNEE = [(1, "Prunier n°3", "Traitement cuivre · urgent"),
           (2, "Poirier 'Conférence'", "Traitement cuivre · 40 m"),
           (3, "Pelouse devant la maison", "Tonte · 70 m"),
           (4, "Haie de charmilles", "Taille · 60 m")]

t7_retour = entete_carte(t7)
c7, pions7 = fond_carte(t7, 0, 108, W, H_CARTE, "aérienne")
moi = d.cadre("Ma position", c7, W * .22 - 9, H_CARTE * .44 - 9, 18, 18, fond=FOND, rayon=9,
              trait=FORT)
d.cadre("Point", moi, 5, 5, 8, 8, fond=FORT, rayon=4)
d.texte(c7, W * .22 - 40, H_CARTE * .44 + 12, 100, "Vous, ± 8 m", 11, gras=True,
        align="center")
faits7: dict = {}
t7_modifier = tournee(t7, 108 + H_CARTE + 20, TOURNEE[:4], faits7)

# T7 · 02 : une plante touchée sur la carte
t7b = vague1.ecran("T7 · 02 · Carte — plante touchée")
entete_carte(t7b)
c7b, _ = fond_carte(t7b, 0, 108, W, H_CARTE, "aérienne")
d.cadre("Voile", t7b, 0, 0, W, 844, fond="#00000066")
feuille = d.cadre("Feuille · Poirier 'Conférence'", t7b, 0, 844 - 300, W, 300, fond=FOND,
                  rayon=16)
d.texte(feuille, M, 24, W - 2 * M, "2 · Poirier 'Conférence'", 20, gras=True)
d.texte(feuille, M, 54, W - 2 * M, "FRU-030 · Verger bas · à 40 m", 14, couleur=SECOND)
d.texte(feuille, M, 90, W - 2 * M, "Traitement cuivre · jusqu'au 15 nov.", 16)
d.texte(feuille, M, 116, W - 2 * M, "Règle de la bibliothèque, décalée de 7 jours.", 13,
        couleur=SECOND)
t7b_fait = d.bouton(feuille, M, 160, W - 2 * M, 56, "Fait", principal=True)
t7b_fiche = d.bouton(feuille, M, 228, W - 2 * M, 48, "Ouvrir la fiche")

# T7 · 03 : modifier l'ordre de la tournée
t7c = vague1.ecran("T7 · 03 · Tournée — modifier l'ordre")
d.texte(t7c, M, 60, W - 2 * M, "Ordre de la tournée", 22, gras=True)
d.texte(t7c, M, 94, W - 2 * M, "Proposé du plus proche au plus loin, depuis votre position. "
        "C'est une suggestion.", 14, couleur=SECOND)
for k, (n, nom, geste) in enumerate([TOURNEE[0], TOURNEE[2], TOURNEE[1], TOURNEE[3]]):
    r = d.cadre(f"Ligne · {nom}", t7c, M, 160 + k * 92, W - 2 * M, 84, fond=FOND, rayon=10,
                trait=FORT if k == 1 else TRAIT)
    d.texte(r, 14, 12, 220, f"{k + 1} · {nom}", 15, gras=True)
    d.texte(r, 14, 56, 220, geste.split(" · ")[0], 13, couleur=SECOND)
    d.bouton(r, W - 2 * M - 112, 18, 48, 48, "↑", inactif=k == 0)
    d.bouton(r, W - 2 * M - 58, 18, 48, 48, "↓", inactif=k == 3)
d.texte(t7c, M, 540, W - 2 * M, "La pelouse est passée avant le poirier.", 14, couleur=SECOND)
d.bouton(t7c, M, 680, W - 2 * M, 48, "Revenir à l'ordre proposé")
t7c_ok = d.bouton(t7c, M, 740, W - 2 * M, 56, "Terminé", principal=True)

# T7 · 04 : sans fond (RG-47)
t7d = vague1.ecran("T7 · 04 · Carte — fond indisponible")
entete_carte(t7d)
fond_carte(t7d, 0, 108, W, H_CARTE, "neutre")
bd = d.cadre("Bandeau · fond", t7d, M, 120, W - 2 * M, 56, fond=FOND, rayon=10, trait=FORT)
d.texte(bd, 12, 8, W - 2 * M - 24, "Photo aérienne indisponible. Le reste fonctionne.", 14,
        gras=True)
tournee(t7d, 108 + H_CARTE + 20, TOURNEE, {})

# --- T6 · Une étiquette scannée --------------------------------------------------
t6 = vague1.ecran("T6 · 01 · Étiquette scannée (appareil photo)")
with d.surcharge({("fill", FORT): "fond/appareil-photo"}):
    d.cadre("Viseur", t6, 0, 44, W, 800, fond=FORT)
cadre_qr = d.cadre("Étiquette visée", t6, (W - 200) // 2, 260, 200, 120, fond=FOND, rayon=6)
d.cadre("QR", cadre_qr, 12, 12, 96, 96, fond=FOND, trait=FORT)
d.texte(cadre_qr, 116, 40, 80, "FRU-014", 16, gras=True)
lien_sys = d.cadre("Bandeau du système · ouvrir le lien", t6, M, 420, W - 2 * M, 64, fond=FOND,
                   rayon=14)
d.texte(lien_sys, 16, 12, W - 2 * M - 32, "Ouvrir dans le navigateur", 15, gras=True)
d.texte(lien_sys, 16, 36, W - 2 * M - 32, "jardin.exemple.fr/p/FRU-014", 13, couleur=SECOND)
d.texte(t6, M, 720, W - 2 * M, "L'appareil photo du téléphone, pas un lecteur dans l'outil : "
        "aucune application à installer.", 13, couleur=BLANC, align="center")

t6b = vague1.ecran("T6 · 02 · Étiquette inconnue")
d.texte(t6b, M, 120, W - 2 * M, "Aucune plante « FRU-099 » dans vos jardins", 22, gras=True)
d.texte(t6b, M, 186, W - 2 * M, "L'étiquette vient peut-être d'un autre jardin, ou la plante a "
        "été supprimée.", 15, couleur=SECOND)
d.bouton(t6b, M, 260, W - 2 * M, 56, "Chercher une plante par son nom", principal=True)
d.bouton(t6b, M, 328, W - 2 * M, 56, "Aller à Aujourd'hui")

t6c = vague1.ecran("T6 · 03 · Étiquette scannée — pas connecté")
d.texte(t6c, M, 100, W - 2 * M, "Connectez-vous pour ouvrir cette plante", 22, gras=True)
d.texte(t6c, M, 166, W - 2 * M, "La fiche s'ouvrira juste après.", 15, couleur=SECOND)
champ(t6c, M, 220, W - 2 * M, "Adresse électronique", "leon@exemple.fr")
champ(t6c, M, 320, W - 2 * M, "Mot de passe", "••••••••••")
t6c_ok = d.bouton(t6c, M, 424, W - 2 * M, 56, "Se connecter", principal=True)

# --- Prototype -----------------------------------------------------------------
d.retour(t7_retour)
for nom, b in faits7.items():
    d.cmd("proto.interaction.add", id=b, trigger={"type": "onClick"}, action={
        "type": "openOverlay", "dest": vague1.conf, "position": "bottomCenter",
        "close_on_outside": True, "background": "#00000066"},
        transition={"type": "moveIn", "direction": "top"}, durationMs=200)
d.lien(pions7["Poirier 'Conférence'"], t7b, "instant")
d.lien(t7b_fiche, vague3.t5)
d.cmd("proto.interaction.add", id=t7b_fait, trigger={"type": "onClick"}, action={
    "type": "openOverlay", "dest": vague1.conf, "position": "bottomCenter",
    "close_on_outside": True, "background": "#00000066"},
    transition={"type": "moveIn", "direction": "top"}, durationMs=200)
d.lien(t7_modifier, t7c)
d.lien(t7c_ok, t7)
d.lien(lien_sys, vague3.t5)
d.lien(t6c_ok, vague3.t5)
d.cmd("proto.flow.add", start=vague1.t1, name="Vague 6 — carte et tournée (terrain)")
d.cmd("proto.flow.add", start=t6, name="Vague 6 — étiquette scannée")

d.cmd("page.select", id=ids_pages["4 · Wireframes bureau"])
d.lien(b13_fiche, vague3.b6, "dissolve")
d.lien(b13_outils["Poser une plante"], b13p, "instant")
d.lien(a_placer[0], b13p, "instant")
d.lien(b13p_ok, b13, "instant")
d.lien(b13_fonds["Plan importé"], b13c, "instant")
d.lien(b13_fonds["Neutre"], b13n, "instant")
for bouton, nom in vague3.LIENS_NAV:
    if nom == "Carte":
        d.lien(bouton, b13, "instant")
for bouton, nom in vague3.LIENS_NAV[N0:]:
    if nom == "Plantes":
        d.lien(bouton, vague3.b5, "instant")
    elif nom == "À faire":
        d.lien(bouton, b4, "instant")
d.cmd("proto.flow.add", start=b13, name="Vague 6 — carte au bureau")

d.noeud("text", x=-680, y=3700, text=(
    "VAGUE 6 — LA CARTE   [à revoir]\n\n"
    "B13 · Carte bureau : les plantes dues sont numérotées dans l'ordre de la tournée ; un "
    "clic ouvre une bulle avec « Fait » et la fiche. Le panneau liste ce qui reste à placer : "
    "on crée 12 pommiers en lot, puis on les pose un par un (critère 24). Caler un plan : "
    "trois points. Fond indisponible : fond neutre à l'échelle, rien d'autre ne change "
    "(RG-47).\n\n"
    "T7 · Carte terrain : on y entre par le bouton « Carte » de T1. La tournée est un panneau "
    "fixe sous la carte, pas une liste à glisser (même raison que le bouton : un glissé est "
    "invisible et entre en conflit avec le déplacement de la carte). « Fait » y est à un geste "
    "depuis la tournée, à deux depuis la carte (critère 21). L'ordre se modifie avec ↑ ↓.\n\n"
    "T6 · Étiquette : l'appareil photo du téléphone ouvre l'adresse de la fiche. Un code "
    "inconnu ne dit pas s'il existe ailleurs (RG-21).\n\n"
    "Données fictives."), name="Notes · vague 6", props={
        "width": 560, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 16,
        "fills": [TEXTE]})
