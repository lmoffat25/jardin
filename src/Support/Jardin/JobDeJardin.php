<?php

namespace Src\Support\Jardin;

use Closure;
use LogicException;

/**
 * Un job qui travaille dans un jardin (A20) : il porte son `jardinId` dans son
 * payload, et son middleware positionne le jardin courant avant `handle()`.
 * Sans `jardinId`, le job échoue avant `handle()`, sans rien écrire.
 *
 * Le job reçoit son propre `JardinCourant`, et celui d'avant est rendu à la
 * fin : un job lancé en synchrone ne change pas le jardin du code qui l'a lancé.
 * Un job qui a d'autres middlewares les ajoute à `middleware()` (alias du trait).
 */
trait JobDeJardin
{
    public string $jardinId;

    /** @return list<Closure(object, Closure(object): mixed): mixed> */
    public function middleware(): array
    {
        return [$this->dansSonJardin(...)];
    }

    /**
     * @param  Closure(object): mixed  $suivant
     *
     * @throws LogicException
     */
    public function dansSonJardin(object $job, Closure $suivant): mixed
    {
        if (($this->jardinId ?? '') === '') {
            throw new LogicException(static::class.' ne porte pas de jardinId : il ne sait pas dans quel jardin écrire (A20).');
        }

        $avant = app(JardinCourant::class);
        $courant = new JardinCourant;
        $courant->positionner($this->jardinId);
        app()->instance(JardinCourant::class, $courant);

        try {
            return $suivant($job);
        } finally {
            app()->instance(JardinCourant::class, $avant);
        }
    }
}
