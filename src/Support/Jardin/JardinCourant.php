<?php

namespace Src\Support\Jardin;

/**
 * Le jardin dans lequel le code s'exécute (A11) : un contexte explicite, posé
 * par le middleware HTTP, par le job à son démarrage, par la commande Artisan
 * depuis son argument. Jamais lu de la session, jamais `null` : un `null`
 * silencieux désactiverait le filtre et rendrait tout visible.
 *
 * Instance de portée du conteneur : oubliée à chaque requête et à chaque job.
 */
final class JardinCourant
{
    private ?string $jardinId = null;

    public function positionner(string $jardinId): void
    {
        $this->jardinId = $jardinId;
    }

    /** @throws JardinCourantAbsent */
    public function id(): string
    {
        return $this->jardinId ?? throw new JardinCourantAbsent;
    }

    public function estPositionne(): bool
    {
        return $this->jardinId !== null;
    }
}
