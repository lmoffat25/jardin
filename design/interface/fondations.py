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

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))  # -I retire ce dossier du chemin
from icones import ICONES  # noqa: E402

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
RAYONS = [4, 6, 10, 13, 24]
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
    # Un bouton rond de 48 posé sur une photo (T5, retour) : la moitié de son côté.
    "forme/rayon-rond": ("number", "rayon/24"),
}
# Les couples à 7:1 (texte sur son fond), et les contours à 3:1.
CONTRASTES_7 = [("texte/principal", "fond/page"), ("texte/secondaire", "fond/page"),
                ("texte/secondaire", "fond/sourdine"), ("action/principale-texte", "action/principale"),
                ("texte/inverse", "etat/en-retard")] + [
    (f"etat/{e}", f"etat/{e}-fond") for e in ("en-retard", "a-faire", "fait", "hors-saison",
                                              "non-envoye", "erreur", "attention")] + [
    (f"etat/{e}", "fond/surface") for e in ("en-retard", "a-faire", "fait", "non-envoye")] + [
    # Le détail d'un bandeau sur sa teinte, le texte d'une pastille forte sur sa couleur.
    ("texte/secondaire", f"etat/{e}-fond") for e in ("fait", "non-envoye", "attention", "erreur")] + [
    ("texte/inverse", f"etat/{e}") for e in ("a-faire", "fait", "hors-saison", "non-envoye")] + [
    ("texte/inverse", "action/principale")]
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


def _jeu(d, nom: str, variantes: list[dict], x0: int, y0: int, dessiner, pas: int = 72,
         textes: dict | None = None, booleens: dict | None = None,
         permutations: dict | None = None) -> dict:
    """Un composant à variantes : une variante par combinaison ({propriété: valeur}),
    combinées en un jeu. `dessiner(cadre, v)` pose le contenu et rend ses calques nommés
    ({nom: id}). Toutes les variantes ont les mêmes calques, dans le même ordre : changer
    de variante recrée les calques de l'instance, et seuls des calques identiques gardent
    leurs identifiants (essai du 20/10/2026).

    `textes`, `booleens`, `permutations` : {propriété: (calque, défaut)}, liées au calque
    nommé de chaque variante (texte, visibilité, composant de l'instance). Le résultat
    donne le nombre de calques d'une instance et la place de chaque calque nommé
    (`parties`), pour viser un bouton à l'intérieur d'une instance."""
    ids, calques, nb = [], [], None
    for k, v in enumerate(variantes):
        depart = d.suivant
        c = d.noeud("frame", roles={}, name=", ".join(f"{p}={x}" for p, x in v.items()),
                    x=x0, y=y0 + k * pas, width=160, height=48, props={"fills": []})
        cal = dessiner(c, v)
        decal = {cle: i - c for cle, i in cal.items()}
        if calques and (d.suivant - depart - 1 != nb or decal != calques[0][1]):
            raise SystemExit(f"ECHOUE {nom} : les variantes n'ont pas les mêmes calques")
        nb = d.suivant - depart - 1
        d.cmd("component.create", ids=[c])
        ids.append(c)
        calques.append((cal, decal))
    if len(ids) > 1:
        d.cmd("component.combineVariants", ids=ids)
        jeu = d._id()
    else:
        jeu = ids[0]
    d.cmd("node.set", ids=[jeu], name=nom)
    for sorte, champ, table in (("text", "characters", textes), ("boolean", "visible", booleens),
                                ("instanceSwap", "mainComponent", permutations)):
        for prop, (calque, defaut) in (table or {}).items():
            d.cmd("component.addProperty", id=jeu, name=prop, type=sorte, default=defaut,
                  layer=calques[0][0][calque])
            for cal, _ in calques[1:]:
                d.cmd("component.bindProperty", layer=cal[calque], property=prop, field=champ)
            if sorte == "boolean" and not defaut:   # le maître montre l'état par défaut
                d.cmd("node.set", ids=[cal[calque] for cal, _ in calques], visible=False)
    return {"id": jeu, "calques": nb, "parties": calques[0][1]}


# Le rôle du texte de chaque variante de bouton : son icône le reprend.
BOUTON_TEXTE = {"principale": "action/principale-texte", "secondaire": "texte/principal",
                "inactif": "texte/desactive"}
# Ce que dit un bandeau : le rôle d'état qui le colore, et son icône.
BANDEAUX = {"succes": ("fait", "circle-check"), "non-envoye": ("non-envoye", "wifi-off"),
            "attention": ("attention", "triangle-alert"), "erreur": ("erreur", "circle-alert")}
PASTILLES = ["en-retard", "a-faire", "fait", "hors-saison", "non-envoye", "neutre"]


def _cadre(d, c, roles: dict, **reglages) -> None:
    """Règle le cadre d'une variante et lie son remplissage, son contour et son rayon."""
    if "fill" in roles:
        reglages["fills"] = ["#000000"]
    if "stroke" in roles:
        reglages.update(stroke="#000000", strokeAlign="inside")
        if "strokeBottomWeight" not in reglages:   # un trait du bas seul, comme `boite`
            reglages.setdefault("strokeWeight", 1.5)
    d.cmd("node.set", ids=[c], **reglages)
    for champ, variable in roles.items():
        d.lier(c, champ, variable)


def _boite(d, parent, nom: str, sens: str, **props) -> int:
    """Un cadre sans fond en mise en page automatique, qui prend la largeur."""
    return d.noeud("frame", roles={}, parent=parent, name=nom, props={
        "fills": [], "layout": sens, "sizingH": "fill", "sizingV": "hug", **props})


def _texte(d, parent, nom: str, s: str, role: str, style: str, flux: bool = True) -> int:
    props = {"fills": ["#000000"]}
    if flux:
        props.update(sizingH="fill", textAutoResize="height")
    return d.noeud("text", roles={"fill": role, "style": style}, parent=parent, text=s, name=nom,
                   props=props)


def _icone(d, parent, nom: str, cote: int, role: str, calque: str) -> int:
    i = d.instance(f"Icône/{nom}", parent, w=cote, h=cote, calque=calque)
    d.lier(i + 1, "stroke", role)   # le trait, seul calque de l'icône
    return i


def _composants(d) -> None:
    d.noeud("text", x=0, y=-80, text="Composants — les props portent les noms des props React (§3)",
            name="Titre", props={"fontSize": 24, "fontStyle": "Bold", "fills": ["#1A1A1A"]})

    # Icônes Lucide (O25), posées d'abord : les autres composants en contiennent. Un trait
    # de 2 px lié à texte/principal, que l'instance relie à un autre rôle ; il suit la
    # taille de l'instance (contrainte « scale »).
    for k, (nom, trace) in enumerate(ICONES.items()):
        c = d.noeud("frame", roles={}, name=f"Icône/{nom}", x=600 + (k % 7) * 48,
                    y=(k // 7) * 48, width=24, height=24, props={"fills": []})
        d.noeud("vector", roles={"stroke": "texte/principal"}, parent=c, path=trace, name="trait",
                props={"fills": [], "stroke": "#000000", "strokeWeight": 2, "strokeCap": "round",
                       "strokeJoin": "round",
                       "constraints": {"horizontal": "scale", "vertical": "scale"}})
        d.cmd("component.create", ids=[c])
        d.composants[f"Icône/{nom}"] = {"id": c, "calques": 1}
    icone = {nom: d.composants[f"Icône/{nom}"]["id"] for nom in ICONES}

    # Bouton : une icône peut accompagner le libellé, avant ou après ; l'icône seule
    # (avecLibelle = faux) garde son libellé comme nom accessible (§2, icônes).
    def bouton(c, v):
        v = v["variante"]
        fond = {"principale": "action/principale", "secondaire": "fond/surface",
                "inactif": "fond/sourdine"}[v]
        roles = {"fill": fond, "cornerRadius": "forme/rayon-controle"}
        if v == "secondaire":
            roles["stroke"] = "bordure/defaut"
        _cadre(d, c, roles, layout="horizontal", mainAlign="center", crossAlign="center",
               padding=[0, 12, 0, 12], gap=8)
        avant = _icone(d, c, "plus", 24, BOUTON_TEXTE[v], "icone")
        lib = _texte(d, c, "libelle", "Fait", BOUTON_TEXTE[v], "Corps fort", flux=False)
        apres = _icone(d, c, "chevron-right", 24, BOUTON_TEXTE[v], "iconeApres")
        return {"icone": avant, "libelle": lib, "iconeApres": apres}
    d.composants["Bouton"] = _jeu(
        d, "Bouton", [{"variante": v} for v in BOUTON_TEXTE], 0, 0, bouton,
        textes={"libelle": ("libelle", "Fait")},
        booleens={"avecIcone": ("icone", False), "avecLibelle": ("libelle", True),
                  "avecIconeApres": ("iconeApres", False)},
        permutations={"icone": ("icone", icone["plus"]),
                      "iconeApres": ("iconeApres", icone["chevron-right"])})

    def case(c, v):
        cochee = v["etat"] == "cochee"
        couleur = "action/principale" if cochee else "fond/surface"
        _cadre(d, c, {"fill": couleur, "stroke": "action/principale" if cochee else "bordure/forte",
                      "cornerRadius": "forme/rayon-case"},
               width=20, height=20, layout="horizontal", mainAlign="center", crossAlign="center")
        coche = _icone(d, c, "check", 14, "texte/inverse", "coche")
        d.cmd("node.set", ids=[coche], visible=cochee)
        return {"coche": coche}
    d.composants["Case"] = _jeu(d, "Case", [{"etat": "vide"}, {"etat": "cochee"}], 240, 0, case)

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

    # Pastille d'état : le mot dit l'état, la couleur le redouble (charte §5).
    def pastille(c, v):
        e, forte = v["etat"], v["forte"] == "oui"
        couleur = "action/principale" if e == "neutre" else f"etat/{e}"
        if forte:
            fond, trait, texte = couleur, couleur, "texte/inverse"
        elif e == "neutre":
            fond, trait, texte = "fond/surface", "bordure/forte", "texte/principal"
        else:
            fond, trait, texte = f"etat/{e}-fond", couleur, couleur
        _cadre(d, c, {"fill": fond, "stroke": trait, "cornerRadius": "forme/rayon-pastille"},
               width=96, height=26, layout="horizontal", mainAlign="center", crossAlign="center",
               padding=[0, 8, 0, 8])
        return {"libelle": _texte(d, c, "libelle", "à faire", texte, "Légende forte", flux=False)}
    d.composants["Pastille"] = _jeu(
        d, "Pastille", [{"etat": e, "forte": f} for e in PASTILLES for f in ("non", "oui")],
        0, 320, pastille, pas=40, textes={"libelle": ("libelle", "à faire")})

    # Champ : la valeur saisie, ou l'indication en texte/desactive ; `liste` montre le
    # chevron d'une liste de choix.
    def champ(c, v):
        e = v["etat"]
        _cadre(d, c, {"fill": "fond/surface", "cornerRadius": "forme/rayon-controle",
                      "stroke": "etat/erreur" if e == "erreur" else "bordure/defaut"},
               width=320, height=48, layout="horizontal", crossAlign="center",
               padding=[0, 14, 0, 14], gap=8, strokeWeight=2 if e == "erreur" else 1.5)
        val = _texte(d, c, "valeur", "Valeur", "texte/desactive" if e == "vide" else
                     "texte/principal", "Corps")
        return {"valeur": val, "chevron": _icone(d, c, "chevron-down", 20, "texte/secondaire",
                                                 "chevron")}
    d.composants["Champ"] = _jeu(
        d, "Champ", [{"etat": e} for e in ("vide", "rempli", "erreur")], 240, 320, champ,
        textes={"valeur": ("valeur", "Valeur")}, booleens={"liste": ("chevron", False)})

    # Bandeau de message : un titre, un détail facultatif, une action facultative.
    def bandeau(c, v):
        e, nom_icone = BANDEAUX[v["sorte"]]
        _cadre(d, c, {"fill": f"etat/{e}-fond", "stroke": f"etat/{e}",
                      "cornerRadius": "forme/rayon-controle"},
               width=358, height=72, layout="horizontal", crossAlign="center",
               padding=[12, 14, 12, 14], gap=12)
        corps = _boite(d, c, "Corps", "vertical", gap=4)
        ligne = _boite(d, corps, "Titre", "horizontal", gap=8)
        _icone(d, ligne, nom_icone, 20, f"etat/{e}", "icone")
        titre = _texte(d, ligne, "titre", "Enregistré", f"etat/{e}", "Corps fort")
        detail = _texte(d, corps, "detail", "Détail", "texte/secondaire", "Petit")
        action = d.instance("Bouton", c, proprietes={"variante": "secondaire",
                                                     "libelle": "Annuler"}, calque="action")
        return {"titre": titre, "detail": detail, "action": action}
    d.composants["Bandeau"] = _jeu(
        d, "Bandeau", [{"sorte": s} for s in BANDEAUX], 640, 320, bandeau, pas=100,
        textes={"titre": ("titre", "Enregistré"), "detail": ("detail", "Détail")},
        booleens={"avecDetail": ("detail", True), "avecAction": ("action", False)})

    # Ligne d'échéance (terrain) : la liste du jour (T1, T4) et la tournée (T7, avecNumero).
    def ligne(c, v):
        retard = v["etat"] == "en-retard"
        _cadre(d, c, {"stroke": "bordure/discrete"}, width=390, height=72, layout="horizontal",
               crossAlign="center", padding=[12, 16, 12, 16], gap=12, sizingV="hug",
               strokeBottomWeight=1, fills=[])
        numero = d.noeud("frame", roles={"fill": "action/principale"}, parent=c, name="numero",
                         width=28, height=28, props={"fills": ["#000000"], "layout": "horizontal",
                                                     "mainAlign": "center", "crossAlign": "center"})
        d.lier(numero, "cornerRadius", "forme/rayon-pastille")
        rang = _texte(d, numero, "rang", "1", "texte/inverse", "Petit fort", flux=False)
        col = _boite(d, c, "Texte", "vertical", gap=2)
        plante = _texte(d, col, "plante", "Poirier 'Conférence'", "texte/principal", "Corps fort")
        det = _boite(d, col, "Détail", "horizontal", gap=6, crossAlign="center")
        alerte = _icone(d, det, "triangle-alert", 16, "etat/en-retard", "alerte")
        d.cmd("node.set", ids=[alerte], visible=retard)
        detail = _texte(d, det, "detail", "Taille · à faire", "etat/en-retard" if retard else
                        "texte/secondaire", "Petit fort" if retard else "Petit")
        fait = d.instance("Bouton", c, w=88, h=48, proprietes={"variante": "secondaire",
                                                               "libelle": "Fait"}, calque="fait")
        return {"numero": numero, "rang": rang, "plante": plante, "detail": detail, "fait": fait}
    d.composants["Échéance/Ligne"] = _jeu(
        d, "Échéance/Ligne", [{"etat": "a-faire"}, {"etat": "en-retard"}], 1100, 320, ligne,
        pas=100, textes={"plante": ("plante", "Poirier 'Conférence'"),
                         "detail": ("detail", "Taille · à faire"), "rang": ("rang", "1")},
        booleens={"avecNumero": ("numero", False)})


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
