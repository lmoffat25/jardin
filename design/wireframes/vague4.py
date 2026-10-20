"""Wireframes de la vague 4 : le travail au bureau (B4, B8, B9).

Le tableau « À faire » (P3), l'intervention passée sur plusieurs plantes (P5) et
la correction d'une règle (P7). Données fictives ; seules les valeurs des
exemples du CDC apparaissent (taille du pommier déc.–mars, haie 1er nov. →
15 mars, tonte tous les 7 jours, « moi, je tonds tous les 10 jours »).
"""
from __future__ import annotations

import vague3
from vague1 import BLANC, FOND, FORT, SECOND, SURFACE, TEXTE, TRAIT, d
from vague2 import BH, BW, b4, champ, ecran
from vague3 import CW, CX0, case, coquille, pastille, photo

N0 = len(vague3.LIENS_NAV)   # les coquilles de cette vague s'ajoutent après

# --- B4 · À faire (P3) ----------------------------------------------------------
ECHEANCES = [  # zone, plante, geste, état, fort, quand, explication
    ("Verger haut", "Prunier n°3", "Traitement cuivre", "urgente", True,
     "avant le 24 oct. · dans 3 jours",
     "Règle que vous avez définie, à la chute des feuilles."),
    ("Verger haut", "Pommier 'Reine des reinettes'", "Traitement cuivre", "ouverte", False,
     "jusqu'au 15 nov.", "Règle que vous avez définie."),
    ("Pelouse", "Pelouse devant la maison", "Tonte", "ouverte", False,
     "d'ici ven. 24 oct.", "Règle de la bibliothèque · 7 jours après la dernière tonte "
     "(17 oct.)."),
    ("Verger bas", "Poirier 'Conférence'", "Traitement cuivre", "ouverte", False,
     "jusqu'au 15 nov.", "Règle que vous avez définie."),
]  # zones dans l'ordre de leur échéance la plus proche (CDC §3.3)
COLS = [("", 44), ("Plante", 330), ("Geste", 190), ("État", 120), ("Quand", 260),
        ("", CW - 44 - 330 - 190 - 120 - 260)]


def a_faire(f, periode: str, groupe: bool, cochees=0):
    coquille(f, "À faire")
    d.texte(f, CX0, 40, 500, "À faire", 32, gras=True)
    d.texte(f, CX0, 86, 700, "Le Clos · 1 urgente · 3 ouvertes", 16,
            couleur=SECOND)
    passee = d.bouton(f, CX0 + CW - 520, 40, 300, 48, "Ajouter une intervention passée")
    imprimer = d.bouton(f, CX0 + CW - 208, 40, 208, 48, "Imprimer la liste")
    onglets = {}
    x = CX0
    for p in ["Aujourd'hui", "15 prochains jours", "Ce mois", "Tout"]:
        w = 40 + 9 * len(p)
        sel = p == periode
        b = d.cadre(f"Période · {p}", f, x, 128, w, 44, fond=FORT if sel else FOND, rayon=8,
                    trait=FORT)
        d.texte(b, 0, 12, w, p, 15, gras=True, couleur=BLANC if sel else TEXTE, align="center")
        onglets[p] = b
        x += w + 8
    g = d.cadre("Grouper par zone", f, CX0 + CW - 220, 128, 220, 44, fond=FOND, rayon=8,
                trait=TRAIT)
    case(g, 14, 12, groupe)
    d.texte(g, 46, 12, 170, "Grouper par zone", 15, gras=True)

    y = 196
    boutons_fait = []
    zone_prec = None
    for i, (zone, plante, geste, etat, fort, quand, expl) in enumerate(ECHEANCES):
        if groupe and zone != zone_prec:
            n = sum(1 for e in ECHEANCES if e[0] == zone)
            d.texte(f, CX0, y + 8, CW, f"{zone.upper()} · {n}", 13, gras=True, couleur=SECOND)
            y += 36
            zone_prec = zone
        with d.etat("en-retard", fond=False) if fort else d.surcharge({}):
            r = d.cadre(f"Échéance · {plante}", f, CX0, y, CW, 80,
                        fond=SURFACE if i < cochees else FOND, rayon=8, trait=FORT if fort else TRAIT)
        case(r, 12, 30, i < cochees)
        d.texte(r, 44, 16, 320, plante, 16, gras=True)
        d.texte(r, 44, 42, 600, expl, 13, couleur=SECOND)
        d.texte(r, 374, 16, 180, geste, 15)
        pastille(r, 564, 13, etat, fort)
        d.texte(r, 684, 16, 250, quand, 15, gras=fort)
        boutons_fait.append(d.bouton(r, CW - 100, 16, 88, 48, "Fait", principal=fort))
        y += 88
    return passee, imprimer, onglets, boutons_fait


b4_passee, b4_imprimer, b4_onglets, b4_fait = a_faire(b4, "15 prochains jours", True)
d.texte(b4, CX0, 818, CW, "Ce qui est à venir au-delà de 15 jours (taille de la haie dès le 8 nov., "
        "du pommier dès le 8 déc.) est dans « Tout ».", 14, couleur=SECOND)

b4s = ecran("B4 · 02 · À faire — 3 cochées")
a_faire(b4s, "15 prochains jours", True, cochees=3)
barre = d.cadre("Actions groupées", b4s, CX0, BH - 88, CW, 72, fond=FORT, rayon=12)
d.texte(barre, 24, 25, 300, "3 échéances cochées", 17, gras=True, couleur=BLANC)
b4s_fait = d.bouton(barre, CW - 24 - 200 - 10 - 160 - 10 - 140, 12, 200, 48, "Tout marquer fait",
                    principal=False)
d.bouton(barre, CW - 24 - 160 - 10 - 140, 12, 160, 48, "Reporter…")
d.bouton(barre, CW - 24 - 140, 12, 140, 48, "Imprimer")

# Impression : la liste du week-end, sur une feuille A4.
imp = ecran("B4 · 03 · Liste imprimée (aperçu)")
d.cadre("Fond", imp, 0, 0, BW, BH, fond=SURFACE)
feuille = d.cadre("Feuille A4", imp, (BW - 600) // 2, 40, 600, 820, fond=FOND, trait=TRAIT)
d.texte(feuille, 48, 44, 504, "Le Clos — à faire du 21 oct. au 4 nov.", 20, gras=True)
d.texte(feuille, 48, 76, 504, "Imprimée le 21 oct. · 4 échéances", 13, couleur=SECOND)
y = 120
prec = None
for zone, plante, geste, etat, fort, quand, _ in ECHEANCES:
    if zone != prec:
        d.texte(feuille, 48, y, 504, zone.upper(), 12, gras=True, couleur=SECOND)
        y += 28
        prec = zone
    d.cadre("Case à cocher au stylo", feuille, 48, y + 2, 16, 16, fond=FOND, trait=FORT)
    d.texte(feuille, 76, y, 360, f"{geste} · {plante}", 14, gras=fort)
    d.texte(feuille, 440, y, 112, quand.split(" · ")[0], 13, couleur=SECOND, align="right")
    y += 34
d.texte(feuille, 48, 760, 504, "Cochez au stylo, saisissez ensuite : « Ajouter une intervention "
        "passée ».", 12, couleur=SECOND)
imp_retour = d.bouton(imp, BW - 240, 40, 200, 48, "Retour")

# --- B8 · Intervention passée, sur plusieurs plantes (P5) -----------------------
FG = 560
XR = CX0 + FG + 48
FD = CW - FG - 48
ZONES = {"Verger haut": ["Pommier 'Reine des reinettes' · FRU-001", "Pommier 1 · FRU-002",
                         "Pommier 2 · FRU-003", "Pommier 3 · FRU-004",
                         "Pommier 'Belle de Boskoop' · FRU-014",
                         "Pommier 'Calville blanc' · FRU-015"],
         "Verger bas": ["Cerisier 'Burlat' · FRU-022", "Poirier 'Conférence' · FRU-030"]}


def passee(nom, choisies: int, enregistre=False):
    f = ecran(nom)
    coquille(f, "À faire")
    d.texte(f, CX0, 24, 600, "À faire  ›  Intervention passée", 14, couleur=SECOND)
    d.texte(f, CX0, 52, 800, "Ajouter une intervention passée", 32, gras=True)
    champ(f, CX0, 120, 260, "Date", "12/02/2026" if choisies else None, "jj/mm/aaaa")
    d.texte(f, CX0 + 276, 156, 280, "il y a 8 mois" if choisies else "", 14, couleur=SECOND)
    champ(f, CX0, 224, FG, "Geste", "Taille  ↓" if choisies else None, "Choisissez  ↓")
    champ(f, CX0, 328, 260, "Durée totale (facultatif)", "4 h" if choisies else None, "—")
    champ(f, CX0, 432, FG, "Note (facultatif)", "Taille d'hiver de tout le verger haut"
          if choisies else None, "—")
    d.texte(f, CX0, 536, FG, "Photos (facultatif)", 15, gras=True)
    depot = d.cadre("Dépôt · photos", f, CX0, 562, FG, 80, fond=SURFACE, rayon=8, trait=TRAIT,
                    pointille=True)
    d.texte(depot, 0, 28, FG, "Glissez des photos : leur date de prise de vue est conservée", 14,
            couleur=SECOND, align="center")

    pan = d.cadre("Plantes concernées", f, XR, 120, FD, 630, fond=SURFACE, rayon=12)
    d.texte(pan, 24, 20, FD - 48, f"Plantes concernées · {choisies} cochées", 18, gras=True)
    rech = d.cadre("Champ · filtre", pan, 24, 56, FD - 48, 40, fond=FOND, rayon=8, trait=TRAIT)
    d.texte(rech, 12, 10, FD - 72, "Filtrer par nom ou code", 14, couleur=TRAIT)
    y = 112
    n = 0
    lignes_zone = {}
    for zone, plantes in ZONES.items():
        lz = d.cadre(f"Zone · {zone}", pan, 24, y, FD - 48, 32)
        case(lz, 0, 6, choisies >= len(plantes) and zone == "Verger haut")
        d.texte(lz, 32, 6, 400, f"{zone} — toute la zone", 15, gras=True)
        lignes_zone[zone] = lz
        y += 38
        for p in plantes:
            cochee = n < choisies
            case(pan, 56, y + 4, cochee)
            d.texte(pan, 88, y + 4, FD - 120, p, 14, gras=cochee)
            y += 32
            n += 1
        y += 8
    res = d.cadre("Résultat", pan, 24, 480, FD - 48, 130, fond=FOND, rayon=10)
    if choisies:
        d.texte(res, 16, 12, FD - 80, f"{choisies} interventions seront enregistrées, une par "
                "plante (RG-04).", 15, gras=True)
        d.texte(res, 16, 58, FD - 80, "Les 6 échéances de taille se recalculent à partir du "
                "12/02/2026. Celle d'avril 2026, déjà consignée manquée, devient soldée : "
                "l'historique le dira.", 13, couleur=SECOND)
    else:
        d.texte(res, 16, 12, FD - 80, "Cochez une zone entière, ou les plantes une à une.", 15,
                couleur=SECOND)
    valider = d.bouton(f, XR + FD - 280, BH - 112, 280, 56,
                       f"Enregistrer pour {choisies} plantes" if choisies else "Enregistrer",
                       principal=True, inactif=not choisies)
    d.bouton(f, XR + FD - 280 - 152, BH - 112, 140, 56, "Annuler")
    if enregistre:
        voile = d.cadre("Voile", f, 0, 0, BW, BH, fond="#00000066")
        boite = d.cadre("Enregistré", voile, (BW - 560) // 2, 260, 560, 300, fond=FOND, rayon=14)
        d.texte(boite, 32, 32, 496, "✓ 6 interventions enregistrées", 22, gras=True)
        d.texte(boite, 32, 72, 496, "Taille du 12/02/2026, sur les 6 pommiers du Verger haut.",
                15)
        for k, s in enumerate(["6 échéances recalculées : prochaine taille avant le 7 avril 2027",
                               "1 échéance manquée requalifiée en soldée"]):
            d.texte(boite, 32, 112 + k * 28, 496, "• " + s, 14, couleur=SECOND)
        ok = d.bouton(boite, 32, 212, 240, 56, "Revenir à « À faire »", principal=True)
        d.bouton(boite, 288, 212, 240, 56, "Annuler la saisie")
        return f, lignes_zone, valider, ok
    return f, lignes_zone, valider, None


b8a, b8a_zones, _, _ = passee("B8 · 01 · Intervention passée", 0)
b8b, _, b8b_valider, _ = passee("B8 · 02 · Intervention passée — 6 plantes", 6)
b8c, _, _, b8c_ok = passee("B8 · 03 · Intervention passée — enregistrée", 6, enregistre=True)

# --- B9 · Corriger une règle (P7) ------------------------------------------------
b9 = ecran("B9 · 01 · Corriger une règle")
coquille(b9, "Plantes")
d.texte(b9, CX0, 24, 800, "Plantes  ›  Pelouse devant la maison  ›  Règle de tonte", 14,
        couleur=SECOND)
d.texte(b9, CX0, 52, 800, "Corriger la règle de tonte", 32, gras=True)
d.texte(b9, CX0, 98, 800, "Pelouse devant la maison · PEL-001 · copiée de la bibliothèque le "
        "02/05/2026", 15, couleur=SECOND)
d.texte(b9, CX0, 150, FG, "Rythme", 15, gras=True)
for k, (lib, sel) in enumerate([("Tous les N jours", True), ("Dans une fenêtre chaque année",
                                                               False)]):
    r = d.cadre(f"Type · {lib}", b9, CX0 + k * 284, 176, 276, 48, fond=FORT if sel else FOND,
                rayon=8, trait=FORT)
    d.texte(r, 0, 14, 276, lib, 15, gras=True, couleur=BLANC if sel else TEXTE, align="center")
champ(b9, CX0, 248, 200, "Tous les", "10 jours")
d.texte(b9, CX0 + 216, 284, 340, "au lieu de 7 (bibliothèque)", 14, couleur=SECOND)
d.texte(b9, CX0, 352, FG, "Variation selon le mois", 15, gras=True)
d.texte(b9, CX0, 378, FG, "Celle de la bibliothèque, appliquée à vos 10 jours. Voir le détail "
        "mois par mois", 14, couleur=SECOND)
d.texte(b9, CX0, 430, FG, "Pourquoi cette correction ? (facultatif)", 15, gras=True)
motif = d.cadre("Champ · motif", b9, CX0, 456, FG, 72, fond=FOND, rayon=8, trait=TRAIT)
d.texte(motif, 14, 12, FG - 28, "« L'herbe pousse moins vite à l'ombre du noyer. »", 15)
d.texte(b9, CX0, 556, FG, "Appliquer à", 15, gras=True)
for k, (lib, sel) in enumerate([("Cette pelouse seulement", True),
                                ("Mes 3 pelouses", False)]):
    r = d.cadre(f"Portée · {lib}", b9, CX0, 584 + k * 52, FG, 44, fond=FOND, rayon=8,
                trait=FORT if sel else TRAIT)
    d.cadre("Radio", r, 14, 12, 20, 20, fond=FORT if sel else FOND, rayon=10, trait=FORT)
    d.texte(r, 46, 12, FG - 60, lib, 15, gras=sel)
d.texte(b9, CX0, 698, FG, "La bibliothèque n'est pas modifiée : votre version fait foi pour "
        "vos plantes (RG-09).", 13, couleur=SECOND)

pan = d.cadre("Effet de la correction", b9, XR, 150, FD, 420, fond=SURFACE, rayon=12)
d.texte(pan, 24, 20, FD - 48, "Ce que ça change", 18, gras=True)
for k, (lib, val, fort) in enumerate([("Prochaine tonte, avant", "ven. 24 oct.", False),
                                      ("Prochaine tonte, après", "lun. 27 oct.", True)]):
    d.texte(pan, 24, 64 + k * 34, 280, lib, 15, couleur=SECOND)
    d.texte(pan, 300, 64 + k * 34, FD - 324, val, 16, gras=fort)
d.texte(pan, 24, 148, FD - 48, "Recalculée immédiatement, à partir de la dernière tonte "
        "(17 oct.).", 13, couleur=SECOND)
d.texte(pan, 24, 196, FD - 48, "Versions de cette règle", 15, gras=True)
for k, (v, q) in enumerate([("v2 · tous les 10 jours", "aujourd'hui, par vous — en cours"),
                            ("v1 · tous les 7 jours", "02/05/2026 · bibliothèque 2026.1")]):
    d.texte(pan, 24, 228 + k * 50, FD - 48, v, 15, gras=k == 0)
    d.texte(pan, 24, 250 + k * 50, FD - 48, q, 13, couleur=SECOND)
d.texte(pan, 24, 340, FD - 48, "Une version se restaure en un clic ; aucune n'est effacée.", 13,
        couleur=SECOND)
b9_ok = d.bouton(b9, XR + FD - 280, BH - 112, 280, 56, "Enregistrer la correction",
                 principal=True)
b9_annuler = d.bouton(b9, XR + FD - 280 - 152, BH - 112, 140, 56, "Annuler")

# --- Prototype -----------------------------------------------------------------
d.lien(b4_passee, b8a, "dissolve")
d.lien(b4_imprimer, imp, "dissolve")
d.lien(imp_retour, b4, "dissolve")
for b in b4_fait:
    d.lien(b, b4s, "instant")
d.lien(b8a_zones["Verger haut"], b8b, "instant")
d.lien(b8b_valider, b8c, "dissolve")
d.lien(b8c_ok, b4, "dissolve")
d.lien(vague3.b6_saisir, b8a, "dissolve")
d.lien(vague3.b6_btn["Modifier la règle"], b9, "dissolve")
d.lien(b9_ok, vague3.b6, "dissolve")
d.lien(b9_annuler, vague3.b6, "dissolve")
for bouton, nom in vague3.LIENS_NAV[N0:]:
    if nom == "Plantes":
        d.lien(bouton, vague3.b5, "instant")
    elif nom == "À faire":
        d.lien(bouton, b4, "instant")
d.cmd("proto.flow.add", start=b4, name="Vague 4 — le travail au bureau (P3, P5, P7)")

d.noeud("text", x=-680, y=2200, text=(
    "VAGUE 4 — LE TRAVAIL AU BUREAU   [à revoir]\n\n"
    "B4 · À faire (P3) : « 15 prochains jours » groupé par zone, c'est le week-end de travaux. "
    "Chaque ligne porte son explication (RG-06). Imprimer donne une feuille à cocher au stylo ; "
    "« envoyer sur le téléphone » n'a pas d'écran : c'est la même liste, dans « Aujourd'hui ».\n\n"
    "B8 · Intervention passée (P5) : cocher une zone coche toutes ses plantes. Le panneau dit "
    "ce que la saisie va changer avant de la faire. Elle se décompose en une intervention par "
    "plante (RG-04).\n\n"
    "B9 · Corriger une règle (P7) : l'effet est montré avant d'enregistrer ; chaque version est "
    "datée et restaurable (RG-08) ; la bibliothèque n'est jamais touchée (RG-09).\n\n"
    "À trancher par Léon plus tard : une intervention rétroactive qui tombe dans une fenêtre "
    "déjà « manquée » la requalifie en « soldée » (choix fait ici, registre Q-UX)."),
    name="Notes · vague 4", props={
        "width": 560, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 16,
        "fills": [TEXTE]})
