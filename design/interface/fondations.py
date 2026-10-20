"""Fondations de l'interface : variables en trois couches, styles de texte, composants.

Appelé par la passe interface de `design/wireframes/construire.py`, avant les
écrans (conception-ecrans.md §2). La marque provisoire se change ici, dans
PRIMITIVES et MARQUE, et nulle part ailleurs (M3) : la couche sémantique et la
densité n'y portent que des alias.

Contrastes vérifiés au 19/10/2026 (WCAG 2.2) : chaque couple texte / fond de
SEMANTIQUE tient 7:1 (terrain, plein soleil) ; bordure/defaut tient 3:1 sur
blanc (contour d'un champ, WCAG 1.4.11). Les recalculer après tout changement
de PRIMITIVES : `python3 -I design/interface/fondations.py`.
"""
from __future__ import annotations

# --- Primitives : la marque, et rien d'autre (M3) ------------------------------
PRIMITIVES_COULEURS = {
    "neutre/0": "#FFFFFF", "neutre/50": "#F6F7F4", "neutre/100": "#ECEEE9",
    "neutre/300": "#C9CEC6", "neutre/400": "#8C948A", "neutre/500": "#767E74",
    "neutre/700": "#434A41", "neutre/900": "#1B201A",
    "vert/100": "#E3EFE5", "vert/700": "#1E5631",
    "rouge/100": "#FBE7E4", "rouge/700": "#8A1810",
    "ambre/100": "#FCEFD6", "ambre/700": "#663E00",
    "orange/100": "#FDEADB", "orange/700": "#743100",
    "bleu/700": "#1F4FA8", "noir/40": "#00000066",
}
TAILLES = [12, 13, 14, 15, 17, 18, 20, 24, 28, 36, 40]
RAYONS = [4, 6, 10, 13]
MARQUE = {"police/base": "Inter", "marque/nom": "Nom du produit"}

# --- Sémantique : des rôles, chacun alias d'une primitive (M4) -----------------
SEMANTIQUE_COULEURS = {
    "fond/page": "neutre/50", "fond/surface": "neutre/0", "fond/sourdine": "neutre/100",
    "fond/carte": "neutre/100", "fond/voile": "noir/40", "fond/appareil-photo": "neutre/900",
    "texte/principal": "neutre/900", "texte/secondaire": "neutre/700",
    "texte/desactive": "neutre/500", "texte/inverse": "neutre/0",
    "action/principale": "vert/700", "action/principale-texte": "neutre/0",
    "bordure/defaut": "neutre/400", "bordure/discrete": "neutre/100",
    "bordure/forte": "neutre/900", "focus": "bleu/700",
    "etat/en-retard": "rouge/700", "etat/en-retard-fond": "rouge/100",
    "etat/a-faire": "ambre/700", "etat/a-faire-fond": "ambre/100",
    "etat/fait": "vert/700", "etat/fait-fond": "vert/100",
    "etat/hors-saison": "neutre/700", "etat/hors-saison-fond": "neutre/100",
    "etat/non-envoye": "orange/700", "etat/non-envoye-fond": "orange/100",
    "etat/erreur": "rouge/700", "etat/erreur-fond": "rouge/100",
    "etat/attention": "ambre/700", "etat/attention-fond": "ambre/100",
}
SEMANTIQUE_AUTRES = {   # nom: (type, primitive)
    "typo/famille": ("string", "police/base"), "texte/nom-produit": ("string", "marque/nom"),
    "forme/rayon-controle": ("number", "rayon/10"), "forme/rayon-case": ("number", "rayon/4"),
    "forme/rayon-marque": ("number", "rayon/6"), "forme/rayon-pastille": ("number", "rayon/13"),
}
# Les couples à 7:1 (texte sur son fond), et les contours à 3:1.
CONTRASTES_7 = [("texte/principal", "fond/page"), ("texte/secondaire", "fond/page"),
                ("texte/secondaire", "fond/sourdine"), ("action/principale-texte", "action/principale"),
                ("texte/inverse", "etat/en-retard")] + [
    (f"etat/{e}", f"etat/{e}-fond") for e in ("en-retard", "a-faire", "fait", "hors-saison",
                                              "non-envoye", "erreur", "attention")] + [
    (f"etat/{e}", "fond/surface") for e in ("en-retard", "a-faire", "fait", "non-envoye")]
CONTRASTES_3 = [("bordure/defaut", "fond/surface"), ("focus", "fond/surface")]

# --- Densité : deux modes, Bureau et Terrain (A23) -----------------------------
DENSITE = {   # nom: (Bureau, Terrain), en primitives taille/N et cible/N
    "texte/legende": (12, 13), "texte/petit": (14, 15), "texte/corps": (15, 17),
    "texte/grand": (18, 20), "titre/3": (18, 20), "titre/2": (24, 24), "titre/1": (28, 28),
    "texte/chiffre": (36, 40),
}
CIBLE = (24, 48)

# --- Styles de texte : police et taille liées aux variables --------------------
STYLES = {   # nom: (graisse, variable de taille)
    "Titre 1": ("Bold", "titre/1"), "Titre 2": ("Bold", "titre/2"), "Titre 3": ("Bold", "titre/3"),
    "Corps grand": ("Regular", "texte/grand"), "Corps": ("Regular", "texte/corps"),
    "Corps fort": ("Bold", "texte/corps"), "Petit": ("Regular", "texte/petit"),
    "Petit fort": ("Bold", "texte/petit"), "Légende": ("Regular", "texte/legende"),
    "Légende forte": ("Bold", "texte/legende"), "Chiffre": ("Bold", "texte/chiffre"),
}


def _luminance(h: str) -> float:
    c = [int(h[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    c = [x / 12.92 if x <= 0.04045 else ((x + 0.055) / 1.055) ** 2.4 for x in c]
    return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]


def contraste(a: str, b: str) -> float:
    la, lb = sorted((_luminance(a), _luminance(b)), reverse=True)
    return (la + .05) / (lb + .05)


def verifier_contrastes() -> list[str]:
    val = {r: PRIMITIVES_COULEURS[p] for r, p in SEMANTIQUE_COULEURS.items()}
    fautes = [f"{t} sur {f} : {contraste(val[t], val[f]):.2f} < {s}"
              for couples, s in ((CONTRASTES_7, 7), (CONTRASTES_3, 3))
              for t, f in couples if contraste(val[t], val[f]) < s]
    return fautes


def poser(d, pages: dict) -> None:
    """Crée variables, styles et composants dans le document `d` (classe vague1.Doc)."""
    fautes = verifier_contrastes()
    if fautes:
        raise SystemExit("ECHOUE contrastes insuffisants :\n" + "\n".join(fautes))

    d.cmd("variable.createCollection", name="Primitives")
    for nom, v in PRIMITIVES_COULEURS.items():
        d.cmd("variable.create", collection="Primitives", name=nom, type="color", value=v)
    for t in TAILLES:
        d.cmd("variable.create", collection="Primitives", name=f"taille/{t}", type="number", value=t)
    for r in RAYONS:
        d.cmd("variable.create", collection="Primitives", name=f"rayon/{r}", type="number", value=r)
    for c in CIBLE:
        d.cmd("variable.create", collection="Primitives", name=f"cible/{c}", type="number", value=c)
    for nom, v in MARQUE.items():
        d.cmd("variable.create", collection="Primitives", name=nom, type="string", value=v)

    d.cmd("variable.createCollection", name="Sémantique")
    for nom, prim in SEMANTIQUE_COULEURS.items():
        d.cmd("variable.create", collection="Sémantique", name=nom, type="color",
              value=PRIMITIVES_COULEURS[prim])
        d.cmd("variable.set", variable=nom, value={"alias": prim})
    for nom, (type_, prim) in SEMANTIQUE_AUTRES.items():
        d.cmd("variable.create", collection="Sémantique", name=nom, type=type_,
              value=MARQUE.get(prim) or int(prim.split("/")[1]))
        d.cmd("variable.set", variable=nom, value={"alias": prim})

    d.cmd("variable.createCollection", name="Densité", modes=["Bureau", "Terrain"])
    for nom, (b, t) in DENSITE.items():
        d.cmd("variable.create", collection="Densité", name=nom, type="number",
              values={"Bureau": b, "Terrain": t})
        d.cmd("variable.set", variable=nom, mode="Bureau", value={"alias": f"taille/{b}"})
        d.cmd("variable.set", variable=nom, mode="Terrain", value={"alias": f"taille/{t}"})
    d.cmd("variable.create", collection="Densité", name="cible/min", type="number",
          values={"Bureau": CIBLE[0], "Terrain": CIBLE[1]})
    for mode, c in zip(("Bureau", "Terrain"), CIBLE):
        d.cmd("variable.set", variable="cible/min", mode=mode, value={"alias": f"cible/{c}"})

    for nom, (graisse, taille) in STYLES.items():
        d.cmd("style.create", type="text", name=nom,
              value={"fontFamily": "Inter", "fontStyle": graisse, "fontSize": 16})
        d.cmd("style.bind", style=nom, field="fontSize", variable=taille)
        d.cmd("style.bind", style=nom, field="fontFamily", variable="typo/famille")

    d.cmd("page.select", id=pages["6 · Composants"])
    _composants(d)
    d.cmd("page.select", id=pages["5 · Fondations"])
    _planche(d)


def _jeu(d, nom: str, propriete: str, variantes: list, x0: int, y0: int, dessiner) -> dict:
    """Un composant à variantes : une variante par valeur, combinées en un jeu.
    `dessiner(cadre, valeur)` pose le contenu et rend le calque texte à lier à
    `libelle` (ou None). Toutes les variantes ont les mêmes calques."""
    ids, textes = [], []
    for k, v in enumerate(variantes):
        c = d.noeud("frame", roles={}, name=f"{propriete}={v}", x=x0, y=y0 + k * 72,
                    width=160, height=48, props={"fills": []})
        textes.append(dessiner(c, v))
        d.cmd("component.create", ids=[c])
        ids.append(c)
    d.cmd("component.combineVariants", ids=ids)
    jeu = d._id()
    d.cmd("node.set", ids=[jeu], name=nom)
    if textes[0] is not None:
        d.cmd("component.addProperty", id=jeu, name="libelle", type="text", default="Fait",
              layer=textes[0])
        for t in textes[1:]:
            d.cmd("component.bindProperty", layer=t, property="libelle", field="characters")
    return {"id": jeu, "calques": 1}   # chaque variante : le cadre et un calque


def _composants(d) -> None:
    d.noeud("text", x=0, y=-80, text="Composants — les props portent les noms des props React (§3)",
            name="Titre", props={"fontSize": 24, "fontStyle": "Bold", "fills": ["#1A1A1A"]})

    def bouton(c, v):
        fond = {"principale": "action/principale", "secondaire": "fond/surface",
                "inactif": "fond/sourdine"}[v]
        texte = {"principale": "action/principale-texte", "secondaire": "texte/principal",
                 "inactif": "texte/desactive"}[v]
        d.cmd("node.set", ids=[c], layout="horizontal", mainAlign="center", crossAlign="center",
              padding=[0, 12, 0, 12], fills=["#000000"])
        d.lier(c, "fill", fond)
        d.lier(c, "cornerRadius", "forme/rayon-controle")
        if v == "secondaire":
            d.cmd("node.set", ids=[c], stroke="#000000", strokeWeight=1.5, strokeAlign="inside")
            d.lier(c, "stroke", "bordure/defaut")
        return d.noeud("text", roles={"fill": texte, "style": "Corps fort"}, parent=c,
                       text="Fait", name="libelle", props={"fills": ["#000000"]})
    d.composants["Bouton"] = _jeu(d, "Bouton", "variante", ["principale", "secondaire", "inactif"],
                                  0, 0, bouton)

    def case(c, v):
        d.cmd("node.set", ids=[c], width=20, height=20, fills=["#000000"], stroke="#000000",
              strokeWeight=1.5, strokeAlign="inside")
        d.lier(c, "fill", "action/principale" if v == "cochee" else "fond/surface")
        d.lier(c, "stroke", "action/principale" if v == "cochee" else "bordure/forte")
        d.lier(c, "cornerRadius", "forme/rayon-case")
        d.noeud("text", roles={"fill": "texte/inverse", "style": "Petit fort"}, parent=c,
                        x=0, y=1, text="✓", name="coche",
                        props={"width": 20, "textAlign": "center", "fills": ["#000000"],
                               "visible": v == "cochee"})
        return None
    d.composants["Case"] = _jeu(d, "Case", "etat", ["vide", "cochee"], 240, 0, case)

    logo = d.noeud("frame", roles={}, name="Marque/Logo", x=400, y=0, width=112, height=32,
                   props={"fills": [], "layout": "horizontal", "gap": 8, "crossAlign": "center",
                          "sizingH": "hug"})
    signe = d.noeud("frame", roles={"fill": "action/principale"}, parent=logo, name="Signe",
                    width=24, height=24, props={"fills": ["#000000"]})
    d.lier(signe, "cornerRadius", "forme/rayon-marque")
    nom = d.noeud("text", roles={"fill": "texte/principal", "style": "Corps fort"}, parent=logo,
                  text=MARQUE["marque/nom"], name="Nom du produit", props={"fills": ["#000000"]})
    d.lier(nom, "characters", "texte/nom-produit")
    d.cmd("component.create", ids=[logo])
    d.composants["Marque/Logo"] = {"id": logo, "calques": 2}


def _planche(d) -> None:
    """La planche des fondations : chaque rôle sémantique, chaque style."""
    d.noeud("text", x=0, y=-80, text="Fondations — rôles sémantiques et styles de texte",
            name="Titre", props={"fontSize": 24, "fontStyle": "Bold", "fills": ["#1A1A1A"]})
    for k, (nom, prim) in enumerate(SEMANTIQUE_COULEURS.items()):
        x, y = (k % 4) * 260, (k // 4) * 96
        d.noeud("frame", roles={"fill": nom, "stroke": "bordure/discrete"}, name=nom, x=x, y=y,
                width=56, height=56, props={"fills": ["#000000"], "stroke": "#000000",
                                            "strokeWeight": 1, "cornerRadius": 8})
        d.noeud("text", roles={"fill": "texte/principal", "style": "Petit fort"}, x=x + 68, y=y + 6,
                text=nom, name=nom, props={"fills": ["#000000"]})
        d.noeud("text", roles={"fill": "texte/secondaire", "style": "Légende"}, x=x + 68,
                y=y + 30, text=f"→ {prim} · {PRIMITIVES_COULEURS[prim]}", name=prim,
                props={"fills": ["#000000"]})
    y0 = (len(SEMANTIQUE_COULEURS) // 4 + 1) * 96 + 40
    for k, nom in enumerate(STYLES):
        d.noeud("text", roles={"fill": "texte/principal", "style": nom}, x=0, y=y0 + k * 56,
                text=f"{nom} — Poirier 'Conférence' · mar. 21 oct.", name=nom,
                props={"fills": ["#000000"]})


if __name__ == "__main__":
    f = verifier_contrastes()
    print("CONFIRME contrastes" if not f else "ECHOUE\n" + "\n".join(f))
