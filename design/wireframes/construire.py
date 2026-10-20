#!/usr/bin/env python3
"""Construit design/jardin.figcraft à partir de toutes les vagues de wireframes.

    python3 -I design/wireframes/construire.py

Chaque vague ajoute ses écrans au même document, dans l'ordre d'import. Deux
passes : les wireframes (pages 3 et 4), puis l'interface (pages 5 à 8), dans
un second processus qui reprend le fichier et le compteur d'identifiants.
"""
import os
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))  # -I retire ce dossier du chemin

import vague1  # noqa: E402  (socle, pages, vague 1)
import vague2  # noqa: E402,F401
import vague3  # noqa: E402,F401
import vague4  # noqa: E402,F401
import vague5  # noqa: E402,F401
import vague6  # noqa: E402,F401

code = vague1.main()
if code or vague1.INTERFACE:
    sys.exit(code)
env = dict(os.environ, JARDIN_PASSE="interface", JARDIN_DEPART=str(vague1.d.suivant))
sys.exit(subprocess.run([sys.executable, "-I", __file__], env=env).returncode)
