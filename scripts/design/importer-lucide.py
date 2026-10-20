"""Convertit des icônes Lucide (SVG 24 × 24) en un tracé SVG unique par icône.

    python3 -I scripts/design/importer-lucide.py <dossier des .svg> > design/interface/icones.py

Lucide est sous licence ISC (et MIT pour les icônes reprises de Feather) : usage
commercial permis, avis de copyright à conserver (O25, `design/interface/LICENSE-lucide`).
Cercles, rectangles, lignes et polylignes deviennent des sous-tracés : FigCraft
crée un vecteur depuis un seul attribut `d`.
"""
from __future__ import annotations

import pathlib
import re
import sys
import xml.etree.ElementTree as ET


def _f(e, k, defaut="0"):
    return float(e.get(k, defaut))


def tracer(e) -> str:
    tag = e.tag.split("}")[-1]
    if tag == "path":
        # Un « m » en tête est absolu, et les couples qui le suivent sont des « l » relatifs.
        # Mis bout à bout, il deviendrait relatif : on l'écrit « M x y l … ».
        m = re.match(r"m\s*(-?[\d.]+)[\s,]*(-?[\d.]+)\s*(.*)", e.get("d").strip(), re.S)
        if not m:
            return e.get("d")
        x, y, reste = m.groups()
        return f"M{x} {y}" + (f"l{reste}" if reste[:1] in "-.0123456789" and reste else reste)
    if tag == "circle":
        cx, cy, r = _f(e, "cx"), _f(e, "cy"), _f(e, "r")
        return f"M{cx - r} {cy}a{r} {r} 0 1 0 {2 * r} 0a{r} {r} 0 1 0 {-2 * r} 0z"
    if tag == "line":
        return f"M{_f(e, 'x1')} {_f(e, 'y1')}L{_f(e, 'x2')} {_f(e, 'y2')}"
    if tag in ("polyline", "polygon"):
        p = re.split(r"[\s,]+", e.get("points").strip())
        pts = [f"{p[k]} {p[k + 1]}" for k in range(0, len(p), 2)]
        return "M" + "L".join(pts) + ("z" if tag == "polygon" else "")
    if tag == "rect":
        x, y, w, h = _f(e, "x"), _f(e, "y"), _f(e, "width"), _f(e, "height")
        r = _f(e, "rx", e.get("ry", "0"))
        if not r:
            return f"M{x} {y}h{w}v{h}h{-w}z"
        return (f"M{x + r} {y}h{w - 2 * r}a{r} {r} 0 0 1 {r} {r}v{h - 2 * r}a{r} {r} 0 0 1 {-r} {r}"
                f"h{-(w - 2 * r)}a{r} {r} 0 0 1 {-r} {-r}v{-(h - 2 * r)}a{r} {r} 0 0 1 {r} {-r}z")
    raise SystemExit(f"ECHOUE élément SVG non pris en charge : {tag}")


def main(dossier: str) -> None:
    print('"""Tracés des icônes Lucide v0.460.0 (ISC), générés par scripts/design/importer-lucide.py."""')
    print("ICONES = {")
    for f in sorted(pathlib.Path(dossier).glob("*.svg")):
        racine = ET.parse(f).getroot()
        d = "".join(tracer(e) for e in racine)
        print(f"    {f.stem!r}: {d!r},")
    print("}")


if __name__ == "__main__":
    main(sys.argv[1])
