<?php

namespace Src\Jardin\Domaine;

use Illuminate\Database\Eloquent\Model;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Le dernier numéro de code court donné dans un jardin pour un préfixe (S8,
 * D-03). Clé composée `(jardin_id, prefixe)` : Eloquent ne sait pas la
 * sauvegarder, `AttribuerCodeCourt` l'écrit par requête.
 *
 * @property string $prefixe
 * @property int $dernier
 */
class CompteurCodeCourt extends Model
{
    use AppartientAuJardin;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'compteurs_codes_courts';

    protected $primaryKey = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['dernier' => 'integer'];
    }
}
