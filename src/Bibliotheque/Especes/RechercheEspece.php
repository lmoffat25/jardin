<?php

namespace Src\Bibliotheque\Especes;

use Illuminate\Support\Collection;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\Domaine\RegleReference;

/**
 * Chercher une espèce de la bibliothèque (CDC §5.1, écran B7) par son nom
 * commun ou latin, sans égard à la casse ni aux accents (`unaccent`, D-94).
 * Les noms qui commencent par la recherche viennent d'abord, puis les plus
 * courts.
 */
final class RechercheEspece
{
    public const RESULTATS = 20;

    /** @return Collection<int, Espece> */
    public function chercher(string $q): Collection
    {
        // Les jokers de LIKE se lisent comme des caractères.
        $motif = addcslashes($q, '\\%_');
        $noms = 'unaccent(nom_commun) ILIKE unaccent(?) OR unaccent(coalesce(nom_latin, \'\')) ILIKE unaccent(?)';

        return Espece::query()
            ->select(['id', 'cle', 'nom_commun', 'nom_latin', 'categorie'])
            ->whereRaw("({$noms})", ["%{$motif}%", "%{$motif}%"])
            ->orderByRaw("CASE WHEN {$noms} THEN 0 ELSE 1 END", ["{$motif}%", "{$motif}%"])
            ->orderByRaw('length(nom_commun)')
            ->orderBy('nom_commun')
            ->limit(self::RESULTATS)
            ->get();
    }

    /**
     * Les variantes de conduite des espèces données (RG-51), tirées de leurs
     * règles en vigueur : une espèce sans variante n'a pas de clé.
     *
     * @param  list<int>  $especes
     * @return array<int, list<string>> espèce => variantes, dans l'ordre alphabétique
     */
    public function variantes(array $especes): array
    {
        $variantes = [];
        RegleReference::query()
            ->whereIn('espece_id', $especes)
            ->whereNull('remplacee_le')
            ->whereNotNull('variante')
            ->distinct()
            ->orderBy('variante')
            ->get(['espece_id', 'variante'])
            ->each(function (RegleReference $r) use (&$variantes) {
                $variantes[$r->espece_id][] = (string) $r->variante;
            });

        return $variantes;
    }
}
