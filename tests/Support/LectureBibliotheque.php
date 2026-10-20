<?php

namespace Tests\Support;

use DateTimeImmutable;
use Src\Bibliotheque\Chargement\LireBibliotheque;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Periode;

/**
 * La bibliothèque telle que l'utilisateur la reçoit (B8, V68, V69) : lue par
 * le chargeur, chaque fenêtre projetée par le moteur pour chacun des cinq
 * profils du jeu de référence. Demande une base où `ReferenceSeeder` a tourné.
 *
 * @phpstan-import-type EspeceLue from LireBibliotheque
 * @phpstan-import-type FenetreLue from LireBibliotheque
 */
final class LectureBibliotheque
{
    /** Les années de début projetées : une ordinaire, une qui finit sur une bissextile, une bissextile. */
    public const ANNEES = [2026, 2027, 2028];

    /**
     * Les espèces du dossier, et la famille de chaque type de leurs règles.
     *
     * @return array{0: list<EspeceLue>, 1: array<string, string>}
     */
    public static function lire(string $dossier): array
    {
        /** @var array<string, string> $types */
        $types = TypeIntervention::query()->withoutGlobalScopes()->whereNull('jardin_id')->pluck('famille', 'libelle')->all();

        return [(new LireBibliotheque)->lire($dossier, $types), $types];
    }

    /** @return array<string, int> libellé du profil => décalage en jours */
    public static function profils(): array
    {
        /** @var array<string, int> */
        return ProfilClimatique::query()->orderBy('ordre_affichage')->pluck('decalage_jours', 'libelle')->all();
    }

    /** @param FenetreLue $fenetre */
    public static function projeter(array $fenetre, int $annee, int $decalage): Periode
    {
        return (new Fenetre(
            self::jourMois($fenetre['debut']),
            self::jourMois($fenetre['fin']),
            self::jourMois($fenetre['optimal'][0] ?? null),
            self::jourMois($fenetre['optimal'][1] ?? null),
            self::jourMois($fenetre['butoirs'][0] ?? null),
            self::jourMois($fenetre['butoirs'][1] ?? null),
            $fenetre['libelle'],
        ))->projeter($annee, Decalage::jours($decalage));
    }

    /**
     * Les jours de la période, un par un.
     *
     * @return iterable<DateTimeImmutable>
     */
    public static function jours(Periode $periode): iterable
    {
        for ($jour = $periode->debut; $jour <= $periode->fin; $jour = $jour->modify('+1 day')) {
            yield $jour;
        }
    }

    /** Le jour tombe-t-il entre deux JJ/MM, bornes comprises, passage du 1er janvier compris ? */
    public static function entre(DateTimeImmutable $jour, string $debut, string $fin): bool
    {
        $md = $jour->format('m/d');
        [$a, $b] = [self::md($debut), self::md($fin)];

        return $a <= $b ? $a <= $md && $md <= $b : $md >= $a || $md <= $b;
    }

    /** @return ($jourMois is null ? null : JourMois) */
    private static function jourMois(?string $jourMois): ?JourMois
    {
        if ($jourMois === null) {
            return null;
        }
        [$jour, $mois] = explode('/', $jourMois);

        return new JourMois((int) $jour, (int) $mois);
    }

    /** JJ/MM en MM/JJ, qui se compare comme du texte. */
    private static function md(string $jourMois): string
    {
        [$jour, $mois] = explode('/', $jourMois);

        return "{$mois}/{$jour}";
    }
}
