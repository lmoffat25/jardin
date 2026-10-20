<?php

namespace Src\Journal\Saisie;

use Illuminate\Validation\ValidationException;

/**
 * Une ligne de saisie que le jardin courant ne connaît pas : sa plante n'y est
 * pas, ou son identifiant d'intervention existe dans un autre jardin. Les deux
 * reçoivent la même réponse, octet pour octet (D-08, RG-21) : un `422` avec
 * une erreur `lignes.N` par ligne, rendu comme toute erreur de validation.
 */
final class Introuvable extends ValidationException
{
    public const MESSAGE = 'Plante introuvable dans ce jardin.';

    /** @param  list<int>  $rangs */
    public static function lignes(array $rangs): self
    {
        sort($rangs);

        return self::withMessages(array_fill_keys(array_map(fn (int $rang) => "lignes.{$rang}", $rangs), self::MESSAGE));
    }
}
