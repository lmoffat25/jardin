<?php

namespace Src\Jardin\Identification;

use Illuminate\Support\Str;
use Src\Jardin\Domaine\Plante;

/**
 * La plante d'un code court, dans le jardin courant seulement (RG-13, RG-21) :
 * le filtre du jardin vient du scope de `Plante`. Les codes sont écrits en
 * majuscules (`AttribuerCodeCourt`) : la saisie l'est aussi, la casse ne
 * compte pas. Une plante archivée garde son code, et sa fiche.
 */
final class ResoudreCodeCourt
{
    public function plante(string $code): ?Plante
    {
        return Plante::query()->where('code_court', Str::upper($code))->first();
    }
}
