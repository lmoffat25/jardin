"""Produit le référentiel climatique des communes (RG-52, D-66, D-85), hors CI.

    python3 -I scripts/climat/generer-communes.py [dossier de sortie] [--cache <dossier>]

Seul ce script touche le réseau. Il télécharge les sources, toutes sous Licence
Ouverte, et écrit dans le dossier de sortie (par défaut `bibliotheque/climat/`) :

- `communes.csv` : code Insee, nom, codes postaux, coordonnées du point La Poste,
  altitude de ce point (service altimétrique de l'IGN) ;
- `stations.csv` : stations Météo-France, coordonnées, altitude, températures
  moyennes mensuelles 1991-2020, et `periode_complete` (0 si la fiche dit
  « Statistiques établies sur la période … » sous la température moyenne) ;
- `sources.csv` : chaque source, sa licence et sa date de mise à jour, pour les
  mentions légales.

Le calcul de RG-52 (station, écart, décalage, profil) n'est pas ici : il a lieu
à l'import (`php artisan jardin:importer-communes`), avec `parametres.yaml`, et
il est testé. La France métropolitaine seule, Corse comprise : les profils sont
calés sur Trappes, et n'ont pas de sens outre-mer (D-85).

Le cache (par défaut `$TMPDIR/climat-cache`) garde chaque téléchargement :
relancer le script ne refait que ce qui manque.
"""
from __future__ import annotations

import concurrent.futures
import csv
import datetime
import hashlib
import io
import json
import os
import pathlib
import re
import sys
import tempfile
import time
import urllib.parse
import urllib.request

COG = "https://www.insee.fr/fr/statistiques/fichier/8740222/v_commune_2026.csv"
# L'Insee ne rend pas de Last-Modified : date de publication lue sur data.gouv.fr.
COG_PUBLIE = "2026-02-24"
LA_POSTE = (
    "https://datanova.laposte.fr/data-fair/api/v1/datasets/laposte-hexasmal/"
    "metadata-attachments/base-officielle-codes-postaux.csv"
)
STATIONS = "https://meteofrance.s3.sbg.io.cloud.ovh.net/data/synchro_ftp/REF_STATION/liste_fiches_clim.geojson"
FICHE = "https://meteofrance.s3.sbg.io.cloud.ovh.net/data/synchro_ftp/REF_STATION/FICHECLIM_{}.data"
ALTI = "https://data.geopf.fr/altimetrie/1.0/calcul/alti/rest/elevation.json"

# Le service rend 414 au-delà d'environ 300 points par requête.
POINTS_PAR_APPEL = 200

AGENT = "jardin-referentiel-climat/1.0 (generer-communes.py)"


def metropole(departement: str) -> bool:
    return departement in ("2A", "2B") or (departement.isdigit() and 1 <= int(departement) <= 95)


def telecharger(url: str, cache: pathlib.Path, nom: str) -> tuple[bytes, str]:
    """Le contenu, et sa date de mise à jour (`Last-Modified`, sinon aujourd'hui)."""
    chemin, date = cache / nom, cache / (nom + ".date")
    if chemin.exists() and date.exists():
        return chemin.read_bytes(), date.read_text()
    for essai in range(4):
        try:
            requete = urllib.request.Request(url, headers={"User-Agent": AGENT})
            with urllib.request.urlopen(requete, timeout=120) as r:
                contenu = r.read()
                entete = r.headers.get("Last-Modified")
            break
        except OSError:
            if essai == 3:
                raise
            time.sleep(2 ** essai)
    jour = (
        datetime.datetime.strptime(entete, "%a, %d %b %Y %H:%M:%S %Z").date().isoformat()
        if entete
        else datetime.date.today().isoformat()
    )
    chemin.write_bytes(contenu)
    date.write_text(jour)
    return contenu, jour


def lire_cog(contenu: bytes) -> tuple[dict[str, str], dict[str, str]]:
    """Les communes de métropole (code → nom) et les codes enfants → commune parente.

    Arrondissements municipaux, communes déléguées et associées portent leurs
    propres lignes chez La Poste : elles se rattachent à leur commune.
    """
    communes, parents = {}, {}
    for ligne in csv.DictReader(io.StringIO(contenu.decode("utf-8"))):
        if ligne["TYPECOM"] == "COM":
            if metropole(ligne["DEP"]):
                communes[ligne["COM"]] = ligne["LIBELLE"]
        elif ligne["COMPARENT"]:
            parents[ligne["COM"]] = ligne["COMPARENT"]
    return communes, parents


def lire_la_poste(contenu: bytes, communes: dict[str, str], parents: dict[str, str]) -> dict[str, dict]:
    """Par commune : ses codes postaux et un point, le sien de préférence."""
    resultat: dict[str, dict] = {}
    for ligne in csv.DictReader(io.StringIO(contenu.decode("utf-8"))):
        propre = ligne["code_commune_insee"]
        code = propre if propre in communes else parents.get(propre)
        if code not in communes:
            continue
        c = resultat.setdefault(code, {"cp": [], "point": None, "points": []})
        if ligne["code_postal"] not in c["cp"]:
            c["cp"].append(ligne["code_postal"])
        if not ligne["latitude"]:
            continue
        point = (float(ligne["latitude"]), float(ligne["longitude"]))
        if propre == code and not ligne["ligne_5"] and c["point"] is None:
            c["point"] = point
        c["points"].append(point)
    for c in resultat.values():
        # Paris, Lyon, Marseille n'ont que les points de leurs arrondissements.
        if c["point"] is None and c["points"]:
            c["point"] = (
                sum(p[0] for p in c["points"]) / len(c["points"]),
                sum(p[1] for p in c["points"]) / len(c["points"]),
            )
    return resultat


def altitudes(points: list[tuple[float, float]], cache: pathlib.Path) -> list[float]:
    """L'altitude de chaque point (lat, lon), par lots, au modèle RGE ALTI de l'IGN."""
    resultat: list[float] = []
    for debut in range(0, len(points), POINTS_PAR_APPEL):
        lot = points[debut : debut + POINTS_PAR_APPEL]
        requete = urllib.parse.urlencode({
            "lon": "|".join(f"{p[1]:.6f}" for p in lot),
            "lat": "|".join(f"{p[0]:.6f}" for p in lot),
            "resource": "ign_rge_alti_wld",
            "zonly": "true",
        })
        contenu, _ = telecharger(f"{ALTI}?{requete}", cache, f"alti-{hashlib.sha256(requete.encode()).hexdigest()[:16]}.json")
        valeurs = json.loads(contenu)["elevations"]
        if len(valeurs) != len(lot):
            raise RuntimeError(f"altimétrie : {len(valeurs)} valeurs pour {len(lot)} points")
        resultat.extend(float(v) for v in valeurs)
        print(f"  altitudes : {len(resultat)} / {len(points)}", file=sys.stderr, end="\r")
    print(file=sys.stderr)
    return resultat


def lire_fiche(texte: str) -> dict | None:
    """Altitude, températures moyennes des 12 mois, et période complète ou non."""
    lignes = [l.rstrip("\r") for l in texte.split("\n")]
    entete = next((l for l in lignes if "Indicatif :" in l), "")
    alt = re.search(r"alt : (-?\d+)m", entete)
    try:
        i = next(k for k, l in enumerate(lignes) if l.startswith("Température moyenne (Moyenne en °C)"))
    except StopIteration:
        return None
    courte = lignes[i + 1].startswith("Statistiques établies sur la période")
    valeurs = [v.strip() for v in lignes[i + (2 if courte else 1)].split(";")[1:13]]
    if alt is None or len(valeurs) != 12 or not all(re.fullmatch(r"-?\d+(\.\d+)?", v) for v in valeurs):
        return None
    complete = not courte and any("Statistiques  1991-2020" in l for l in lignes[:5])
    return {"alt": int(alt.group(1)), "t": [float(v) for v in valeurs], "complete": complete}


def stations(cache: pathlib.Path) -> tuple[list[dict], str]:
    contenu, _ = telecharger(STATIONS, cache, "liste_fiches_clim.geojson")
    entites = [
        f for f in json.loads(contenu)["features"] if metropole(f["properties"]["num"][:2])
    ]

    def fiche(entite: dict) -> dict | None:
        num = entite["properties"]["num"]
        texte, _ = telecharger(FICHE.format(num), cache, f"FICHECLIM_{num}.data")
        lue = lire_fiche(texte.decode("utf-8", errors="replace"))
        if lue is None:
            return None
        lon, lat = entite["geometry"]["coordinates"][:2]
        edition = re.search(r"Edité le : (\d\d)/(\d\d)/(\d{4})", texte.decode("utf-8", errors="replace"))
        return {
            "num": num,
            "nom": entite["properties"]["nom"].title(),
            "lat": lat,
            "lon": lon,
            **lue,
            "edition": f"{edition[3]}-{edition[2]}-{edition[1]}" if edition else "",
        }

    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
        lues = [s for s in pool.map(fiche, entites) if s is not None]
    return lues, max((s["edition"] for s in lues), default="")


def main(argv: list[str]) -> int:
    sortie = pathlib.Path(argv[0] if argv and not argv[0].startswith("--") else "bibliotheque/climat")
    cache = pathlib.Path(
        argv[argv.index("--cache") + 1] if "--cache" in argv else os.path.join(tempfile.gettempdir(), "climat-cache")
    )
    cache.mkdir(parents=True, exist_ok=True)
    sortie.mkdir(parents=True, exist_ok=True)
    consulte = datetime.date.today().isoformat()

    print("COG de l'Insee, base des codes postaux…", file=sys.stderr)
    cog, _ = telecharger(COG, cache, "v_commune_2026.csv")
    noms, parents = lire_cog(cog)
    poste, date_poste = telecharger(LA_POSTE, cache, "base-officielle-codes-postaux.csv")
    par_commune = lire_la_poste(poste, noms, parents)

    codes = sorted(c for c, v in par_commune.items() if v["point"] is not None)
    print(f"{len(codes)} communes situées, {len(noms) - len(codes)} sans point La Poste", file=sys.stderr)
    alts = altitudes([par_commune[c]["point"] for c in codes], cache)

    ecrites = 0
    with open(sortie / "communes.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f, delimiter=";", lineterminator="\n")
        w.writerow(["code_insee", "nom", "codes_postaux", "latitude", "longitude", "altitude_m"])
        for code, alt in zip(codes, alts):
            # Le service rend -99999 hors de son modèle (littoral, îlots).
            if alt < -500:
                continue
            lat, lon = par_commune[code]["point"]
            w.writerow([code, noms[code], " ".join(sorted(par_commune[code]["cp"])), f"{lat:.6f}", f"{lon:.6f}", round(alt)])
            ecrites += 1
    print(f"{ecrites} communes écrites", file=sys.stderr)

    print("Fiches climatologiques de Météo-France…", file=sys.stderr)
    lues, edition = stations(cache)
    with open(sortie / "stations.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f, delimiter=";", lineterminator="\n")
        w.writerow(["num", "nom", "latitude", "longitude", "altitude_m", "periode_complete", *[f"t{m:02d}" for m in range(1, 13)]])
        for s in sorted(lues, key=lambda s: s["num"]):
            w.writerow([s["num"], s["nom"], f"{s['lat']:.6f}", f"{s['lon']:.6f}", s["alt"], int(s["complete"]), *s["t"]])
    completes = sum(s["complete"] for s in lues)
    print(f"{len(lues)} stations, dont {completes} à période 1991-2020 complète", file=sys.stderr)

    with open(sortie / "sources.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f, delimiter=";", lineterminator="\n")
        w.writerow(["source", "producteur", "url", "licence", "mise_a_jour", "consulte_le"])
        w.writerow(["Code officiel géographique 2026", "Insee", COG, "Licence Ouverte", COG_PUBLIE, consulte])
        w.writerow(["Base officielle des codes postaux", "La Poste", LA_POSTE, "Licence Ouverte", date_poste, consulte])
        w.writerow(["Fiches climatologiques 1991-2020", "Météo-France", STATIONS, "Licence Ouverte 2.0", edition, consulte])
        w.writerow(["Service altimétrique, RGE ALTI", "IGN (Géoplateforme)", ALTI, "Licence Ouverte 2.0", consulte, consulte])
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
