"""Wireframes de la vague 5 : le socle du bureau (B1, B10, B11, B12).

Connexion, étiquettes, catalogue des types d'intervention, export. La V1 n'a
qu'un compte propriétaire, créé à l'installation (CDC §6, lot 0) : ni
inscription publique ni mot de passe oublié, qui sont du lot 1.
"""
from __future__ import annotations

import vague3
from vague1 import BLANC, FOND, FORT, SECOND, SURFACE, TEXTE, TRAIT, d
from vague2 import BH, BW, b4, champ, ecran
from vague3 import CW, CX0, case, coquille, pastille

N0 = len(vague3.LIENS_NAV)

# --- B1 · Connexion ---------------------------------------------------------------


def connexion(nom, erreur=None):
    f = ecran(nom)
    d.cadre("Fond", f, 0, 0, BW, BH, fond=SURFACE)
    c = d.cadre("Carte · connexion", f, (BW - 440) // 2, 160, 440, 530 if erreur else 470,
                fond=FOND, rayon=16)
    logo = d.cadre("Marque/Logo (provisoire)", c, 40, 40, 112, 32, rayon=6, trait=TRAIT,
                   pointille=True)
    d.texte(logo, 0, 7, 112, "Logo", 13, couleur=SECOND, align="center")
    d.texte(c, 40, 96, 360, "Se connecter", 26, gras=True)
    y = 148
    if erreur:
        e = d.cadre("Erreur", c, 40, y, 360, 44, fond=SURFACE, rayon=8, trait=FORT)
        d.texte(e, 14, 12, 332, "⚠ " + erreur, 14, gras=True)
        y += 60
    champ(c, 40, y, 360, "Adresse électronique", "leon@exemple.fr")
    champ(c, 40, y + 100, 360, "Mot de passe", "••••••••••" if not erreur else "•••••••")
    ok = d.bouton(c, 40, y + 204, 360, 56, "Se connecter", principal=True)
    d.texte(c, 40, y + 276, 360, "Rester connecté sur cet appareil : oui, 30 jours", 13,
            couleur=SECOND)
    return f, ok


b1, b1_ok = connexion("B1 · 01 · Connexion")
b1e, b1e_ok = connexion("B1 · 02 · Connexion — refusée",
                        "Adresse ou mot de passe incorrect.")

# --- B10 · Étiquettes -------------------------------------------------------------
b10 = ecran("B10 · 01 · Étiquettes")
coquille(b10, "Étiquettes")
d.texte(b10, CX0, 40, 600, "Étiquettes", 32, gras=True)
d.texte(b10, CX0, 86, 760, "Le code court est gravé une fois pour toutes : il ne change jamais, "
        "même si la plante est renommée ou archivée.", 16, couleur=SECOND)
FG = 400
d.texte(b10, CX0, 140, FG, "Quelles plantes", 15, gras=True)
for k, (lib, n, sel) in enumerate([("Verger haut", 18, True), ("Verger bas", 9, True),
                                   ("Haies", 6, False), ("Massif d'entrée", 11, False),
                                   ("Pelouse", 3, False)]):
    case(b10, CX0, 172 + k * 36, sel)
    d.texte(b10, CX0 + 32, 172 + k * 36, 260, lib, 15, gras=sel)
    d.texte(b10, CX0 + 300, 172 + k * 36, 100, f"{n}", 15, couleur=SECOND, align="right")
case(b10, CX0, 372, True)
d.texte(b10, CX0 + 32, 372, FG - 32, "Seulement celles sans étiquette imprimée", 15)
d.texte(b10, CX0, 424, FG, "Format", 15, gras=True)
for k, (lib, sel) in enumerate([("Planche A4 · 21 étiquettes (63,5 × 38,1 mm)", True),
                                ("Une étiquette par page", False)]):
    r = d.cadre(f"Format · {lib}", b10, CX0, 452 + k * 52, FG, 44, fond=FOND, rayon=8,
                trait=FORT if sel else TRAIT)
    d.cadre("Radio", r, 14, 12, 20, 20, fond=FORT if sel else FOND, rayon=10, trait=FORT)
    d.texte(r, 46, 12, FG - 60, lib, 14, gras=sel)
case(b10, CX0, 572, True)
d.texte(b10, CX0 + 32, 572, FG - 32, "Ajouter le QR code (ouvre la fiche au scan)", 15)
d.texte(b10, CX0, 616, FG, "Le QR ne donne accès à rien sans être connecté au jardin.", 13,
        couleur=SECOND)
b10_imprimer = d.bouton(b10, CX0, 680, FG, 56, "Imprimer 14 étiquettes (PDF)", principal=True)
d.texte(b10, CX0, 748, FG, "14 plantes du Verger haut et du Verger bas n'ont pas encore "
        "d'étiquette.", 13, couleur=SECOND)

XA = CX0 + FG + 48
AW = CW - FG - 48
d.texte(b10, XA, 140, AW, "Aperçu de la planche", 15, gras=True)
planche = d.cadre("Planche A4", b10, XA, 172, 470, 664, fond=SURFACE, trait=TRAIT)
CODES = [("FRU-002", "Pommier 1"), ("FRU-003", "Pommier 2"), ("FRU-004", "Pommier 3"),
         ("FRU-005", "Pommier 4"), ("FRU-006", "Pommier 5"), ("FRU-007", "Pommier 6"),
         ("FRU-008", "Pommier 7"), ("FRU-009", "Pommier 8"), ("FRU-010", "Pommier 9"),
         ("FRU-011", "Pommier 10"), ("FRU-012", "Pommier 11"), ("FRU-013", "Pommier 12"),
         ("FRU-015", "Pommier 'Calville blanc'"), ("FRU-030", "Poirier 'Conférence'")]
for k in range(21):
    x, y = 14 + (k % 3) * 150, 14 + (k // 3) * 92
    e = d.cadre("Étiquette", planche, x, y, 142, 84, fond=FOND, rayon=4, trait=TRAIT)
    if k < len(CODES):
        code, nom = CODES[k]
        qr = d.cadre("QR", e, 8, 10, 56, 56, fond=FOND, trait=FORT)
        for i in range(3):
            d.cadre("Module", qr, 6 + i * 16, 6 + (i % 2) * 22, 12, 12, fond=FORT)
        d.texte(e, 70, 12, 70, code, 15, gras=True)
        d.texte(e, 70, 34, 70, nom, 10, couleur=SECOND)
        d.texte(e, 70, 64, 70, "Le Clos", 9, couleur=SECOND)
d.texte(b10, XA + 490, 172, AW - 490, "Le code en grand, lisible à un mètre : c'est lui qui "
        "fait foi si le QR s'abîme.\n\nLe nom en petit, pour le repérage ; il peut vieillir "
        "sans gêne.", 13, couleur=SECOND)

# --- B11 · Catalogue des types d'intervention -------------------------------------
FAMILLES = [  # CDC §3.7 ; « ajouté par vous » marque l'extension (RG-03)
    ("Taille", [("Taille de formation", "fourni", 0), ("Taille d'entretien / fructification",
                                                        "fourni", 31),
                ("Taille de haie", "fourni", 6), ("Rabattage", "fourni", 0)]),
    ("Traitement", [("Traitement cuivre", "fourni", 22), ("Huile blanche", "fourni", 0)]),
    ("Entretien courant", [("Tonte", "fourni", 48), ("Ramassage des feuilles", "fourni", 3),
                           ("Ramassage des fruits tombés", "ajouté par vous", 4)]),
    ("Récolte", [("Récolte", "fourni", 12)]),
]


def catalogue(nom, ajout=False):
    f = ecran(nom, BH + (240 if ajout else 80))   # la page défile
    coquille(f, "Types d'intervention")
    d.texte(f, CX0, 40, 700, "Types d'intervention", 32, gras=True)
    d.texte(f, CX0, 86, 760, "Le catalogue fourni, plus les types que vous ajoutez. La famille "
            "décide des champs demandés à la saisie.", 16, couleur=SECOND)
    nouveau = d.bouton(f, CX0 + CW - 220, 40, 220, 48, "Nouveau type", principal=True)
    y = 140
    if ajout:
        a = d.cadre("Ajout · nouveau type", f, CX0, y, CW, 120, fond=SURFACE, rayon=10)
        champ(a, 20, 12, 420, "Nom", "Badigeon des troncs")
        champ(a, 456, 12, 300, "Famille", "Traitement  ↓")
        d.bouton(a, CW - 20 - 140, 46, 140, 48, "Ajouter", principal=True)
        d.bouton(a, CW - 20 - 140 - 12 - 120, 46, 120, 48, "Annuler")
        d.texte(f, CX0, y + 128, CW, "Un type proche existe déjà : « Badigeon » (Traitement, "
                "fourni). Ajoutez quand même si c'est un geste différent.", 14, gras=True)
        y += 168
    for fam, types in FAMILLES:
        d.texte(f, CX0, y, 400, fam.upper(), 13, gras=True, couleur=SECOND)
        y += 26
        for nom_t, origine, n in types:
            r = d.cadre(f"Type · {nom_t}", f, CX0, y, CW, 44, fond=FOND, trait=TRAIT)
            d.texte(r, 16, 12, 420, nom_t, 15, gras=origine != "fourni")
            pastille(r, 460, 9, origine, False)
            d.texte(r, 700, 12, 240, f"utilisé {n} fois" if n else "jamais utilisé", 14,
                    couleur=SECOND)
            d.texte(r, CW - 216, 12, 200, "Renommer · Masquer", 14, couleur=SECOND,
                    align="right")
            y += 44
        y += 14
    d.texte(f, CX0, y, CW, "Un type déjà utilisé se masque, il ne se supprime pas : "
            "l'historique en garde la trace.", 13, couleur=SECOND)
    return f, nouveau


b11, b11_nouveau = catalogue("B11 · 01 · Types d'intervention")
b11a, _ = catalogue("B11 · 02 · Types d'intervention — ajout", ajout=True)

# --- B12 · Export ----------------------------------------------------------------


def export(nom, pret=False):
    f = ecran(nom)
    coquille(f, "Export")
    d.texte(f, CX0, 40, 700, "Export des données", 32, gras=True)
    d.texte(f, CX0, 86, 800, "Tout le jardin, dans des formats qui se relisent sans l'outil. "
            "À tout moment, autant de fois que vous voulez.", 16, couleur=SECOND)
    W2 = 640
    d.texte(f, CX0, 150, W2, "Ce que contient l'archive", 15, gras=True)
    for k, (lib, n) in enumerate([("Zones", "6"), ("Plantes, y compris archivées", "151"),
                                  ("Interventions", "1 284"), ("Règles et leurs versions", "212"),
                                  ("Échéances, y compris manquées", "389"),
                                  ("Types d'intervention", "29")]):
        d.texte(f, CX0, 182 + k * 32, 400, lib, 15)
        d.texte(f, CX0 + 420, 182 + k * 32, 120, n, 15, couleur=SECOND, align="right")
    case(f, CX0, 392, True)
    d.texte(f, CX0 + 32, 392, W2, "Avec les photos (412 photos, environ 1,2 Go)", 15)
    d.texte(f, CX0, 444, W2, "Format", 15, gras=True)
    for k, (lib, sel) in enumerate([("JSON et CSV, dans une archive .zip", True),
                                    ("CSV seulement (un fichier par table)", False)]):
        r = d.cadre(f"Format · {lib}", f, CX0, 472 + k * 52, W2, 44, fond=FOND, rayon=8,
                    trait=FORT if sel else TRAIT)
        d.cadre("Radio", r, 14, 12, 20, 20, fond=FORT if sel else FOND, rayon=10, trait=FORT)
        d.texte(r, 46, 12, W2 - 60, lib, 15, gras=sel)
    if not pret:
        go = d.bouton(f, CX0, 600, 300, 56, "Préparer l'export", principal=True)
        d.texte(f, CX0, 670, W2, "La préparation prend quelques minutes avec les photos. Vous "
                "pouvez quitter la page : le lien sera ici.", 13, couleur=SECOND)
    else:
        go = None
        z = d.cadre("Export prêt", f, CX0, 600, W2, 132, fond=SURFACE, rayon=10, trait=FORT)
        d.texte(z, 20, 16, W2 - 40, "✓ Export prêt · le-clos-2026-10-21.zip · 1,2 Go", 16,
                gras=True)
        d.texte(z, 20, 42, W2 - 40, "Contient un fichier LISEZMOI qui décrit chaque colonne. "
                "Lien valable 7 jours.", 13, couleur=SECOND)
        d.bouton(z, 20, 72, 200, 48, "Télécharger", principal=True)
    XH = CX0 + W2 + 64
    d.texte(f, XH, 150, CW - W2 - 64, "Derniers exports", 15, gras=True)
    for k, (q, s) in enumerate([("21/10/2026", "avec photos · 1,2 Go")] * pret
                               + [("01/09/2026", "avec photos · 1,1 Go"),
                                  ("02/05/2026", "sans photos · 3 Mo")]):
        d.texte(f, XH, 182 + k * 48, 200, q, 15, gras=True)
        d.texte(f, XH, 204 + k * 48, 300, s, 13, couleur=SECOND)
    return f, go


b12, b12_go = export("B12 · 01 · Export")
b12p, _ = export("B12 · 02 · Export — prêt", pret=True)

# --- Prototype -----------------------------------------------------------------
d.lien(b1_ok, b4, "dissolve")
d.lien(b1e_ok, b1, "instant")
d.lien(b11_nouveau, b11a, "instant")
d.lien(b12_go, b12p, "dissolve")
for bouton, nom in vague3.LIENS_NAV:     # toutes les coquilles, y compris les vagues 3 et 4
    dest = {"Étiquettes": b10, "Types d'intervention": b11, "Export": b12}.get(nom)
    if dest:
        d.lien(bouton, dest, "instant")
for bouton, nom in vague3.LIENS_NAV[N0:]:
    if nom == "Plantes":
        d.lien(bouton, vague3.b5, "instant")
    elif nom == "À faire":
        d.lien(bouton, b4, "instant")
d.cmd("proto.flow.add", start=b1, name="Vague 5 — connexion, étiquettes, catalogue, export")

d.noeud("text", x=-680, y=3000, text=(
    "VAGUE 5 — LE SOCLE DU BUREAU   [à revoir]\n\n"
    "B1 · Connexion : un seul compte en V1, créé à l'installation. Pas d'inscription ni de mot "
    "de passe oublié (lot 1). L'erreur ne dit pas lequel des deux est faux, à dessein.\n\n"
    "B10 · Étiquettes : par zone, par défaut celles qui n'ont pas encore d'étiquette. Le code "
    "court en grand fait foi ; le QR (lot 4, en V1) ouvre la fiche.\n\n"
    "B11 · Catalogue : fourni + ajouté (RG-03). Un type utilisé se masque, ne se supprime pas. "
    "Un doublon probable est signalé, pas interdit.\n\n"
    "B12 · Export : JSON + CSV, photos comprises, avec un LISEZMOI (§8.1, RG-11)."),
    name="Notes · vague 5", props={
        "width": 560, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 16,
        "fills": [TEXTE]})
