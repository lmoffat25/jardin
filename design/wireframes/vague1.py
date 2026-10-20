#!/usr/bin/env python3
"""Wireframes de la vague 1 (terrain : T1 à T4) et leur prototype.

Ce module porte aussi le socle commun aux vagues (la classe `Doc`, les pages,
les gris, les briques de dessin). Il ne s'exécute pas seul :

    python3 -I design/wireframes/construire.py        écrit design/jardin.figcraft

Le fichier FigCraft est généré, pas dessiné à la main : relancer ce script le
reconstruit à l'identique. `figcraft-cli run` rend des identifiants entiers
attribués dans l'ordre de création (pages comprises) et un script ne peut pas
citer le résultat d'une étape : la classe `Doc` tient donc le compteur.
Données fictives uniquement (conception-ecrans.md §3).
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
from contextlib import contextmanager
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
SORTIE = RACINE / "jardin.figcraft"

# Gris de wireframe : aucune couleur avant validation (M1).
FOND, SURFACE, TRAIT, TEXTE, SECOND, FORT, BLANC = (
    "#FFFFFF", "#F0F0F0", "#BDBDBD", "#1A1A1A", "#616161", "#333333", "#FFFFFF")
W, H, M = 390, 844, 16

# Deux passes (conception-ecrans.md §1) : la première dessine les wireframes en
# gris sur les pages 3 et 4 ; la seconde (JARDIN_PASSE=interface) rejoue les
# mêmes écrans sur les pages 7 et 8, chaque gris lié à un rôle sémantique (M4),
# chaque texte à un style, chaque bouton instance du composant. La mise en page
# n'est écrite qu'une fois.
INTERFACE = os.environ.get("JARDIN_PASSE") == "interface"

# Le rôle de chaque gris, selon ce qu'il colore. Un gris absent de la table
# arrête la construction : une valeur en dur ne passe pas (M6).
ROLES = {
    "text": {TEXTE: "texte/principal", FORT: "texte/principal", SECOND: "texte/secondaire",
             TRAIT: "texte/desactive", BLANC: "texte/inverse"},
    "fill": {FOND: "fond/surface", SURFACE: "fond/sourdine", TRAIT: "bordure/defaut",
             "#DEDEDE": "fond/carte", "#00000066": "fond/voile", FORT: "action/principale", TEXTE: "texte/principal",
             SECOND: "texte/secondaire"},
    "stroke": {TRAIT: "bordure/defaut", FORT: "bordure/forte", TEXTE: "bordure/forte",
               SECOND: "bordure/forte", SURFACE: "bordure/discrete", FOND: "fond/surface"},
}


def style_texte(taille: float, gras: bool) -> str:
    """Le style de texte le plus proche d'une taille de wireframe (fondations.STYLES)."""
    if taille <= 12:
        return "Légende forte" if gras else "Légende"
    if taille <= 14:
        return "Petit fort" if gras else "Petit"
    if taille <= 17:
        return "Corps fort" if gras else "Corps"
    if taille <= 20:
        return "Titre 3" if gras else "Corps grand"
    if taille <= 26:
        return "Titre 2"
    return "Titre 1" if taille <= 34 else "Chiffre"


class Doc:
    def __init__(self) -> None:
        self.cmds: list[dict] = []
        self.suivant = int(os.environ.get("JARDIN_DEPART", 2))   # la page 1 existe déjà
        self.surcharges: list[dict] = []
        self.composants: dict[str, dict] = {}   # posés par fondations.poser (passe interface)

    def cmd(self, command: str, **params) -> None:
        if INTERFACE and command == "proto.flow.add":
            params["name"] = "Interface · " + params["name"]
        self.cmds.append({"command": command, "params": params})

    # --- Passe interface -------------------------------------------------------
    @contextmanager
    def surcharge(self, roles: dict):
        """Change le rôle de certains gris le temps d'un bloc : {(sorte, gris): rôle}."""
        self.surcharges.append(roles)
        try:
            yield
        finally:
            self.surcharges.pop()

    def etat(self, nom: str, fond: bool = True):
        """Ce bloc dit un état (« en-retard », « a-faire »…) : FORT et TEXTE en prennent la
        couleur, FOND sa teinte claire. L'état reste aussi écrit en toutes lettres (charte §5)."""
        r = {("fill", FORT): f"etat/{nom}", ("stroke", FORT): f"etat/{nom}",
             ("text", FORT): f"etat/{nom}", ("text", TEXTE): f"etat/{nom}"}
        if fond:
            r[("fill", FOND)] = f"etat/{nom}-fond"
        return self.surcharge(r)

    def role(self, sorte: str, gris: str) -> str:
        for s in reversed(self.surcharges):
            if (sorte, gris) in s:
                return s[(sorte, gris)]
        try:
            return ROLES[sorte][gris]
        except KeyError:
            raise SystemExit(f"ECHOUE gris sans rôle sémantique : {sorte} {gris} (M6)")

    def lier(self, i: int, champ: str, variable: str) -> None:
        self.cmd("variable.bind", ids=[i], field=champ, variable=variable)

    def _habiller(self, i: int, type_: str, props: dict, roles: dict) -> None:
        if type_ == "text":
            self.cmd("style.apply", ids=[i], style=roles.get("style") or style_texte(
                props.get("fontSize", 16), props.get("fontStyle") == "Bold"))
        sorte = "text" if type_ == "text" else "fill"
        fills = props.get("fills") or []
        if "fill" in roles:
            self.lier(i, "fill", roles["fill"])
        elif fills:
            if len(fills) != 1 or not isinstance(fills[0], str):
                raise SystemExit(f"ECHOUE remplissage composé, à lier à la main : {fills}")
            self.lier(i, "fill", self.role(sorte, fills[0]))
        if "stroke" in roles:
            self.lier(i, "stroke", roles["stroke"])
        elif props.get("stroke"):
            self.lier(i, "stroke", self.role("stroke", props["stroke"]))

    def instance(self, nom: str, parent, x=0, y=0, w=None, h=None, proprietes=None) -> int:
        c = self.composants[nom]
        kw = {"parent": parent} if parent else {}
        self.cmd("instance.create", component=c["id"], x=x, y=y, **kw)
        i = self._id()
        self.suivant += c["calques"]          # chaque calque de l'instance prend un identifiant
        if proprietes:
            self.cmd("instance.setProperties", ids=[i], properties=proprietes)
        if w is not None:
            self.cmd("node.set", ids=[i], width=w, height=h, name=f"{nom} · {proprietes}")
        return i

    def densite(self, f: int, mode: str) -> None:
        if INTERFACE:
            self.cmd("variable.setMode", ids=[f], collection="Densité", mode=mode)

    def _id(self) -> int:
        i = self.suivant
        self.suivant += 1
        return i

    def page(self, nom: str) -> int:
        self.cmd("page.add", name=nom)
        return self._id()

    def noeud(self, type_: str, roles: dict | None = None, **params) -> int:
        self.cmd("node.create", type=type_, select=False, **params)
        i = self._id()
        if INTERFACE:
            self._habiller(i, type_, params.get("props", {}), roles or {})
        return i

    def cadre(self, nom, parent, x, y, w, h, fond=None, rayon=0, trait=None, pointille=False):
        props = {"fills": [fond] if fond else [], "clip": True}
        if rayon:
            props["cornerRadius"] = rayon
        if trait:
            props.update(stroke=trait, strokeWeight=2 if pointille else 1.5, strokeAlign="inside")
            if pointille:
                props["dashes"] = [6, 4]
        kw = {"parent": parent} if parent else {}
        return self.noeud("frame", name=nom, x=x, y=y, width=w, height=h, props=props, **kw)

    def texte(self, parent, x, y, w, s, taille=16, gras=False, couleur=TEXTE, align="left"):
        return self.noeud("text", parent=parent, x=x, y=y, text=s, name=s[:40], props={
            "width": w, "textAutoResize": "height", "fontFamily": "Inter",
            "fontStyle": "Bold" if gras else "Regular", "fontSize": taille,
            "fills": [couleur], "textAlign": align})

    def bouton(self, parent, x, y, w, h, libelle, principal=False, inactif=False):
        if INTERFACE:
            variante = "inactif" if inactif else ("principale" if principal else "secondaire")
            return self.instance("Bouton", parent, x, y, w, h,
                                 {"variante": variante, "libelle": libelle})
        fond = FORT if principal and not inactif else (SURFACE if inactif else FOND)
        b = self.cadre(f"Bouton · {libelle}", parent, x, y, w, h, fond=fond, rayon=10,
                       trait=None if principal else TRAIT)
        couleur = BLANC if principal and not inactif else (TRAIT if inactif else TEXTE)
        self.texte(b, 8, (h - 22) / 2, w - 16, libelle, 17, gras=True, couleur=couleur, align="center")
        return b

    def logo(self, parent, x, y):
        """Une seule marque (§2) : le composant Marque/Logo à l'interface."""
        if INTERFACE:
            return self.instance("Marque/Logo", parent, x, y)
        logo = self.cadre("Marque/Logo (provisoire)", parent, x, y, 112, 32, rayon=6, trait=TRAIT,
                          pointille=True)
        self.texte(logo, 0, 7, 112, "Logo", 13, couleur=SECOND, align="center")
        return logo

    def lien(self, source, dest, transition="push"):
        self.cmd("proto.interaction.add", id=source, trigger={"type": "onClick"},
                 action={"type": "navigate", "dest": dest},
                 transition={"type": transition, "direction": "left"} if transition != "instant"
                 else {"type": "instant"}, durationMs=220)

    def retour(self, source):
        self.cmd("proto.interaction.add", id=source, trigger={"type": "onClick"},
                 action={"type": "back"}, transition={"type": "push", "direction": "right"},
                 durationMs=220)

    def selon_reseau(self, source, si_oui, si_non):
        """Valider : enregistré si le réseau passe, sinon « non envoyé » (RG-50)."""
        self.cmd("proto.interaction.add", id=source, trigger={"type": "onClick"}, action={
            "type": "conditional", "blocks": [
                {"expression": "reseau", "actions": [{"type": "navigate", "dest": si_oui}]},
                {"actions": [{"type": "navigate", "dest": si_non}]}]},
            transition={"type": "dissolve"}, durationMs=180)


d = Doc()

# --- Pages (conception-ecrans.md §3) -------------------------------------------
PAGES = ["1 · Charte", "2 · Parcours", "3 · Wireframes terrain", "4 · Wireframes bureau",
         "5 · Fondations", "6 · Composants", "7 · Écrans terrain", "8 · Écrans bureau", "Archive"]
if not INTERFACE:
    d.cmd("page.rename", name="0 · Mode d'emploi")
    ids_pages = {nom: d.page(nom) for nom in PAGES}

    # Variables du prototype : l'état du réseau, pour essayer les deux suites de RG-50.
    d.cmd("variable.createCollection", name="Prototype")
    d.cmd("variable.create", collection="Prototype", name="reseau", type="boolean", value=True)
    d.cmd("variable.create", collection="Prototype", name="etat_reseau", type="string",
          value="Essai : réseau ✓")
else:
    # Les pages existent depuis la première passe ; les vagues qui visent les pages
    # de wireframes dessinent ici sur celles de l'interface.
    ids_pages = {nom: 2 + k for k, nom in enumerate(PAGES)}
    ids_pages["3 · Wireframes terrain"] = ids_pages["7 · Écrans terrain"]
    ids_pages["4 · Wireframes bureau"] = ids_pages["8 · Écrans bureau"]
    sys.path.insert(0, str(RACINE / "interface"))
    import fondations  # noqa: E402
    fondations.poser(d, ids_pages)

# Le contenu de la page 0 se pose à la fin : `node.create` sans parent vise la
# page courante, et `page.add` vient d'en changer. On revient sur la page 3.
d.cmd("page.select", id=ids_pages["3 · Wireframes terrain"])

ECRANS: dict[str, int] = {}
X = [0]


def ecran(nom: str, hauteur: int = H) -> int:
    with d.surcharge({("fill", FOND): "fond/page"}):
        f = d.cadre(nom, None, X[0], 120, W, hauteur, fond=FOND)
    d.densite(f, "Terrain")
    X[0] += W + 80
    ECRANS[nom] = f
    d.cadre("Barre d'état", f, 0, 0, W, 44, fond=SURFACE)
    return f


def pastille_essai(f, x=246, y=6):
    """Outil d'essai, pas un élément de l'écran : bascule le réseau simulé."""
    p = d.cadre("Essai · réseau (outil, hors écran)", f, x - 12, y, 144, 32, fond=FOND, rayon=16,
                trait=SECOND, pointille=True)
    t = d.texte(p, 4, 8, 136, "Essai : réseau ✓", 12, couleur=SECOND, align="center")
    d.cmd("variable.bind", ids=[t], field="characters", variable="etat_reseau")
    d.cmd("proto.interaction.add", id=p, trigger={"type": "onClick"}, action={
        "type": "conditional", "blocks": [
            {"expression": "reseau", "actions": [
                {"type": "setVariable", "variable": "reseau", "value": False},
                {"type": "setVariable", "variable": "etat_reseau", "value": "Essai : réseau ✗"}]},
            {"actions": [
                {"type": "setVariable", "variable": "reseau", "value": True},
                {"type": "setVariable", "variable": "etat_reseau", "value": "Essai : réseau ✓"}]}]})


LIGNES = [  # zone, plante, intervention, état, en retard ?
    ("Verger haut", "Prunier n°3", "Traitement cuivre", "urgent · avant le 24 oct.", True),
    ("Verger bas", "Poirier 'Conférence'", "Traitement cuivre", "à faire jusqu'au 15 nov.", False),
    ("Pelouse", "Pelouse devant la maison", "Tonte", "à faire d'ici ven. 24 oct.", False),
    ("Haies", "Haie de charmilles — côté route", "Taille", "à faire jusqu'au 31 mars", False),
]
TYPES_RECENTS = ["Taille", "Tonte", "Traitement cuivre", "Arrosage", "Récolte", "Autre…"]


BOUTONS_CARTE: list[int] = []


def entete_aujourdhui(f, sous_titre):
    d.texte(f, M, 56, 230, "Aujourd'hui", 28, gras=True)
    d.texte(f, M, 94, 250, sous_titre, 16, couleur=SECOND)
    # Accès à la carte terrain (T7) : un bouton, pas un glissé (décision du 19/10/2026).
    BOUTONS_CARTE.append(d.bouton(f, W - M - 104, 60, 104, 48, "Carte"))


def boite(nom, parent, sens, padding=0, gap=0, fond=None, trait_bas=False, **pos):
    """Cadre en mise en page automatique : sa hauteur suit son contenu."""
    props = {"fills": [fond] if fond else [], "layout": sens, "padding": padding, "gap": gap,
             "sizingV": "hug"}
    if trait_bas:
        props.update(stroke=SURFACE, strokeBottomWeight=1)
    if "x" not in pos:
        props["sizingH"] = "fill"
    if sens == "horizontal":
        props["crossAlign"] = "center"
    return d.noeud("frame", name=nom, parent=parent, props=props, **pos)


def texte_flux(parent, s, taille=16, gras=False, couleur=TEXTE):
    """Texte enfant d'une mise en page automatique : prend la largeur, passe à la ligne."""
    return d.noeud("text", parent=parent, text=s, name=s[:40], props={
        "textAutoResize": "height", "sizingH": "fill", "fontFamily": "Inter",
        "fontStyle": "Bold" if gras else "Regular", "fontSize": taille, "fills": [couleur]})


RANGS_ECHEANCE: list[int] = []   # chaque ligne ouvre la fiche plante (T5, vague 3)


def liste_du_jour(f, y, lignes, boutons_fait: dict):
    liste = boite("Liste du jour", f, "vertical", x=0, y=y, width=W)
    zone = None
    for z, plante, inter, etat, retard in lignes:
        if z != zone:
            entete = boite(f"Zone · {z}", liste, "vertical", padding=[14, M, 2, M])
            texte_flux(entete, z.upper(), 13, gras=True, couleur=SECOND)
            zone = z
        ligne = boite(f"Échéance · {plante}", liste, "horizontal", padding=[12, M, 12, M],
                      gap=12, trait_bas=True)
        col = boite("Texte", ligne, "vertical", gap=2)
        texte_flux(col, plante, 17, gras=True)
        marque = "⚠ " if retard else ""
        with d.etat("en-retard"):
            texte_flux(col, f"{inter} · {marque}{etat}", 15, gras=retard,
                       couleur=TEXTE if retard else SECOND)
        boutons_fait[plante] = d.bouton(ligne, 0, 0, 88, 48, "Fait")
        RANGS_ECHEANCE.append(ligne)
    return liste


def tiroir_je_viens_de_faire(f):
    t = d.cadre("Je viens de faire", f, 0, 588, W, H - 588, fond=SURFACE, rayon=16)
    d.texte(t, M, 16, W - 2 * M, "Je viens de faire…", 17, gras=True)
    puces = {}
    for i, nom in enumerate(TYPES_RECENTS):
        col, rang = i % 2, i // 2
        puces[nom] = d.bouton(t, M + col * 183, 52 + rang * 58, 175, 50, nom)
    d.texte(t, M, 232, W - 2 * M, "Les 5 derniers types utilisés", 13, couleur=SECOND)
    return puces


# --- T1 · Aujourd'hui ----------------------------------------------------------
t1 = ecran("T1 · 01 · Aujourd'hui")
entete_aujourdhui(t1, "mar. 21 oct. · 4 à faire")
pastille_essai(t1)
fait_t1: dict = {}
liste_du_jour(t1, 130, LIGNES, fait_t1)
puces_t1 = tiroir_je_viens_de_faire(t1)

# Confirmation « fait » (superposition, P2 : une seule confirmation).
conf = ecran("T1 · 02 · Confirmer « fait »", 300)
d.texte(conf, M, 60, W - 2 * M, "Traitement cuivre", 22, gras=True)
d.texte(conf, M, 92, W - 2 * M, "Prunier n°3 · fait aujourd'hui, mar. 21 oct.", 16, couleur=SECOND)
ok_conf = d.bouton(conf, M, 140, W - 2 * M, 56, "C'est fait", principal=True)
annuler_conf = d.bouton(conf, M, 208, W - 2 * M, 56, "Annuler")

# T1 après enregistrement.
t1ok = ecran("T1 · 03 · Aujourd'hui — enregistré")
entete_aujourdhui(t1ok, "mar. 21 oct. · 3 à faire")
pastille_essai(t1ok)
bandeau = d.cadre("Bandeau · enregistré", t1ok, M, 128, W - 2 * M, 64, fond=SURFACE, rayon=10)
d.texte(bandeau, 14, 10, 220, "✓ Enregistré", 16, gras=True)
d.texte(bandeau, 14, 34, 230, "Taille · Pommier 'Reine des reinettes'", 13, couleur=SECOND)
annuler_ok = d.bouton(bandeau, W - 2 * M - 104, 8, 96, 48, "Annuler")
fait_t1ok: dict = {}
liste_du_jour(t1ok, 204, LIGNES[1:], fait_t1ok)
puces_t1ok = tiroir_je_viens_de_faire(t1ok)

# --- T3 · Choisir les plantes --------------------------------------------------
PROCHES = [("Pommier 'Reine des reinettes'", "8 m · Verger haut"),
           ("Pommier 'Belle de Boskoop'", "11 m · Verger haut"),
           ("Prunier n°3", "15 m · Verger haut"),
           ("Cerisier 'Burlat'", "22 m · Verger bas"),
           ("Haie de charmilles — côté route", "30 m · Haies"),
           ("Rosier 'Pierre de Ronsard'", "41 m · Massif d'entrée")]


def choisir_plantes(nom, coches: int, titre="Taille", sous="Près de vous", liste=PROCHES):
    f = ecran(nom)
    retour = d.bouton(f, 8, 52, 48, 48, "‹")
    d.texte(f, 64, 56, 180, titre, 24, gras=True)
    pastille_essai(f)
    d.texte(f, 64, 88, 250, "Quelles plantes ?", 15, couleur=SECOND)
    champ = d.cadre("Champ · recherche", f, M, 124, W - 2 * M, 48, fond=FOND, rayon=10, trait=TRAIT)
    d.texte(champ, 14, 14, 300, "Rechercher une plante ou un code", 15, couleur=TRAIT)
    d.texte(f, M, 188, W - 2 * M, sous.upper(), 13, gras=True, couleur=SECOND)
    rangs = []
    liste_p = boite("Plantes", f, "vertical", x=0, y=208, width=W)
    for i, (plante, ou) in enumerate(liste):
        r = boite(f"Plante · {plante}", liste_p, "horizontal", padding=[10, M, 10, M], gap=14)
        case = d.cadre("Case", r, 0, 0, 28, 28, fond=FORT if i < coches else FOND, rayon=6, trait=FORT)
        if i < coches:
            d.texte(case, 0, 3, 28, "✓", 17, gras=True, couleur=BLANC, align="center")
        col = boite("Texte", r, "vertical", gap=2)
        texte_flux(col, plante, 17, gras=True)
        texte_flux(col, ou, 14, couleur=SECOND)
        rangs.append(r)
    # Champs facultatifs, en bas, jamais obligatoires (CDC §2.2).
    for j, (lib, w) in enumerate([("Maintenant", 128), ("+ Photo", 104), ("+ Note", 94)]):
        d.bouton(f, M + [0, 136, 248][j], 652, w, 48, lib)
    libelle = f"Valider · {coches} plante" + ("s" if coches > 1 else "") if coches else "Choisissez une plante"
    valider = d.bouton(f, M, 716, W - 2 * M, 60, libelle, principal=True, inactif=not coches)
    return f, retour, rangs, valider


t3, t3_retour, t3_rangs, _ = choisir_plantes("T3 · 01 · Choisir les plantes", 0)
t3b, t3b_retour, t3b_rangs, t3b_valider = choisir_plantes("T3 · 02 · Choisir les plantes — 1 choisie", 1)
t3c, t3c_retour, t3c_rangs, t3c_valider = choisir_plantes("T3 · 03 · Choisir les plantes — 2 choisies", 2)

# --- T4 · Non envoyé (RG-50) ---------------------------------------------------
t4 = ecran("T4 · 01 · Aujourd'hui — non envoyé")
entete_aujourdhui(t4, "mar. 21 oct. · 4 à faire")
pastille_essai(t4)
with d.etat("non-envoye"):
    alerte = d.cadre("Bandeau · non envoyé", t4, M, 128, W - 2 * M, 140, fond=FOND, rayon=10,
                     trait=FORT)
    d.texte(alerte, 14, 12, W - 2 * M - 28, "⚠ Pas envoyé — pas de réseau", 16, gras=True)
d.texte(alerte, 14, 38, W - 2 * M - 28, "Taille · Pommier 'Reine des reinettes'", 13,
        couleur=SECOND)
d.texte(alerte, 14, 62, W - 2 * M - 28, "14:32 · gardé sur ce téléphone.", 13, couleur=SECOND)
renvoyer = d.bouton(alerte, W - 2 * M - 126, 80, 116, 48, "Renvoyer", principal=True)
fait_t4: dict = {}
liste_du_jour(t4, 284, LIGNES[:3], fait_t4)
tiroir_je_viens_de_faire(t4)

# --- T2 · Tous les types ------------------------------------------------------
t2 = ecran("T2 · 01 · Autre type")
t2_retour = d.bouton(t2, 8, 52, 48, 48, "‹")
d.texte(t2, 64, 60, 250, "Quel type ?", 24, gras=True)
TOUS = ["Arrosage", "Désherbage", "Engrais", "Huile blanche", "Paillage", "Plantation",
        "Récolte", "Taille", "Tonte", "Traitement cuivre", "Ajouter un type…"]
t2_rangs = []
for i, nom in enumerate(TOUS):
    r = d.cadre(f"Type · {nom}", t2, 0, 116 + i * 60, W, 60, fond=FOND)
    d.texte(r, M, 18, 300, nom, 17, gras=nom != TOUS[-1], couleur=TEXTE if nom != TOUS[-1] else SECOND)
    d.cadre("Séparateur", r, M, 59, W - 2 * M, 1, fond=SURFACE)
    t2_rangs.append(r)

# --- États sans prototype (M8) ------------------------------------------------
vide = ecran("T1 · 04 · Aujourd'hui — rien à faire")
entete_aujourdhui(vide, "mar. 21 oct.")
d.texte(vide, M, 180, W - 2 * M, "Rien à faire aujourd'hui.", 22, gras=True, align="center")
d.texte(vide, M, 216, W - 2 * M, "Prochaine échéance : jeu. 23 oct.\nTonte · Pelouse devant la maison",
        16, couleur=SECOND, align="center")
tiroir_je_viens_de_faire(vide)

premier = ecran("T1 · 05 · Aujourd'hui — premier jour")
entete_aujourdhui(premier, "mar. 21 oct.")
d.texte(premier, M, 180, W - 2 * M, "Aucune plante pour l'instant.", 22, gras=True, align="center")
d.texte(premier, M, 216, W - 2 * M,
        "L'inventaire se fait depuis l'ordinateur. Chaque plante saisie donne déjà ses échéances.",
        16, couleur=SECOND, align="center")

choisir_plantes("T3 · 04 · Choisir les plantes — sans position", 0, sous="De A à Z",
                liste=[(p, z.split(" · ")[1]) for p, z in sorted(PROCHES)])

charge = ecran("T1 · 06 · Aujourd'hui — chargé (extrait)", 1500)
entete_aujourdhui(charge, "mar. 21 oct. · 17 à faire")
BEAUCOUP = [(z, f"{p} n°{i}", inter, "en retard de 2 jours" if i % 5 == 0 else etat, i % 5 == 0) for i, (z, p, inter, etat) in enumerate(
    [("Verger haut", "Pommier", "Traitement cuivre", "à faire jusqu'au 15 nov.")] * 6
    + [("Verger bas", "Poirier 'Conférence'", "Traitement cuivre", "à faire jusqu'au 15 nov.")] * 5
    + [("Haies", "Haie de charmilles — tronçon", "Taille", "à faire jusqu'au 31 mars")] * 4
    + [("Pelouse", "Pelouse", "Tonte", "à faire aujourd'hui")] * 2, start=1)]
fait_charge: dict = {}
liste_du_jour(charge, 130, BEAUCOUP, fait_charge)

# Le cadre de T7 naît ici pour que le bouton Carte y aboutisse ; vague6.py le remplit.
t7 = ecran("T7 · 01 · Carte terrain")

# --- Prototype -----------------------------------------------------------------
# P2 : « Fait » → confirmation → enregistré (ou non envoyé).
for b in list(fait_t1.values()) + list(fait_t1ok.values()) + list(fait_t4.values()):
    d.cmd("proto.interaction.add", id=b, trigger={"type": "onClick"}, action={
        "type": "openOverlay", "dest": conf, "position": "bottomCenter",
        "close_on_outside": True, "background": "#00000066"},
        transition={"type": "moveIn", "direction": "top"}, durationMs=200)
d.selon_reseau(ok_conf, t1ok, t4)
d.cmd("proto.interaction.add", id=annuler_conf, trigger={"type": "onClick"},
      action={"type": "closeOverlay"})
# P1 : type → plantes → valider. Trois gestes.
for puces in (puces_t1, puces_t1ok):
    for nom, b in puces.items():
        d.lien(b, t2 if nom == "Autre…" else t3)
for r in t2_rangs[:-1]:
    d.lien(r, t3)
d.retour(t2_retour)
for retour in (t3_retour, t3b_retour, t3c_retour):
    d.retour(retour)
d.lien(t3_rangs[0], t3b, "instant")
d.lien(t3b_rangs[1], t3c, "instant")
d.selon_reseau(t3b_valider, t1ok, t4)
d.selon_reseau(t3c_valider, t1ok, t4)
d.selon_reseau(renvoyer, t1ok, t4)
d.lien(annuler_ok, t1, "dissolve")
for b in BOUTONS_CARTE:
    d.lien(b, t7)
d.cmd("proto.flow.add", start=t1, name="Vague 1 — terrain (P1 et P2)")
d.cmd("proto.settings", device="Custom size", width=W, height=H)

# Notes de la vague, à gauche des écrans.
note = d.noeud("text", x=-560, y=120, text=(
    "VAGUE 1 — TERRAIN   [à valider]\n\n"
    "P1 · Enregistrer : un appui sur un type (« Je viens de faire… »), un appui sur la "
    "plante, Valider. 3 gestes. Les 5 derniers types sont sur l'écran d'accueil : c'est ce qui "
    "permet de tenir les 3 gestes sans écran de choix du type.\n\n"
    "P2 · Solder : « Fait » sur la ligne, « C'est fait » dans la confirmation. 1 geste + 1 "
    "confirmation.\n\n"
    "RG-50 : la pastille en pointillés « Essai : réseau » est un outil d'essai, pas un élément "
    "de l'écran. Touchez-la pour couper le réseau simulé, puis validez : la saisie arrive en "
    "« Pas envoyé », et « Renvoyer » la fait partir.\n\n"
    "Carte : un bouton « Carte » dans l'en-tête, pas un glissé (invisible, en conflit avec le "
    "déplacement de la carte et le retour de Safari, exclu seul par WCAG 2.5.1 et 2.5.7). "
    "T7 : vague 6.\n\n"
    "États sans prototype : rien à faire, premier jour, sans position, chargé.\n\n"
    "Données fictives."), name="Notes · vague 1", props={
        "width": 480, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 16,
        "fills": [TEXTE]})

if not INTERFACE:   # le mode d'emploi n'est posé qu'une fois
    d.cmd("page.select", id=1)
    d.noeud("text", x=0, y=0, text=(
        "MAQUETTE — SERVICE DE GESTION DE JARDIN\n\n"
        "Méthode : docs/conception-ecrans.md. Ce fichier est généré par "
        "design/wireframes/*.py : ne pas le modifier à la main tant que les wireframes ne sont pas "
        "validés.\n\n"
        "Statuts : le nom de la section porte [à valider] ou [validé JJ/MM].\n"
        "Cadres : <écran> · <rang> · <nom> — <état>.\n"
        "Wireframes : gris, sans couleur ni police définitive (M1)."), name="Mode d'emploi",
        props={"width": 640, "textAutoResize": "height", "fontFamily": "Inter", "fontSize": 18,
               "fills": [TEXTE]})


def main() -> int:
    script = SORTIE.with_suffix(".jsonl")
    script.write_text("\n".join(json.dumps(c, ensure_ascii=False) for c in d.cmds) + "\n")
    env = dict(os.environ, FIGCRAFT_NO_SYSTEM_FONTS="1")
    entree = ["--in", str(SORTIE)] if INTERFACE else []
    r = subprocess.run(["figcraft-cli", "run", str(script), *entree, "--out", str(SORTIE)],
                       capture_output=True, text=True, env=env)
    script.unlink()
    erreurs = [l for l in r.stdout.splitlines() if '"ok":false' in l]
    if r.returncode or erreurs:
        print("ECHOUE", *(erreurs or [r.stderr.strip()]), sep="\n")
        return 1
    passe = "interface" if INTERFACE else "wireframes"
    print(f"CONFIRME {SORTIE} · {passe} ({len(d.cmds)} commandes, {len(ECRANS)} écrans)",
          flush=True)
    if INTERFACE:   # M6 : aucune valeur en dur dans ce que la passe a posé ; M5 : export CSS
        inter = RACINE / "interface"
        code = subprocess.run([sys.executable, "-I", str(inter / "controle.py"), str(SORTIE),
                               os.environ["JARDIN_DEPART"], str(d.suivant)]).returncode
        return code or subprocess.run([sys.executable, "-I", str(inter / "exporter_css.py"),
                                       str(SORTIE), str(inter / "variables.css")]).returncode
    return 0

