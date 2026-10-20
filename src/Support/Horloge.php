<?php

namespace Src\Support;

use Carbon\CarbonImmutable;

/**
 * La seule lectrice de l'horloge pour une date métier (D-42), à Paris : reçue
 * par injection, figée dans les tests par `travelTo`. Le moteur, lui, ne la lit
 * jamais : la date du jour lui est passée (TS-4).
 */
final class Horloge
{
    private const FUSEAU = 'Europe/Paris';

    /** Aujourd'hui à minuit, heure de Paris. */
    public function aujourdhui(): \DateTimeImmutable
    {
        return CarbonImmutable::today(self::FUSEAU)->toDateTimeImmutable();
    }

    public function maintenant(): \DateTimeImmutable
    {
        return CarbonImmutable::now(self::FUSEAU)->toDateTimeImmutable();
    }
}
