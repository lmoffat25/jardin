"""M5 : les variables de la maquette, sous les mêmes noms, pour le code.

    python3 -I design/interface/exporter_css.py <fichier.figcraft> <sortie.css>

Lit `variable.export {format: css}` et le met en forme pour le navigateur :
la collection Prototype (état du prototype, pas de la marque) est retirée, les
tailles et rayons reçoivent leur unité, le mode Terrain se choisit par
`data-densite="terrain"` (sans accent, en minuscules).
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import tempfile

AVEC_PX = re.compile(r"^(\s*--(?:taille|rayon|cible)-[\w-]+: )(\d+(?:\.\d+)?);$", re.M)


def exporter(fichier: str) -> str:
    with tempfile.NamedTemporaryFile("w", suffix=".jsonl", delete=False) as f:
        f.write(json.dumps({"command": "variable.export", "params": {"format": "css"}}))
    r = subprocess.run(["figcraft-cli", "run", f.name, "--in", fichier], capture_output=True,
                       text=True, env=dict(os.environ, FIGCRAFT_NO_SYSTEM_FONTS="1"))
    os.unlink(f.name)
    res = json.loads(r.stdout.splitlines()[0])
    if not res.get("ok"):
        raise SystemExit(f"ECHOUE export CSS : {res.get('error')}")
    css = res["result"]["text"]
    blocs = re.split(r"(?=^/\* )", css, flags=re.M)
    css = "".join(b for b in blocs if not b.startswith("/* Prototype"))
    css = AVEC_PX.sub(r"\1\2px;", css)
    css = css.replace('[data-densité="Terrain"]', '[data-densite="terrain"]')
    return ("/* Généré par design/interface/exporter_css.py depuis design/jardin.figcraft.\n"
            "   Ne pas modifier à la main : changer la maquette, puis reconstruire (M5). */\n"
            + css)


if __name__ == "__main__":
    fichier, sortie = sys.argv[1], sys.argv[2]
    css = exporter(fichier)
    if "Prototype" in css or "data-densité" in css or not re.search(r"--taille-12: 12px;", css):
        print("ECHOUE export CSS : mise en forme incomplète")
        sys.exit(1)
    open(sortie, "w").write(css)
    n = len(re.findall(r"^\s*--[\w-]+:", css, re.M))
    print(f"CONFIRME {sortie} ({n} déclarations)")
