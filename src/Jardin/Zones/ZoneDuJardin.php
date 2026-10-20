<?php

namespace Src\Jardin\Zones;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Src\Jardin\Domaine\Zone;

/**
 * Une zone choisie dans une saisie (D-53) : une zone du jardin courant, par
 * son filtre, et non archivée. Tout choix de zone passe par cette règle. Le
 * message ne distingue pas une zone de B d'une zone qui n'existe pas.
 */
final class ZoneDuJardin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $trouvee = is_string($value) && Str::isUuid($value)
            && Zone::query()->whereKey($value)->whereNull('archivee_le')->exists();

        if (! $trouvee) {
            $fail('Zone introuvable dans ce jardin.');
        }
    }
}
