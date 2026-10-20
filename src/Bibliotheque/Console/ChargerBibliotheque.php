<?php

namespace Src\Bibliotheque\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Bibliotheque\Chargement\LireBibliotheque;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\Domaine\FamilleBotanique;
use Src\Bibliotheque\Domaine\RegleReference;
use Src\Bibliotheque\Domaine\SourceRegle;
use Src\Bibliotheque\Domaine\TypeIntervention;

/**
 * Charge les fichiers de la bibliothèque en base (RG-24, chapitre 4 §5, D-39,
 * D-93), tout ou rien : un fichier mal formé n'écrit rien.
 *
 * Idempotent, ligne à ligne : une famille, une espèce ou une source identique
 * n'est pas réécrite. Une règle dont les paramètres, la confiance, le type ou
 * l'espèce changent reçoit une version suivante, et l'ancienne `remplacee_le` ;
 * des sources seules changées sont remplacées sur la version en vigueur. Une
 * règle absente des fichiers est retirée (`remplacee_le`), sans version
 * nouvelle. Les règles des jardins (`regles`, `fenetres`, `echeances`) ne sont
 * jamais touchées : la correction leur est proposée ailleurs (RG-24, RG-39).
 *
 * @phpstan-import-type EspeceLue from LireBibliotheque
 * @phpstan-import-type RegleLue from LireBibliotheque
 * @phpstan-import-type Source from LireBibliotheque
 */
final class ChargerBibliotheque extends Command
{
    /** Les colonnes d'une espèce que les fichiers donnent, et leur valeur quand ils se taisent. */
    private const COLONNES_ESPECE = [
        'famille_id' => null, 'nom_commun' => null, 'nom_latin' => null, 'categorie' => null, 'cycle' => 'perenne',
        'exposition' => null, 'zone_rusticite' => null, 'hauteur_adulte_cm' => null, 'periode_floraison' => null, 'description' => null,
    ];

    protected $signature = 'bibliotheque:charger {dossier=bibliotheque : Le dossier de la bibliothèque}';

    protected $description = 'Charge les fichiers de la bibliothèque : familles, espèces, règles de référence versionnées et leurs sources';

    /** @var array<string, int> */
    private array $bilan = ['especes' => 0, 'versions' => 0, 'sources' => 0, 'retirees' => 0];

    public function handle(): int
    {
        $types = TypeIntervention::query()->withoutGlobalScopes()->whereNull('jardin_id')->get(['id', 'libelle', 'famille']);
        /** @var array<string, string> $familles */
        $familles = $types->pluck('famille', 'libelle')->all();
        /** @var array<string, int> $ids */
        $ids = $types->pluck('id', 'libelle')->all();

        try {
            $especes = (new LireBibliotheque)->lire((string) $this->argument('dossier'), $familles);
        } catch (RuntimeException $e) {
            $this->error("Rien n'est chargé : {$e->getMessage()}");

            return self::FAILURE;
        }

        DB::transaction(fn () => $this->charger($especes, $ids));
        $this->info(sprintf(
            '%d espèces, %d règles. Écrites : %d espèces, %d versions de règle, %d jeux de sources ; %d règles retirées.',
            count($especes), array_sum(array_map(fn (array $e) => count($e['regles']), $especes)),
            $this->bilan['especes'], $this->bilan['versions'], $this->bilan['sources'], $this->bilan['retirees'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<EspeceLue>  $especes
     * @param  array<string, int>  $types  libellé => id des types fournis
     */
    private function charger(array $especes, array $types): void
    {
        $familles = $this->familles($especes);

        $existantes = Espece::query()->get()->keyBy('cle');
        $enVigueur = RegleReference::query()->with('sources')->whereNull('remplacee_le')->get()->keyBy('cle');
        /** @var array<string, int> $dernieres */
        $dernieres = RegleReference::query()->toBase()->groupBy('cle')->selectRaw('cle, max(version) AS derniere')->pluck('derniere', 'cle')->all();

        $lues = [];
        foreach ($especes as $espece) {
            $colonnes = $espece['colonnes'] + ['famille_id' => $espece['famille'] === null ? null : $familles[$espece['famille']]] + self::COLONNES_ESPECE;
            $existante = $existantes->get($espece['cle']);
            if ($existante === null) {
                $especeId = (int) Espece::query()->insertGetId($colonnes);
                $this->bilan['especes']++;
            } else {
                $especeId = $existante->id;
                $changees = array_filter($colonnes, fn ($v, string $c) => self::scalaire($existante->getRawOriginal($c)) !== self::scalaire($v), ARRAY_FILTER_USE_BOTH);
                if ($changees !== []) {
                    Espece::query()->whereKey($especeId)->update($changees);
                    $this->bilan['especes']++;
                }
            }

            foreach ($espece['regles'] as $regle) {
                $lues[] = $regle['cle'];
                $this->regle($regle, $especeId, $types[$regle['type_intervention']], $enVigueur->get($regle['cle']), $dernieres[$regle['cle']] ?? 0);
            }
        }

        // Une règle retirée des fichiers n'est plus proposée ; ses versions restent (RG-24).
        $retirees = $enVigueur->keys()->diff($lues)->all();
        if ($retirees !== []) {
            $this->bilan['retirees'] += RegleReference::query()->whereIn('cle', $retirees)->whereNull('remplacee_le')->update(['remplacee_le' => now()]);
        }
    }

    /**
     * Les familles nommées dans les fichiers, créées si besoin.
     *
     * @param  list<EspeceLue>  $especes
     * @return array<string, int> nom => id
     */
    private function familles(array $especes): array
    {
        /** @var array<string, int> $connues */
        $connues = FamilleBotanique::query()->pluck('id', 'nom')->all();
        $noms = array_unique(array_filter(array_column($especes, 'famille'), 'is_string'));
        foreach (array_diff($noms, array_keys($connues)) as $nom) {
            $connues[$nom] = (int) FamilleBotanique::query()->insertGetId(['nom' => $nom]);
        }

        return $connues;
    }

    /** @param RegleLue $regle */
    private function regle(array $regle, int $espece, int $type, ?RegleReference $enVigueur, int $derniere): void
    {
        $colonnes = [
            'espece_id' => $espece,
            'variante' => $regle['variante'],
            'type_intervention_id' => $type,
            'type_regle' => $regle['type_regle'],
            'confiance' => $regle['confiance'],
        ];

        if ($enVigueur !== null && self::canonique($enVigueur->parametres) === self::canonique($regle['parametres'])
            && array_filter($colonnes, fn ($v, string $c) => self::scalaire($enVigueur->getRawOriginal($c)) !== self::scalaire($v), ARRAY_FILTER_USE_BOTH) === []) {
            $avant = $enVigueur->sources->map(fn (SourceRegle $s) => [
                'url' => $s->url, 'libelle' => (string) $s->libelle, 'consultee_le' => $s->consultee_le->format('Y-m-d'),
            ])->sortBy('url')->values()->all();
            if ($avant !== collect($regle['sources'])->sortBy('url')->values()->all()) {
                SourceRegle::query()->where('regle_reference_id', $enVigueur->id)->delete();
                $this->sources($enVigueur->id, $regle['sources']);
            }

            return;
        }

        // L'ancienne version sort de vigueur avant que la suivante n'y entre (index partiel).
        if ($enVigueur !== null) {
            RegleReference::query()->whereKey($enVigueur->id)->update(['remplacee_le' => now()]);
        }
        $id = (int) RegleReference::query()->insertGetId($colonnes + [
            'cle' => $regle['cle'],
            'parametres' => json_encode($regle['parametres'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'version' => $derniere + 1,
        ]);
        $this->bilan['versions']++;
        $this->sources($id, $regle['sources']);
    }

    /** @param list<Source> $sources */
    private function sources(int $regle, array $sources): void
    {
        SourceRegle::query()->insert(array_map(fn (array $s) => $s + ['regle_reference_id' => $regle], $sources));
        $this->bilan['sources']++;
    }

    /** Les tables triées par clé, à toute profondeur : `jsonb` ne garde pas l'ordre des clés. */
    private static function canonique(mixed $valeur): mixed
    {
        if (! is_array($valeur)) {
            return $valeur;
        }
        $valeur = array_map(self::canonique(...), $valeur);
        if (! array_is_list($valeur)) {
            ksort($valeur);
        }

        return $valeur;
    }

    /** Une valeur de colonne comparable, que PDO l'ait rendue en texte ou en entier. */
    private static function scalaire(mixed $valeur): ?string
    {
        return is_scalar($valeur) ? (string) $valeur : null;
    }
}
