<?php

namespace Src\Bibliotheque\Chargement;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lit et valide les fichiers de la bibliothèque (D-19, D-93) : un fichier YAML
 * par catégorie dans le dossier, plus `sources-exclues.yaml` (B3). Rien n'est
 * écrit : la lecture rend les espèces prêtes à charger, ou lève une
 * RuntimeException qui nomme le fichier et le chemin de la clé en cause.
 *
 * Les contrôles de structure du protocole tournent ici, à chaque lecture
 * (B7 : une version qui échoue ne se charge pas) : B-T4 à B-T9, B-T11, B2,
 * B3. Les contrôles agronomiques (B-T1 à B-T3, B-T12 à B-T14) portent sur les
 * valeurs, et sont des tests (4c).
 *
 * @phpstan-type Source array{url: string, libelle: string, consultee_le: string}
 * @phpstan-type FenetreLue array{debut: string, fin: string, optimal: list<string>|null, butoirs: list<string|null>|null, libelle: string|null}
 * @phpstan-type IntervalleLu array{mois_debut: int, mois_fin: int, jours: int}
 * @phpstan-type Parametres array{fenetres: list<FenetreLue>, intervalles: list<IntervalleLu>, periodicite_annees: int|null}
 * @phpstan-type RegleLue array{cle: string, variante: string|null, type_intervention: string, type_regle: string, confiance: string, sources: list<Source>, parametres: Parametres}
 * @phpstan-type EspeceLue array{fichier: string, cle: string, famille: string|null, colonnes: array<string, string|int|null>, regles: list<RegleLue>}
 */
final class LireBibliotheque
{
    /** Les fichiers du dossier qui ne sont pas des catégories. */
    public const HORS_CATEGORIES = ['sources-exclues.yaml', 'retraits.yaml'];

    /** Les catégories d'espèce (CDC §3.4), en code court. */
    public const CATEGORIES = ['fruitier', 'ornement', 'arbuste', 'haie', 'petits_fruits', 'vigne', 'gazon', 'massif', 'annuelle'];

    /**
     * B-T11 : les catégories auxquelles un type s'applique, par libellé, sinon
     * par famille ; un type absent d'ici s'applique à toutes (D-93).
     */
    private const APPLICABLES = [
        'Tonte' => ['gazon'],
        'Taille de haie' => ['haie'],
        'Éclaircissage des fruits' => ['fruitier', 'vigne'],
        'famille:taille' => ['fruitier', 'ornement', 'arbuste', 'haie', 'petits_fruits', 'vigne', 'massif', 'annuelle'],
        'famille:recolte' => ['fruitier', 'petits_fruits', 'vigne', 'annuelle'],
    ];

    /** Les colonnes descriptives libres d'une espèce (B6) et leur longueur, 0 pour un entier. */
    private const DESCRIPTIF = [
        'nom_latin' => 120, 'cycle' => 12, 'exposition' => 60, 'zone_rusticite' => 20,
        'hauteur_adulte_cm' => 0, 'periode_floraison' => 60, 'description' => 10000,
    ];

    private const CLES_REGLE = ['cle', 'variante', 'type_intervention', 'type_regle', 'confiance', 'sources', 'fenetres', 'intervalles', 'periodicite_annees'];

    private string $fichier = '';

    /**
     * @param  array<string, string>  $types  libellé => famille des types fournis
     * @return list<EspeceLue>
     */
    public function lire(string $dossier, array $types): array
    {
        $dossier = rtrim($dossier, '/');
        $exclus = $this->exclus("{$dossier}/sources-exclues.yaml");

        $fichiers = glob("{$dossier}/*.yaml") ?: [];
        sort($fichiers);
        $especes = [];
        /** @var array<string, string> $clesEspeces clé => fichier */
        $clesEspeces = [];
        /** @var array<string, string> $clesRegles clé => fichier */
        $clesRegles = [];
        foreach ($fichiers as $chemin) {
            $this->fichier = basename($chemin);
            if (in_array($this->fichier, self::HORS_CATEGORIES, true)) {
                continue;
            }
            $contenu = $this->analyser($chemin);
            $this->cles($contenu, ['especes'], '');
            $liste = $this->liste($contenu['especes'] ?? null, 'especes');
            foreach ($liste as $i => $brute) {
                $espece = $this->espece($brute, "especes[{$i}]", $types, $exclus);
                if (isset($clesEspeces[$espece['cle']])) {
                    $this->erreur("especes[{$i}].cle", "{$espece['cle']} déjà donnée dans {$clesEspeces[$espece['cle']]}");
                }
                $clesEspeces[$espece['cle']] = $this->fichier;
                foreach ($espece['regles'] as $j => $regle) {
                    if (isset($clesRegles[$regle['cle']])) {
                        $this->erreur("especes[{$i}].regles[{$j}].cle", "{$regle['cle']} déjà donnée dans {$clesRegles[$regle['cle']]}");
                    }
                    $clesRegles[$regle['cle']] = $this->fichier;
                }
                $especes[] = $espece;
            }
        }

        return $especes;
    }

    /** @return list<string> les domaines refusés (B3) */
    private function exclus(string $chemin): array
    {
        $this->fichier = basename($chemin);
        if (! is_file($chemin)) {
            $this->erreur('', 'fichier absent');
        }
        $contenu = $this->analyser($chemin);
        $this->cles($contenu, ['domaines'], '');
        $domaines = [];
        foreach ($this->liste($contenu['domaines'] ?? null, 'domaines') as $i => $ligne) {
            $ligne = $this->table($ligne, "domaines[{$i}]");
            $this->cles($ligne, ['domaine', 'raison'], "domaines[{$i}]");
            $domaines[] = strtolower($this->texte($ligne, 'domaine', "domaines[{$i}]", 253));
            $this->texte($ligne, 'raison', "domaines[{$i}]", 500);
        }

        return $domaines;
    }

    /** @return array<array-key, mixed> */
    private function analyser(string $chemin): array
    {
        try {
            $contenu = Yaml::parseFile($chemin, Yaml::PARSE_DATETIME | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            $this->erreur('', $e->getMessage());
        }

        return $this->table($contenu, '');
    }

    /**
     * @param  array<string, string>  $types
     * @param  list<string>  $exclus
     * @return EspeceLue
     */
    private function espece(mixed $brute, string $chemin, array $types, array $exclus): array
    {
        $espece = $this->table($brute, $chemin);
        $this->cles($espece, ['cle', 'nom_commun', 'famille', 'categorie', 'regles', ...array_keys(self::DESCRIPTIF)], $chemin);
        $cle = $this->identifiant($espece, 'cle', $chemin, 80);
        $categorie = $this->texte($espece, 'categorie', $chemin, 20);
        if (! in_array($categorie, self::CATEGORIES, true)) {
            $this->erreur("{$chemin}.categorie", 'catégorie inconnue : '.$categorie);
        }
        $colonnes = [
            'cle' => $cle,
            'nom_commun' => $this->texte($espece, 'nom_commun', $chemin, 120),
            'categorie' => $categorie,
        ];
        foreach (self::DESCRIPTIF as $champ => $longueur) {
            if (array_key_exists($champ, $espece)) {
                $colonnes[$champ] = $longueur === 0
                    ? $this->entier($espece[$champ], "{$chemin}.{$champ}", 0)
                    : $this->texte($espece, $champ, $chemin, $longueur);
            }
        }
        $famille = array_key_exists('famille', $espece) ? $this->texte($espece, 'famille', $chemin, 80) : null;

        $regles = [];
        foreach ($this->liste($espece['regles'] ?? null, "{$chemin}.regles") as $j => $regle) {
            $regles[] = $lue = $this->regle($regle, "{$chemin}.regles[{$j}]", $categorie, $types, $exclus);
            // B-T7 : une règle commune vaut pour toutes les variantes (RG-51).
            foreach (array_slice($regles, 0, -1) as $avant) {
                if ($avant['type_intervention'] === $lue['type_intervention']
                    && ($avant['variante'] === $lue['variante'] || $avant['variante'] === null || $lue['variante'] === null)) {
                    $this->erreur("{$chemin}.regles[{$j}].type_intervention", "déjà donné par la règle {$avant['cle']} (B-T7)");
                }
            }
        }

        return ['fichier' => $this->fichier, 'cle' => $cle, 'famille' => $famille, 'colonnes' => $colonnes, 'regles' => $regles];
    }

    /**
     * @param  array<string, string>  $types
     * @param  list<string>  $exclus
     * @return RegleLue
     */
    private function regle(mixed $brute, string $chemin, string $categorie, array $types, array $exclus): array
    {
        $regle = $this->table($brute, $chemin);
        $this->cles($regle, self::CLES_REGLE, $chemin);
        $cle = $this->identifiant($regle, 'cle', $chemin, 120);
        $variante = array_key_exists('variante', $regle) ? $this->texte($regle, 'variante', $chemin, 60) : null;

        $type = $this->texte($regle, 'type_intervention', $chemin, 80);
        if (! isset($types[$type])) {
            $this->erreur("{$chemin}.type_intervention", "type d'intervention fourni inconnu : {$type}");
        }
        $applicables = self::APPLICABLES[$type] ?? self::APPLICABLES['famille:'.$types[$type]] ?? self::CATEGORIES;
        if (! in_array($categorie, $applicables, true)) {
            $this->erreur("{$chemin}.type_intervention", "{$type} ne s'applique pas à la catégorie {$categorie} (B-T11)");
        }

        $typeRegle = $this->texte($regle, 'type_regle', $chemin, 2);
        if (! in_array($typeRegle, ['R1', 'R2'], true)) {
            $this->erreur("{$chemin}.type_regle", 'ni R1 ni R2');
        }
        $confiance = $this->texte($regle, 'confiance', $chemin, 12);
        if (! in_array($confiance, ['verifiee', 'indicative'], true)) {
            $this->erreur("{$chemin}.confiance", 'ni verifiee ni indicative (B-T9)');
        }
        $sources = $this->sources($regle, $chemin, $exclus);
        if ($sources === []) {
            $this->erreur("{$chemin}.sources", 'règle sans source (B1)');
        }
        if ($confiance === 'verifiee' && count($sources) < 2) {
            $this->erreur("{$chemin}.sources", 'règle verifiee à moins de deux sources (B2)');
        }

        // R2 : des fenêtres et une périodicité ; R1 : des intervalles par saison.
        [$attendues, $interdites] = $typeRegle === 'R2'
            ? [['fenetres', 'periodicite_annees'], ['intervalles']]
            : [['intervalles'], ['fenetres', 'periodicite_annees']];
        foreach ($interdites as $champ) {
            if (array_key_exists($champ, $regle)) {
                $this->erreur("{$chemin}.{$champ}", "sans objet pour une règle {$typeRegle}");
            }
        }
        foreach ($attendues as $champ) {
            if (! array_key_exists($champ, $regle) || $regle[$champ] === []) {
                $this->erreur("{$chemin}.{$champ}", 'absente');
            }
        }

        $fenetres = [];
        foreach ($this->liste($regle['fenetres'] ?? [], "{$chemin}.fenetres") as $k => $fenetre) {
            $fenetres[] = $this->fenetre($fenetre, "{$chemin}.fenetres[{$k}]");
        }

        return [
            'cle' => $cle,
            'variante' => $variante,
            'type_intervention' => $type,
            'type_regle' => $typeRegle,
            'confiance' => $confiance,
            'sources' => $sources,
            'parametres' => [
                'fenetres' => $fenetres,
                'intervalles' => $this->intervalles($regle['intervalles'] ?? [], "{$chemin}.intervalles"),
                'periodicite_annees' => array_key_exists('periodicite_annees', $regle)
                    ? $this->entier($regle['periodicite_annees'], "{$chemin}.periodicite_annees", 1) : null,
            ],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $regle
     * @param  list<string>  $exclus
     * @return list<Source>
     */
    private function sources(array $regle, string $chemin, array $exclus): array
    {
        $sources = [];
        foreach ($this->liste($regle['sources'] ?? [], "{$chemin}.sources") as $s => $brute) {
            $ici = "{$chemin}.sources[{$s}]";
            $source = $this->table($brute, $ici);
            $this->cles($source, ['url', 'libelle', 'consultee_le'], $ici);
            $url = $this->texte($source, 'url', $ici, 500);
            $hote = parse_url($url, PHP_URL_HOST);
            if (filter_var($url, FILTER_VALIDATE_URL) === false || ! is_string($hote) || ! preg_match('#^https?://#', $url)) {
                $this->erreur("{$ici}.url", 'adresse illisible');
            }
            $hote = strtolower($hote);
            foreach ($exclus as $domaine) {
                if ($hote === $domaine || str_ends_with($hote, ".{$domaine}")) {
                    $this->erreur("{$ici}.url", "domaine exclu {$domaine} (B3)");
                }
            }
            if (in_array($url, array_column($sources, 'url'), true)) {
                $this->erreur("{$ici}.url", 'déjà citée par cette règle');
            }
            $sources[] = [
                'url' => $url,
                'libelle' => $this->texte($source, 'libelle', $ici, 200),
                'consultee_le' => $this->jour($source, $ici),
            ];
        }

        return $sources;
    }

    /** @return FenetreLue */
    private function fenetre(mixed $brute, string $chemin): array
    {
        $fenetre = $this->table($brute, $chemin);
        $this->cles($fenetre, ['debut', 'fin', 'optimal', 'butoirs', 'libelle'], $chemin);
        $debut = $this->jourMois($fenetre['debut'] ?? null, "{$chemin}.debut");
        $fin = $this->jourMois($fenetre['fin'] ?? null, "{$chemin}.fin");

        $optimal = null;
        if (array_key_exists('optimal', $fenetre)) {
            $paire = $this->paire($fenetre['optimal'], "{$chemin}.optimal");
            $optimal = [$this->jourMois($paire[0], "{$chemin}.optimal[0]"), $this->jourMois($paire[1], "{$chemin}.optimal[1]")];
            // B-T6 : l'optimale tient dans la fenêtre, passage du 1er janvier compris.
            $a = self::rang($optimal[0], $debut);
            $b = self::rang($optimal[1], $debut);
            if ($a > $b || $b > self::rang($fin, $debut)) {
                $this->erreur("{$chemin}.optimal", 'hors de sa fenêtre (B-T6)');
            }
        }
        $butoirs = null;
        if (array_key_exists('butoirs', $fenetre)) {
            $paire = $this->paire($fenetre['butoirs'], "{$chemin}.butoirs");
            $butoirs = [
                $paire[0] === null ? null : $this->jourMois($paire[0], "{$chemin}.butoirs[0]"),
                $paire[1] === null ? null : $this->jourMois($paire[1], "{$chemin}.butoirs[1]"),
            ];
            if ($butoirs === [null, null]) {
                $this->erreur("{$chemin}.butoirs", 'aucun butoir');
            }
        }

        // B-T4 : de 14 jours à 5 mois ; 9 mois quand un butoir d'interdiction la borne.
        $jours = self::rang($fin, $debut) + 1;
        if ($jours < 14) {
            $this->erreur($chemin, "dure {$jours} jours, moins de 14 (B-T4)");
        }
        $mois = $butoirs === null ? 5 : 9;
        $depart = self::date($debut);
        if ($depart->modify("+{$jours} days")->modify('-1 day') >= $depart->modify("+{$mois} months")) {
            $this->erreur($chemin, "dure plus de {$mois} mois (B-T4)");
        }

        return [
            'debut' => $debut,
            'fin' => $fin,
            'optimal' => $optimal,
            'butoirs' => $butoirs,
            'libelle' => array_key_exists('libelle', $fenetre) ? $this->texte($fenetre, 'libelle', $chemin, 80) : null,
        ];
    }

    /** @return list<IntervalleLu> */
    private function intervalles(mixed $brute, string $chemin): array
    {
        $intervalles = [];
        $couverts = [];
        foreach ($this->liste($brute, $chemin) as $k => $ligne) {
            $ici = "{$chemin}[{$k}]";
            $intervalle = $this->table($ligne, $ici);
            $this->cles($intervalle, ['mois_debut', 'mois_fin', 'jours'], $ici);
            $de = $this->entier($intervalle['mois_debut'] ?? null, "{$ici}.mois_debut", 1, 12);
            $a = $this->entier($intervalle['mois_fin'] ?? null, "{$ici}.mois_fin", 1, 12);
            $jours = $this->entier($intervalle['jours'] ?? null, "{$ici}.jours", 0);
            // B-T5 : 0 marque le hors-saison (RG-40) ; sinon de 5 à 21 jours.
            if ($jours !== 0 && ($jours < 5 || $jours > 21)) {
                $this->erreur("{$ici}.jours", 'hors de 5 à 21 jours (B-T5)');
            }
            // Le moteur refuse deux saisons qui partagent un mois (D-90).
            for ($m = $de; ; $m = $m % 12 + 1) {
                if (isset($couverts[$m])) {
                    $this->erreur($ici, "le mois {$m} est déjà dans une autre saison");
                }
                $couverts[$m] = true;
                if ($m === $a) {
                    break;
                }
            }
            $intervalles[] = ['mois_debut' => $de, 'mois_fin' => $a, 'jours' => $jours];
        }
        // Le moteur refuse une règle R1 toujours hors saison (D-90).
        if ($intervalles !== [] && max(array_column($intervalles, 'jours')) === 0) {
            $this->erreur($chemin, 'aucune saison à intervalle non nul');
        }

        return $intervalles;
    }

    /** Le rang du jour JJ/MM compté depuis l'origine JJ/MM, sur une année de 366 jours. */
    private static function rang(string $jour, string $origine): int
    {
        return ((int) self::date($jour)->format('z') - (int) self::date($origine)->format('z') + 366) % 366;
    }

    /** Le jour JJ/MM en 2024, bissextile : le 29/02 existe (D-09). */
    private static function date(string $jourMois): DateTimeImmutable
    {
        [$j, $m] = explode('/', $jourMois);

        return new DateTimeImmutable("2024-{$m}-{$j}");
    }

    private function jourMois(mixed $valeur, string $chemin): string
    {
        if (! is_string($valeur) || ! preg_match('#^(\d{2})/(\d{2})$#', $valeur, $p)) {
            $this->erreur($chemin, 'date JJ/MM attendue');
        }
        if (! checkdate((int) $p[2], (int) $p[1], 2024)) {
            $this->erreur($chemin, 'date JJ/MM irréelle');
        }

        return $valeur;
    }

    /** @param array<array-key, mixed> $source */
    private function jour(array $source, string $chemin): string
    {
        $valeur = $source['consultee_le'] ?? null;
        if ($valeur === null) {
            $this->erreur("{$chemin}.consultee_le", 'absente');
        }
        if ($valeur instanceof DateTimeInterface) {
            return $valeur->format('Y-m-d');
        }
        if (! is_string($valeur) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valeur, $p) || ! checkdate((int) $p[2], (int) $p[3], (int) $p[1])) {
            $this->erreur("{$chemin}.consultee_le", 'date AAAA-MM-JJ attendue');
        }

        return $valeur;
    }

    /** @return array{0: mixed, 1: mixed} */
    private function paire(mixed $valeur, string $chemin): array
    {
        if (! is_array($valeur) || ! array_is_list($valeur) || count($valeur) !== 2) {
            $this->erreur($chemin, 'deux dates JJ/MM attendues');
        }

        return [$valeur[0], $valeur[1]];
    }

    /** @param array<array-key, mixed> $table */
    private function texte(array $table, string $cle, string $chemin, int $longueur): string
    {
        $ici = ltrim("{$chemin}.{$cle}", '.');
        if (! array_key_exists($cle, $table) || $table[$cle] === null) {
            $this->erreur($ici, 'absente');
        }
        $valeur = $table[$cle];
        if (! is_string($valeur) || trim($valeur) === '') {
            $this->erreur($ici, 'texte attendu');
        }
        if (mb_strlen($valeur) > $longueur) {
            $this->erreur($ici, "plus de {$longueur} caractères");
        }

        return $valeur;
    }

    /** @param array<array-key, mixed> $table */
    private function identifiant(array $table, string $cle, string $chemin, int $longueur): string
    {
        $valeur = $this->texte($table, $cle, $chemin, $longueur);
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $valeur)) {
            $this->erreur("{$chemin}.{$cle}", 'identifiant attendu : minuscules, chiffres et tirets');
        }

        return $valeur;
    }

    private function entier(mixed $valeur, string $chemin, int $min, int $max = 32767): int
    {
        if ($valeur === null) {
            $this->erreur($chemin, 'absente');
        }
        if (! is_int($valeur) || $valeur < $min || $valeur > $max) {
            $this->erreur($chemin, "entier de {$min} à {$max} attendu");
        }

        return $valeur;
    }

    /** @return array<array-key, mixed> */
    private function table(mixed $valeur, string $chemin): array
    {
        if (! is_array($valeur) || ($valeur !== [] && array_is_list($valeur))) {
            $this->erreur($chemin, 'table de clés attendue');
        }

        return $valeur;
    }

    /** @return list<mixed> */
    private function liste(mixed $valeur, string $chemin): array
    {
        if (! is_array($valeur) || ! array_is_list($valeur)) {
            $this->erreur($chemin, 'liste attendue');
        }

        return $valeur;
    }

    /**
     * @param  array<array-key, mixed>  $table
     * @param  list<string>  $connues
     */
    private function cles(array $table, array $connues, string $chemin): void
    {
        foreach (array_keys($table) as $cle) {
            if (! in_array($cle, $connues, true)) {
                $this->erreur(ltrim("{$chemin}.{$cle}", '.'), 'clé inconnue');
            }
        }
    }

    private function erreur(string $chemin, string $raison): never
    {
        throw new RuntimeException($chemin === '' ? "{$this->fichier} : {$raison}" : "{$this->fichier} : {$chemin} : {$raison}");
    }
}
