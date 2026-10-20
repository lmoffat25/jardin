<?php

namespace Src\Echeances\Reglages;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Moteur\Decalage;
use Src\Echeances\Moteur\Fenetre as FenetreDuMoteur;
use Src\Echeances\Moteur\JourMois;

/**
 * Une fenêtre saisie par l'utilisateur (B9, V104), dans la forme de la
 * bibliothèque : `{debut, fin, optimal?, butoirs?, libelle?}`, des dates
 * « JJ/MM » qui existent une année bissextile (D-09) ; l'optimale est une
 * paire contenue dans la fenêtre, passage du 1er janvier compris (B-T6) ; les
 * butoirs sont une paire `[début|null, fin|null]` dont l'un au moins est
 * donné. Projetée sous chacun des cinq profils climatiques puis rognée à ses
 * butoirs, elle garde au moins un jour : sinon le moteur rendrait une période
 * vide au jardin de ce profil.
 *
 * Partagée par la correction (9a) et les règles libres (9b).
 *
 * @phpstan-type FenetreSaisie array{debut: string, fin: string, optimal?: list<string>|null, butoirs?: list<string|null>|null, libelle?: string|null}
 */
final class ValiderFenetres implements ValidationRule
{
    private const CLES = ['debut', 'fin', 'optimal', 'butoirs', 'libelle'];

    /** Deux années de projection, dont une bissextile : le 29/02 change un rognage. */
    private const ANNEES = [2027, 2028];

    /** @var array<string, int>|null décalage par libellé de profil, lu une fois */
    private ?array $profils = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $erreur = $this->erreur($value);
        if ($erreur !== null) {
            $fail($erreur);
        }
    }

    /**
     * La fenêtre validée, en colonnes de la table `fenetres`.
     *
     * @param  FenetreSaisie  $fenetre
     * @return array<string, int|string|null>
     */
    public static function colonnes(array $fenetre, int $ordre): array
    {
        $colonnes = ['ordre' => $ordre, 'libelle' => $fenetre['libelle'] ?? null];
        $bornes = [
            'debut' => $fenetre['debut'],
            'fin' => $fenetre['fin'],
            'optimal_debut' => $fenetre['optimal'][0] ?? null,
            'optimal_fin' => $fenetre['optimal'][1] ?? null,
            'butoir_debut' => $fenetre['butoirs'][0] ?? null,
            'butoir_fin' => $fenetre['butoirs'][1] ?? null,
        ];
        foreach ($bornes as $borne => $jourMois) {
            $jm = $jourMois === null ? null : self::jourMois($jourMois);
            $colonnes["{$borne}_jour"] = $jm?->jour;
            $colonnes["{$borne}_mois"] = $jm?->mois;
        }

        return $colonnes;
    }

    /** Le message de la première faute, ou null. */
    private function erreur(mixed $fenetre): ?string
    {
        if (! is_array($fenetre) || array_is_list($fenetre)) {
            return 'Une fenêtre se donne par son début et sa fin.';
        }
        $inconnues = array_diff(array_keys($fenetre), self::CLES);
        if ($inconnues !== []) {
            return 'Champ inconnu dans la fenêtre : '.implode(', ', $inconnues).'.';
        }
        $debut = self::jourMois($fenetre['debut'] ?? null);
        $fin = self::jourMois($fenetre['fin'] ?? null);
        if ($debut === null || $fin === null) {
            return 'Le début et la fin sont des dates JJ/MM qui existent.';
        }

        $optimal = [null, null];
        if (($fenetre['optimal'] ?? null) !== null) {
            $paire = self::paire($fenetre['optimal']);
            $optimal = $paire === null ? [null, null] : [self::jourMois($paire[0]), self::jourMois($paire[1])];
            if ($optimal[0] === null || $optimal[1] === null) {
                return 'La période optimale est une paire de dates JJ/MM qui existent.';
            }
            if (self::rang($optimal[0], $debut) > self::rang($optimal[1], $debut) || self::rang($optimal[1], $debut) > self::rang($fin, $debut)) {
                return 'La période optimale tient dans la fenêtre.';
            }
        }

        $butoirs = [null, null];
        if (($fenetre['butoirs'] ?? null) !== null) {
            $paire = self::paire($fenetre['butoirs']);
            if ($paire === null || $paire === [null, null]) {
                return 'Les butoirs sont une paire [début, fin], l\'un au moins donné.';
            }
            $butoirs = [$paire[0] === null ? null : self::jourMois($paire[0]), $paire[1] === null ? null : self::jourMois($paire[1])];
            if (($paire[0] !== null && $butoirs[0] === null) || ($paire[1] !== null && $butoirs[1] === null)) {
                return 'Un butoir est une date JJ/MM qui existe.';
            }
        }

        $libelle = $fenetre['libelle'] ?? null;
        if ($libelle !== null && (! is_string($libelle) || mb_strlen($libelle) > 80)) {
            return 'Le libellé d\'une fenêtre tient en 80 caractères.';
        }

        $projetable = new FenetreDuMoteur($debut, $fin, $optimal[0], $optimal[1], $butoirs[0], $butoirs[1]);
        foreach ($this->profils() as $profil => $jours) {
            foreach (self::ANNEES as $annee) {
                if ($projetable->projeter($annee, Decalage::jours($jours))->estVide()) {
                    return "Rognée à ses butoirs, la fenêtre n'a plus aucun jour pour un jardin « {$profil} ».";
                }
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function profils(): array
    {
        if ($this->profils === null) {
            /** @var array<string, int> $profils */
            $profils = ProfilClimatique::query()->orderBy('ordre_affichage')->pluck('decalage_jours', 'libelle')->all();
            $this->profils = $profils;
        }

        return $this->profils;
    }

    /** @return array{0: mixed, 1: mixed}|null */
    private static function paire(mixed $valeur): ?array
    {
        return is_array($valeur) && array_is_list($valeur) && count($valeur) === 2 ? [$valeur[0], $valeur[1]] : null;
    }

    /** « JJ/MM » qui existe une année bissextile, ou null. */
    private static function jourMois(mixed $valeur): ?JourMois
    {
        if (! is_string($valeur) || preg_match('#^(\d{2})/(\d{2})$#', $valeur, $p) !== 1 || ! checkdate((int) $p[2], (int) $p[1], 2024)) {
            return null;
        }

        return new JourMois((int) $p[1], (int) $p[2]);
    }

    /** Le rang du jour compté depuis l'origine, sur une année de 366 jours. */
    private static function rang(JourMois $jour, JourMois $origine): int
    {
        $z = fn (JourMois $j): int => (int) (new \DateTimeImmutable(sprintf('2024-%02d-%02d', $j->mois, $j->jour)))->format('z');

        return ($z($jour) - $z($origine) + 366) % 366;
    }
}
