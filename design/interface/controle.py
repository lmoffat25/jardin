"""Contrôle M6 : aucun remplissage, contour ni texte de l'interface sans liaison.

    python3 -I design/interface/controle.py <fichier.figcraft> <premier id> <dernier id>

Appelé par la passe interface de `construire.py` sur les identifiants qu'elle a
créés (pages 5 à 8). Rend CONFIRME s'il n'y a aucune valeur en dur, ECHOUE et la
liste des calques fautifs sinon (O22 : `node.get` rend `boundVariables` et
`styles.text`).
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import tempfile


def fautes(n: dict) -> list[str]:
    if n.get("type") == "COMPONENT_SET":   # le pointillé du jeu de variantes : repère d'édition
        return []
    lies = n.get("boundVariables") or {}
    r = []
    if any(p.get("type") == "solid" for p in n.get("fills") or []) and not any(
            k == "fill" or k.startswith("fills") for k in lies):
        r.append("remplissage non lié")
    if n.get("strokes") and not any(k == "stroke" or k.startswith("strokes") for k in lies):
        r.append("contour non lié")
    if n.get("type") == "TEXT" and not (n.get("styles") or {}).get("text"):
        r.append("texte sans style")
    return r


def controler(fichier: str, debut: int, fin: int) -> tuple[list[str], int]:
    cmds = [{"command": "node.get", "params": {"id": i}} for i in range(debut, fin)]
    with tempfile.NamedTemporaryFile("w", suffix=".jsonl", delete=False) as f:
        f.write("\n".join(json.dumps(c) for c in cmds))
    env = dict(os.environ, FIGCRAFT_NO_SYSTEM_FONTS="1")
    # Un identifiant absent arrête `run` : le compte des calques lus le révèle.
    r = subprocess.run(["figcraft-cli", "run", f.name, "--in", fichier], capture_output=True,
                       text=True, env=env)
    os.unlink(f.name)
    sortie, lus = [], 0
    for ligne in r.stdout.splitlines():
        try:
            res = json.loads(ligne)
        except json.JSONDecodeError:
            continue
        if not res.get("ok"):
            continue
        n = res["result"]
        lus += 1
        for faute in fautes(n):
            sortie.append(f"{n.get('id')} {n.get('type')} « {n.get('name', '')[:40]} » : {faute}")
    return sortie, lus


if __name__ == "__main__":
    fichier, debut, fin = sys.argv[1], int(sys.argv[2]), int(sys.argv[3])
    f, lus = controler(fichier, debut, fin)
    if lus != fin - debut:
        print(f"ECHOUE M6 : {lus} calques lus sur {fin - debut} attendus")
        sys.exit(1)
    if f:
        print(f"ECHOUE M6 : {len(f)} valeurs non liées", *f[:40], sep="\n")
        sys.exit(1)
    print(f"CONFIRME M6 : aucune valeur non liée ({lus} calques contrôlés)")
