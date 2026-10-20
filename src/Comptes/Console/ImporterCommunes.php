<?php

namespace Src\Comptes\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Src\Comptes\Climat\ProposerProfil;
use Src\Comptes\Domaine\Commune;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Importe le référentiel des communes (RG-52, §8.6, D-66, D-86) depuis un
 * dossier au format de `bibliotheque/climat/` : `communes.csv`, `stations.csv`
 * et `parametres.yaml`, produits hors CI par `scripts/climat/generer-communes.py`.
 * Aucun appel réseau : tout est dans les fichiers.
 *
 * Pour chaque commune : la station la plus proche dont les normales 1991-2020
 * sont complètes, sa température des mois retenus ramenée à l'altitude de la
 * commune, l'écart à la station de référence, le décalage, le profil proposé.
 *
 * Idempotent : une commune du fichier est écrite ou réécrite ; une commune
 * absente est retirée, sauf si un jardin la désigne. Tout ou rien : une ligne
 * illisible n'écrit rien.
 */
final class ImporterCommunes extends Command
{
    private const COLONNES_COMMUNES = ['code_insee', 'nom', 'codes_postaux', 'latitude', 'longitude', 'altitude_m'];

    private const COLONNES_STATIONS = ['num', 'nom', 'latitude', 'longitude', 'altitude_m', 'periode_complete',
        't01', 't02', 't03', 't04', 't05', 't06', 't07', 't08', 't09', 't10', 't11', 't12'];

    protected $signature = 'jardin:importer-communes {dossier=bibliotheque/climat : Le dossier du référentiel}';

    protected $description = 'Importe le référentiel des communes et calcule le profil proposé de chacune (RG-52)';

    public function handle(): int
    {
        $dossier = rtrim((string) $this->argument('dossier'), '/');

        try {
            $lignes = $this->calculer($dossier);
        } catch (RuntimeException|ParseException $e) {
            $this->error("Rien n'est importé : {$e->getMessage()}");

            return self::FAILURE;
        }

        DB::transaction(function () use ($lignes) {
            foreach (array_chunk($lignes, 1000) as $lot) {
                Commune::query()->upsert($lot, ['code_insee']);
            }
            /** @var list<string> $existantes */
            $existantes = Commune::query()->pluck('code_insee')->all();
            $absentes = array_diff($existantes, array_column($lignes, 'code_insee'));
            foreach (array_chunk($absentes, 1000) as $lot) {
                Commune::query()
                    ->whereIn('code_insee', $lot)
                    ->whereNotExists(fn (Builder $q) => $q->from('jardins')->whereColumn('jardins.commune_insee', 'communes.code_insee'))
                    ->delete();
            }
        });
        $this->info(count($lignes).' communes importées.');

        return self::SUCCESS;
    }

    /** @return list<array<string, int|string|float>> */
    private function calculer(string $dossier): array
    {
        $parametres = Yaml::parseFile("{$dossier}/parametres.yaml");
        if (! is_array($parametres)) {
            throw new RuntimeException('parametres.yaml illisible');
        }
        $mois = $parametres['mois'] ?? null;
        $reference = $parametres['station_reference'] ?? null;
        $joursParDegre = $parametres['jours_par_degre'] ?? null;
        $gradient = $parametres['gradient_c_par_100_m'] ?? null;
        if (! is_string($reference) || ! is_array($mois) || $mois === [] || ! is_numeric($joursParDegre) || ! is_numeric($gradient)) {
            throw new RuntimeException('parametres.yaml : station_reference, mois, jours_par_degre ou gradient_c_par_100_m absent');
        }
        $proposer = ProposerProfil::depuis($parametres);

        $stations = [];
        foreach (self::lire("{$dossier}/stations.csv", self::COLONNES_STATIONS) as $n => $s) {
            if ($s['periode_complete'] !== '1') {
                continue;
            }
            $temperatures = array_map(fn (mixed $m) => self::nombre($s, sprintf('t%02d', is_int($m) ? $m : 0), $n), $mois);
            $stations[$s['num']] = [
                'nom' => $s['nom'],
                'lat' => deg2rad(self::nombre($s, 'latitude', $n)),
                'lon' => deg2rad(self::nombre($s, 'longitude', $n)),
                'alt' => self::nombre($s, 'altitude_m', $n),
                't' => array_sum($temperatures) / count($temperatures),
            ];
        }
        if (! isset($stations[$reference])) {
            throw new RuntimeException("station de référence {$reference} absente ou incomplète");
        }
        $tReference = $stations[$reference]['t'];

        $lignes = [];
        foreach (self::lire("{$dossier}/communes.csv", self::COLONNES_COMMUNES) as $n => $c) {
            $code = $c['code_insee'];
            $codesPostaux = preg_split('/\s+/', trim($c['codes_postaux']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (! preg_match('/^(\d{5}|2[AB]\d{3})$/', $code) || $codesPostaux === []
                || preg_grep('/^\d{5}$/', $codesPostaux, PREG_GREP_INVERT) !== []) {
                throw new RuntimeException("communes.csv ligne {$n} : code de commune ou code postal illisible");
            }
            $altitude = (int) round(self::nombre($c, 'altitude_m', $n));
            $station = self::plusProche($stations, deg2rad(self::nombre($c, 'latitude', $n)), deg2rad(self::nombre($c, 'longitude', $n)));

            // Le gradient vertical ramène la station à l'altitude de la commune (Q24).
            $ecart = $station['t'] + ($station['alt'] - $altitude) * (float) $gradient / 100 - $tReference;
            $decalage = (int) round(-$ecart * (float) $joursParDegre);

            $lignes[] = [
                'code_insee' => $code,
                'nom' => $c['nom'],
                'nom_recherche' => Str::limit(Str::slug($c['nom'], ' '), 120, ''),
                'codes_postaux' => implode(' ', array_unique($codesPostaux)),
                'altitude_m' => $altitude,
                'station' => $station['nom'],
                'ecart_c' => round($ecart, 1),
                'decalage_jours' => $decalage,
                'profil_propose_id' => $proposer->profil($decalage, $altitude),
            ];
        }

        return $lignes;
    }

    /**
     * La station la plus proche, par la distance sur une sphère approchée
     * localement par un plan : l'erreur est négligeable à l'échelle d'un
     * département, et le calcul tient sur 35 000 communes.
     *
     * @param  array<string, array{nom: string, lat: float, lon: float, alt: float, t: float}>  $stations
     * @return array{nom: string, lat: float, lon: float, alt: float, t: float}
     */
    private static function plusProche(array $stations, float $lat, float $lon): array
    {
        $cos = cos($lat);
        $retenue = null;
        $meilleure = INF;
        foreach ($stations as $station) {
            $d = ($station['lat'] - $lat) ** 2 + (($station['lon'] - $lon) * $cos) ** 2;
            if ($d < $meilleure) {
                $meilleure = $d;
                $retenue = $station;
            }
        }
        if ($retenue === null) {
            throw new RuntimeException('aucune station complète');
        }

        return $retenue;
    }

    /**
     * Les lignes d'un CSV séparé par des points-virgules, dont l'en-tête est
     * exactement celui attendu ; indexées par leur numéro de ligne.
     *
     * @param  list<string>  $colonnes
     * @return array<int, array<string, string>>
     */
    private static function lire(string $chemin, array $colonnes): array
    {
        $fichier = @fopen($chemin, 'r');
        if ($fichier === false) {
            throw new RuntimeException(basename($chemin).' introuvable');
        }
        $entete = fgetcsv($fichier, null, ';', '"', '');
        if ($entete !== $colonnes) {
            fclose($fichier);
            throw new RuntimeException(basename($chemin).' : en-tête inattendu');
        }

        $lignes = [];
        $n = 1;
        while (($valeurs = fgetcsv($fichier, null, ';', '"', '')) !== false) {
            $n++;
            if ($valeurs === [null]) {
                continue;
            }
            if (count($valeurs) !== count($colonnes)) {
                fclose($fichier);
                throw new RuntimeException(basename($chemin)." ligne {$n} : ".count($valeurs).' colonnes');
            }
            $lignes[$n] = array_combine($colonnes, array_map('strval', $valeurs));
        }
        fclose($fichier);

        return $lignes;
    }

    /** @param array<string, string> $ligne */
    private static function nombre(array $ligne, string $colonne, int $n): float
    {
        $valeur = $ligne[$colonne] ?? '';
        if (! is_numeric($valeur)) {
            throw new RuntimeException("ligne {$n} : {$colonne} illisible");
        }

        return (float) $valeur;
    }
}
