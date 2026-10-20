<?php

declare(strict_types=1);

namespace Tests\Moteur\Temoins;

use Src\Echeances\Moteur\Calendrier;
use Src\Echeances\Moteur\Fenetre;
use Src\Echeances\Moteur\Historique;
use Src\Echeances\Moteur\IntervalleSaisonnier;
use Src\Echeances\Moteur\JourMois;
use Src\Echeances\Moteur\Regle;

/**
 * Règles témoins des tests du moteur. Ce sont des jeux de test, pas des données
 * agronomiques : elles ne sont jamais copiées dans `bibliotheque/` (RG-28, B1).
 */
final class JeuTemoin
{
    /** Taille du pommier : fenêtre 01/12 → 31/03, optimale 01/02 → 15/03. */
    public static function pommier(
        int $periodicite = 1,
        string $effetLe = '2026-06-01',
        string $confiance = 'verifiee',
        string $origine = 'bibliotheque',
    ): Regle {
        return self::regle(
            'regle-pommier',
            'plante-pommier',
            new Fenetre(new JourMois(1, 12), new JourMois(31, 3), new JourMois(1, 2), new JourMois(15, 3)),
            $periodicite,
            $effetLe,
            $confiance,
            $origine,
        );
    }

    /** Taille du cerisier : fenêtre 01/08 → 30/09, optimale par défaut jusqu'au 31/08. */
    public static function cerisier(string $optimalDebut = '01/08', string $optimalFin = '31/08'): Regle
    {
        return self::regle(
            'regle-cerisier',
            'plante-cerisier',
            new Fenetre(new JourMois(1, 8), new JourMois(30, 9), self::jourMois($optimalDebut), self::jourMois($optimalFin)),
        );
    }

    /** Taille de la haie : fenêtre 16/08 → 15/03, butoirs 16/08 et 15/03 (nidification). */
    public static function haie(): Regle
    {
        return self::regle(
            'regle-haie',
            'plante-haie',
            new Fenetre(new JourMois(16, 8), new JourMois(15, 3), butoirDebut: new JourMois(16, 8), butoirFin: new JourMois(15, 3)),
        );
    }

    /**
     * Traitement en deux passages (RG-36) : chute des feuilles 01/11 → 30/11,
     * fin d'hiver 01/02 → 28/02. `$fenetres` remplace les deux fenêtres.
     *
     * @param  list<Fenetre>|null  $fenetres
     */
    public static function deuxPassages(int $periodicite = 1, string $effetLe = '2026-06-01', ?array $fenetres = null): Regle
    {
        return self::regle(
            'regle-deux-passages',
            'plante-deux-passages',
            $fenetres ?? [
                new Fenetre(new JourMois(1, 11), new JourMois(30, 11), libelle: 'chute des feuilles', ordre: 1),
                new Fenetre(new JourMois(1, 2), new JourMois(28, 2), libelle: 'fin d\'hiver', ordre: 2),
            ],
            $periodicite,
            $effetLe,
        );
    }

    /**
     * Tonte de la pelouse (R1) : 7 jours au printemps (mars–juin), 14 l'été
     * (juillet–août), 10 à l'automne (septembre–novembre), 0 l'hiver
     * (décembre–février). `$intervalles` remplace les quatre saisons.
     *
     * @param  list<IntervalleSaisonnier>|null  $intervalles
     */
    public static function tonte(?array $intervalles = null): Regle
    {
        return new Regle(
            id: 'regle-tonte',
            planteId: 'plante-pelouse',
            typeId: 2,
            typeRegle: 'R1',
            periodiciteAnnees: 1,
            fenetres: [],
            intervalles: $intervalles ?? [
                new IntervalleSaisonnier(3, 6, 7),
                new IntervalleSaisonnier(7, 8, 14),
                new IntervalleSaisonnier(9, 11, 10),
                new IntervalleSaisonnier(12, 2, 0),
            ],
            confiance: 'indicative',
            origine: 'bibliotheque',
            effetLe: self::jour('2026-06-01'),
        );
    }

    /** L'historique d'une seule tonte de la pelouse témoin, ou aucun. */
    public static function tondueLe(?string $iso): Historique
    {
        return $iso === null
            ? Historique::vide()
            : Historique::depuis([['plante_id' => 'plante-pelouse', 'type_id' => 2, 'fait_le' => self::jour($iso)]]);
    }

    /** Une date du moteur, à minuit à Paris, depuis `AAAA-MM-JJ`. */
    public static function jour(string $iso): \DateTimeImmutable
    {
        [$annee, $mois, $jour] = array_map('intval', explode('-', $iso));

        return Calendrier::date($annee, $mois, $jour);
    }

    /** @param  Fenetre|list<Fenetre>  $fenetres */
    private static function regle(
        string $id,
        string $planteId,
        Fenetre|array $fenetres,
        int $periodicite = 1,
        string $effetLe = '2026-06-01',
        string $confiance = 'verifiee',
        string $origine = 'bibliotheque',
    ): Regle {
        return new Regle(
            id: $id,
            planteId: $planteId,
            typeId: 1,
            typeRegle: 'R2',
            periodiciteAnnees: $periodicite,
            fenetres: is_array($fenetres) ? $fenetres : [$fenetres],
            intervalles: [],
            confiance: $confiance,
            origine: $origine,
            effetLe: self::jour($effetLe),
        );
    }

    private static function jourMois(string $jjmm): JourMois
    {
        [$jour, $mois] = array_map('intval', explode('/', $jjmm));

        return new JourMois($jour, $mois);
    }
}
