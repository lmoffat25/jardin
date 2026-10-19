#!/usr/bin/env python3
"""Vérifie la couverture contrat <-> sprints du plan jardin, et la taille des sprints."""
import re,sys
D='~/jardin/docs/plan/'
c=open(D+'validation-contract.md').read(); t=open(D+'tasks.md').read()
err=[];warn=[]
defin=sorted({int(n) for n in re.findall(r'(?m)^\*\*V(\d+) — ',c)})
barrees={int(n) for n in re.findall(r'(?m)^\*\*~~V(\d+)',c)}
carte_txt=c[c.index('## Carte de couverture'):]
carte={}
for sid,a in re.findall(r'(?m)^\| (\w+) \| (V[^|]*?) \|$',carte_txt):
    carte[sid]=[int(x) for x in re.findall(r'V(\d+)',a)]
sec=list(re.finditer(r'(?m)^## Sprint (\S+) — .*$',t))
spr={}
for i,m in enumerate(sec):
    body=t[m.start():sec[i+1].start() if i+1<len(sec) else t.index('## Revue des coûts')]
    g=lambda k: re.search(r'(?m)^\*\*%s :\*\* (.*)$'%k,body)
    ass=[int(x) for x in re.findall(r'V(\d+)',g('Assertions').group(1))]
    lst=lambda k: [] if g(k).group(1).strip()=='aucun' else re.findall(r'`([^`]+)`',g(k).group(1))
    f,cl=lst('Fichiers'),lst('Classes de test')
    ta=re.search(r'<!-- taille : (\d+) assertions?, (\d+) fichiers?, (\d+) classes? de test -->',body)
    att=g('En attente'); spr[m.group(1)]=dict(ass=ass,f=f,c=cl,taille=tuple(map(int,ta.groups())) if ta else None,att=att.group(1) if att else '',body=body)
# 1. ensembles
N=max(defin)
manq=[n for n in range(1,N+1) if n not in defin]
if manq: err.append(f'numéros sans assertion définie : {manq}')
if set(carte)!=set(spr): err.append(f'sprints carte/tasks différents : {set(carte)^set(spr)}')
couv={}
for sid,a in carte.items():
    for n in a: couv.setdefault(n,[]).append(sid)
for n in defin:
    if n in barrees: continue
    if n not in couv: err.append(f'V{n} : aucun sprint')
for n in couv:
    if n not in defin: err.append(f'V{n} : dans la carte mais non définie')
for sid in spr:
    if not carte.get(sid): err.append(f'{sid} : aucune assertion')
# 2. en-têtes
for sid,d in spr.items():
    if sorted(d['ass'])!=sorted(carte.get(sid,[])): err.append(f'{sid} : en-tête {d["ass"]} ≠ carte {carte.get(sid)}')
    real=(len(d['ass']),len(d['f']),len(d['c']))
    if d['taille']!=real: err.append(f'{sid} : commentaire taille {d["taille"]} ≠ listes {real}')
    if real[0]>5 or real[1]>8 or real[2]>4: err.append(f'{sid} : hors budget P5 {real}')
    # chemins comptés cités mais non listés (avertissement)
    cites=set(re.findall(r'`((?:src|app|bootstrap|config|database/migrations|database/seeders|resources|scripts|\.github)/[^`\s]+\.\w+)`',d['body']))
    for p in sorted(cites-set(d['f'])-set(d['c'])):
        if '/tests/' in p: continue
        warn.append(f'{sid} : {p} cité, non listé')
# sortie
print(f'Assertions définies : {len(defin)} (V1 à V{N}), barrées : {len(barrees)}')
print(f'Sprints : {len(spr)} ; carte : {len(carte)} lignes')
print('sprint  A  F  T  assertions                     en attente')
for sid,d in spr.items():
    print(f'{sid:<6} {len(d["ass"]):>2} {len(d["f"]):>2} {len(d["c"]):>2}  {", ".join("V%d"%n for n in d["ass"]):<30} {d["att"]}')
multi={n:s for n,s in couv.items() if len(s)>1}
print(f'Assertions portées par plusieurs sprints : {multi or "aucune"}')
for w in warn: print('AVERTISSEMENT', w)
for e in err: print('ERREUR', e)
print('RÉSULTAT :', 'OK — chaque assertion a au moins un sprint, chaque sprint au moins une assertion, tailles conformes' if not err else f'{len(err)} erreur(s)')
sys.exit(1 if err else 0)
