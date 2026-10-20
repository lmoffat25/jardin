#!/usr/bin/env python3
"""Prototype web des écrans de l'interface, à jouer au téléphone sans FigCraft (O26).

    python3 -I design/prototype/generer.py <dossier de sortie> [fichier.figcraft]

Lit les pages « 7 · Écrans terrain » et « 8 · Écrans bureau » de la maquette :
chaque écran devient une image, chaque calque qui porte une interaction une zone
à toucher, et les parcours du fichier le sommaire. `lecteur.html` joue le tout :
navigation, retour, superpositions, conditions sur les variables du prototype.
Un texte lié à une variable (« Essai : réseau ✓ ») a une image par valeur.

Rend CONFIRME, ou ECHOUE si une destination n'est pas un écran de ces pages.
La sortie est autonome (index.html et ses images) : un serveur statique suffit.
"""
from __future__ import annotations

import concurrent.futures
import json
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ICI = Path(__file__).resolve().parent
PAGES = {"7 · Écrans terrain": 3, "8 · Écrans bureau": 1.5}   # page → échelle des images
ENV = dict(os.environ, FIGCRAFT_NO_SYSTEM_FONTS="1")


def lancer(commandes: list[dict], fichier: Path, sortie: Path | None = None) -> list:
    with tempfile.NamedTemporaryFile("w", suffix=".jsonl", delete=False) as f:
        f.write("\n".join(json.dumps(c) for c in commandes))
    args = ["figcraft-cli", "run", f.name, "--in", str(fichier)]
    if sortie:
        args += ["--out", str(sortie)]
    r = subprocess.run(args, capture_output=True, text=True, env=ENV)
    os.unlink(f.name)
    if r.returncode:
        sys.exit(f"ECHOUE figcraft-cli run : {r.stderr.strip()[:300]}")
    return [json.loads(ligne).get("result") for ligne in r.stdout.splitlines() if ligne.startswith("{")]


def lire(fichier: Path) -> tuple[list[dict], dict[int, dict], dict[int, int]]:
    """Les pages, chaque calque par identifiant, et le parent de chacun (parcours en largeur)."""
    tout = lancer([{"command": "node.get", "params": {"id": i}} for i in range(1, 64)], fichier)
    pages = [n for n in tout if isinstance(n, dict) and n.get("type") == "PAGE" and n["name"] in PAGES]
    noeuds, parents, file = {}, {}, []
    for p in pages:
        for c in p["children"]:
            parents[c["id"]] = p["id"]
            file.append(c["id"])
    while file:
        lot, file = file, []
        for n in lancer([{"command": "node.get", "params": {"id": i}} for i in lot], fichier):
            if not isinstance(n, dict) or "id" not in n:
                continue
            noeuds[n["id"]] = n
            for c in n.get("children") or []:
                parents[c["id"]] = n["id"]
                file.append(c["id"])
    return pages, noeuds, parents


def ecran_de(i: int, parents: dict[int, int], ecrans: set[int]) -> int | None:
    while i is not None and i not in ecrans:
        i = parents.get(i)
    return i


def visible(i: int, noeuds: dict, parents: dict, ecran: int) -> bool:
    while i != ecran:
        if noeuds.get(i, {}).get("visible") is False:
            return False
        i = parents[i]
    return True


def exporter(fichier: Path, i: int, echelle: float, chemin: Path) -> None:
    r = subprocess.run(["figcraft-cli", "export", str(fichier), "-o", str(chemin), "--node", str(i),
                        "--format", "webp", "--scale", str(echelle)],
                       capture_output=True, text=True, env=ENV)
    if r.returncode or not chemin.exists():
        sys.exit(f"ECHOUE export de l'écran {i} : {r.stderr.strip()[:300]}")


def main() -> int:
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    sortie = Path(sys.argv[1]).resolve()
    fichier = Path(sys.argv[2] if len(sys.argv) > 2 else ICI.parent / "jardin.figcraft").resolve()
    pages, noeuds, parents = lire(fichier)
    variables = {v["id"]: v for v in (lancer([{"command": "variable.list", "params": {}}], fichier)[0]
                                      or {}).get("variables", [])}

    ecrans = {}
    for p in pages:
        for c in p["children"]:
            if c["type"] == "FRAME":
                ecrans[c["id"]] = {"id": c["id"], "nom": c["name"], "page": p["name"],
                                   "l": c["width"], "h": c["height"], "zones": [], "images": {}}
    zones, fautes, valeurs = 0, [], {}
    for i, n in noeuds.items():
        if not n.get("interactions"):
            continue
        e = ecran_de(i, parents, set(ecrans))
        if e is None or not visible(i, noeuds, parents, e):
            continue
        b, o = n["absoluteBounds"], noeuds[e]["absoluteBounds"]
        for it in n["interactions"]:
            pile = list(it["actions"])
            while pile:
                a = pile.pop()
                if a.get("dest") is not None and a["dest"] not in ecrans:
                    fautes.append(f"{n['name']} ({i}) → {a['dest']}")
                if a["type"] == "setVariable" and isinstance(a.get("value"), str):
                    valeurs.setdefault(a["variable"], set()).add(a["value"])
                for bloc in a.get("blocks") or []:
                    pile += bloc.get("actions", [])
        ecrans[e]["zones"].append({"x": b["x"] - o["x"], "y": b["y"] - o["y"], "l": b["width"],
                                   "h": b["height"], "nom": n["name"], "interactions": n["interactions"]})
        zones += 1
    if fautes:
        print(f"ECHOUE {len(fautes)} destination(s) hors des écrans de l'interface :")
        print("\n".join("  " + f for f in fautes))
        return 1

    # Un texte lié à une variable texte que le prototype change : une image par valeur.
    liees = {}
    for i, n in noeuds.items():
        v = (n.get("boundVariables") or {}).get("characters")
        if v in valeurs:
            e = ecran_de(i, parents, set(ecrans))
            if e is not None:
                liees.setdefault(v, set()).add(e)
    if len(liees) > 1:
        sys.exit("ECHOUE plusieurs variables texte changent : le lecteur n'en suit qu'une")

    if sortie.exists():
        shutil.rmtree(sortie)
    (sortie / "ecrans").mkdir(parents=True)
    travaux = []
    for e in ecrans.values():
        nom = f"ecrans/{e['id']}.webp"
        e["images"][""] = nom
        travaux.append((fichier, e["id"], PAGES[e["page"]], sortie / nom))
    tmp = Path(tempfile.mkdtemp())
    for v, ids in liees.items():
        for k, val in enumerate(sorted(valeurs[v])):
            variante = tmp / f"variante-{k}.figcraft"
            lancer([{"command": "variable.set", "params": {"variable": v, "value": val}}], fichier, variante)
            for e in ids:
                nom = f"ecrans/{e}-{k}.webp"
                ecrans[e]["images"][val] = nom
                ecrans[e]["variable"] = v
                travaux.append((variante, e, PAGES[ecrans[e]["page"]], sortie / nom))
    with concurrent.futures.ThreadPoolExecutor(4) as pool:
        list(pool.map(lambda t: exporter(*t), travaux))
    shutil.rmtree(tmp)

    donnees = {
        "parcours": [{"nom": f["name"], "depart": f["start"], "page": p["name"]}
                     for p in pages for f in p.get("flows") or []],
        "ecrans": ecrans,
        "variables": {k: next(iter(v["values"].values()))["value"] for k, v in variables.items()
                      if k in {a for e in ecrans.values() for a in _variables_lues(e)}},
    }
    page = (ICI / "lecteur.html").read_text()
    page = page.replace("/*DONNEES*/null", json.dumps(donnees, ensure_ascii=False))
    (sortie / "index.html").write_text(page)
    taille = sum(f.stat().st_size for f in sortie.rglob("*") if f.is_file())
    print(f"CONFIRME {sortie}/index.html · {len(ecrans)} écrans, {zones} zones, "
          f"{len(donnees['parcours'])} parcours, {len(travaux)} images, {taille / 1e6:.1f} Mo")
    return 0


def _variables_lues(e: dict) -> set[str]:
    """Les variables qu'une zone de l'écran lit ou écrit, et celle de son texte lié."""
    r = {e["variable"]} if e.get("variable") else set()
    for z in e["zones"]:
        pile = [a for it in z["interactions"] for a in it["actions"]]
        while pile:
            a = pile.pop()
            if a.get("variable"):
                r.add(a["variable"])
            for bloc in a.get("blocks") or []:
                c = (bloc.get("condition") or {}).get("value") or {}
                if c.get("type") == "alias":
                    r.add(c["value"])
                pile += bloc.get("actions", [])
    return r


if __name__ == "__main__":
    sys.exit(main())
