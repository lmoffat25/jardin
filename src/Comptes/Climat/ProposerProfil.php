<?php

namespace Src\Comptes\Climat;

use RuntimeException;
use Src\Comptes\Domaine\ProfilClimatique;
use Symfony\Component\Yaml\Yaml;

/**
 * Le profil climatique proposé pour une commune (RG-52, étapes 1 et 3) :
 * « Montagne » au-dessus du seuil d'altitude ; sinon le profil dont le
 * décalage est le plus proche du décalage calculé, Montagne exclue (le CDC
 * §5.4 la réserve au-dessus de 600 m) ; à égalité, le plus près de zéro, qui
 * déplace le moins les dates (D-85).
 *
 * Les seuils viennent de `bibliotheque/climat/parametres.yaml` (D-66).
 */
final class ProposerProfil
{
    /** @param array<int, int> $profils décalage en jours, par id de profil */
    public function __construct(
        private readonly array $profils,
        private readonly int $montagneId,
        private readonly int $montagneAuDessusDe,
        private readonly int $demanderDe,
        private readonly int $demanderA,
    ) {}

    /**
     * Depuis les paramètres lus et les profils du jeu de référence.
     *
     * @param  array<mixed>  $parametres
     */
    public static function depuis(array $parametres): self
    {
        /** @var array<int, int> $profils */
        $profils = ProfilClimatique::query()->pluck('decalage_jours', 'id')->all();
        $montagne = $parametres['montagne'] ?? null;
        $demander = $parametres['demander_altitude'] ?? null;
        if (! is_array($montagne) || ! is_array($demander)) {
            throw new RuntimeException('parametres.yaml : « montagne » ou « demander_altitude » absent');
        }

        $entier = static function (mixed $valeur, string $nom): int {
            if (! is_int($valeur)) {
                throw new RuntimeException("parametres.yaml : {$nom} n'est pas un entier");
            }

            return $valeur;
        };
        $montagneId = $entier($montagne['profil_id'] ?? null, 'montagne.profil_id');
        if (! array_key_exists($montagneId, $profils)) {
            throw new RuntimeException("profil Montagne {$montagneId} absent du jeu de référence");
        }

        return new self(
            $profils,
            $montagneId,
            $entier($montagne['au_dessus_de_m'] ?? null, 'montagne.au_dessus_de_m'),
            $entier($demander['de_m'] ?? null, 'demander_altitude.de_m'),
            $entier($demander['a_m'] ?? null, 'demander_altitude.a_m'),
        );
    }

    /** Depuis les paramètres de la bibliothèque. */
    public static function duReferentiel(): self
    {
        /** @var array<mixed> $parametres */
        $parametres = Yaml::parseFile(base_path('bibliotheque/climat/parametres.yaml'));

        return self::depuis($parametres);
    }

    public function profil(int $decalageJours, int $altitudeM): int
    {
        if ($altitudeM > $this->montagneAuDessusDe) {
            return $this->montagneId;
        }

        $retenu = null;
        foreach ($this->profils as $id => $decalage) {
            if ($id === $this->montagneId) {
                continue;
            }
            $distance = abs($decalage - $decalageJours);
            if ($retenu === null
                || $distance < $retenu[1]
                || ($distance === $retenu[1] && abs($decalage) < abs($this->profils[$retenu[0]]))) {
                $retenu = [$id, $distance];
            }
        }
        if ($retenu === null) {
            throw new RuntimeException('aucun profil hors Montagne');
        }

        return $retenu[0];
    }

    /** L'altitude d'une commune n'est pas celle de chaque jardin (RG-52, étape 1). */
    public function demanderAltitude(int $altitudeM): bool
    {
        return $altitudeM >= $this->demanderDe && $altitudeM <= $this->demanderA;
    }
}
