#!/usr/bin/env python3
"""Construit design/jardin.figcraft à partir de toutes les vagues de wireframes.

    python3 -I design/wireframes/construire.py

Chaque vague ajoute ses écrans au même document, dans l'ordre d'import.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))  # -I retire ce dossier du chemin

import vague1  # noqa: E402  (socle, pages, vague 1)
import vague2  # noqa: E402,F401
import vague3  # noqa: E402,F401
import vague4  # noqa: E402,F401
import vague5  # noqa: E402,F401
import vague6  # noqa: E402,F401

sys.exit(vague1.main())
