<?php

namespace Src\Support\Jardin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Src\Comptes\Domaine\Jardin;

/**
 * Un modèle métier, rattaché à un jardin (D1, RG-22) : ses lectures passent
 * par `JardinScope`, et une création reçoit le jardin courant. Écrire dans un
 * autre jardin que le courant est une erreur de programmation.
 *
 * @property string $jardin_id
 */
trait AppartientAuJardin
{
    public static function bootAppartientAuJardin(): void
    {
        static::addGlobalScope(new JardinScope);

        static::creating(function (Model $modele): void {
            $courant = app(JardinCourant::class)->id();
            $fourni = $modele->getAttribute('jardin_id');
            if ($fourni !== null && $fourni !== $courant) {
                throw new LogicException('Écriture dans le jardin '.json_encode($fourni)." depuis le jardin courant {$courant}.");
            }
            $modele->setAttribute('jardin_id', $courant);
        });
    }

    /** @return BelongsTo<Jardin, $this> */
    public function jardin(): BelongsTo
    {
        return $this->belongsTo(Jardin::class);
    }
}
