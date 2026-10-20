<?php

namespace Src\Comptes\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Src\Comptes\Domaine\Jardin;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\FondDePlan;
use Src\Jardin\Domaine\Geometrie;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Journal\Domaine\Intervention;
use Src\Journal\Domaine\Photo;
use Src\Support\Horloge;
use Src\Support\Jardin\JardinCourant;
use ZipStream\ZipStream;

/**
 * L'export intégral du jardin courant (V122, critère 8, D-61, D-112) : une
 * archive lisible sans l'outil. Chaque table est lue par son modèle, donc
 * sous le filtre du jardin (V123), par paquets (`lazyById`), et écrite au fil
 * de la lecture dans un fichier temporaire, puis dans l'archive envoyée en
 * flux (V124). Le CSV et `donnees.json` se remplissent dans la même passe.
 */
final class ExporterJardin
{
    private const PAQUET = 500;

    /** Au-delà, un fichier temporaire passe de la mémoire au disque. */
    private const TEMPORAIRE = 'php://temp/maxmemory:'.(2 * 1024 * 1024);

    public function __construct(private JardinCourant $courant, private Horloge $horloge) {}

    /** `jardin-<nom>-AAAA-MM-JJ.zip`, le nom réduit à des lettres sans accent et des tirets. */
    public function nomDuFichier(): string
    {
        $nom = Jardin::query()->whereKey($this->courant->id())->value('nom');
        $nom = Str::slug(is_string($nom) ? $nom : '') ?: 'sans-nom';

        return "jardin-{$nom}-{$this->horloge->aujourdhui()->format('Y-m-d')}.zip";
    }

    /** @param  resource  $sortie */
    public function ecrire($sortie): void
    {
        $zip = new ZipStream(outputStream: $sortie, sendHttpHeaders: false, defaultEnableZeroHeader: true);
        $zip->addFileFromPath('LISEZMOI.txt', resource_path('export/LISEZMOI.txt'));

        $json = $this->temporaire();
        fwrite($json, '{"jardin":'.$this->encoder($this->jardin()));

        $plans = [];
        foreach ($this->requetes() as $table => [$requete, $cle, $ajouts]) {
            $entetes = [...Schema::getColumnListing($table), ...array_keys($ajouts)];
            $jsonb = array_column(array_filter(Schema::getColumns($table), fn (array $c) => $c['type_name'] === 'jsonb'), 'name');
            $csv = $this->temporaire();
            $ecrire = new EcrireCsv($csv, $entetes);
            fwrite($json, ",\n\"{$table}\":[");
            $premiere = true;

            foreach ($requete->toBase()->lazyById(self::PAQUET, $cle, 'id') as $ligne) {
                /** @var array<string, mixed> $ligne */
                $ligne = (array) $ligne;
                foreach ($ajouts as $colonne => $calcul) {
                    if ($calcul !== null) {
                        $ligne[$colonne] = $calcul($ligne);
                    }
                }
                $valeurs = array_map(fn (string $c) => $ligne[$c] ?? null, $entetes);
                $ecrire->ligne($valeurs);

                $objet = array_combine($entetes, $valeurs);
                foreach ($jsonb as $c) {
                    $objet[$c] = is_string($objet[$c]) ? json_decode($objet[$c], true) : $objet[$c];
                }
                fwrite($json, ($premiere ? "\n" : ",\n").$this->encoder($objet));
                $premiere = false;

                if ($table === 'fonds_de_plan' && is_string($ligne['fichier']) && is_string($ligne['image'])) {
                    $plans[$ligne['image']] = $ligne['fichier'];
                }
            }

            fwrite($json, ($premiere ? '' : "\n").']');
            $this->ajouter($zip, "{$table}.csv", $csv);
        }

        fwrite($json, "\n}\n");
        $this->ajouter($zip, 'donnees.json', $json);

        $disque = Storage::disk('local');
        foreach ($plans as $nom => $fichier) {
            $image = $disque->exists($fichier) ? $disque->readStream($fichier) : null;
            if (is_resource($image)) {
                $zip->addFileFromStream($nom, $image);
                fclose($image);
            }
        }

        $zip->finish();
    }

    /**
     * Les tables de D-61, dans l'ordre de l'archive : la requête, sous le
     * filtre du jardin ; la colonne de son identifiant ; les colonnes
     * ajoutées, noms lisibles joints (`null`) ou calculées en PHP.
     *
     * @return array<string, array{0: Builder<covariant Model>, 1: string, 2: array<string, (\Closure(array<string, mixed>): mixed)|null>}>
     */
    private function requetes(): array
    {
        $plante = ['plante' => null, 'plante_code_court' => null];
        $type = ['type_intervention' => null];

        return [
            'zones' => [
                Zone::query()
                    ->leftJoin('zones as parente', fn (JoinClause $j) => $j->on('parente.id', '=', 'zones.zone_parente_id')->on('parente.jardin_id', '=', 'zones.jardin_id'))
                    ->select('zones.*', 'parente.nom as zone_parente'),
                'zones.id', ['zone_parente' => null],
            ],
            'plantes' => [
                Plante::query()
                    ->leftJoin('zones', fn (JoinClause $j) => $j->on('zones.id', '=', 'plantes.zone_id')->on('zones.jardin_id', '=', 'plantes.jardin_id'))
                    ->leftJoin('especes', 'especes.id', '=', 'plantes.espece_id')
                    ->select('plantes.*', 'zones.nom as zone', 'especes.nom_commun as espece', 'especes.nom_latin as espece_nom_latin'),
                'plantes.id', ['zone' => null, 'espece' => null, 'espece_nom_latin' => null],
            ],
            'geometries' => [
                $this->avecPlante(Geometrie::query(), 'geometries')->select(['geometries.*', ...$this->colonnesPlante()]),
                'geometries.id', $plante,
            ],
            'regles' => [
                $this->avecType($this->avecPlante(Regle::query(), 'regles'), 'regles')
                    ->select(['regles.*', ...$this->colonnesPlante(), 'types_intervention.libelle as type_intervention']),
                'regles.id', $plante + $type,
            ],
            'fenetres' => [
                $this->avecRegle(Fenetre::query(), 'fenetres')->select(['fenetres.*', ...$this->colonnesPlante(), 'types_intervention.libelle as type_intervention']),
                'fenetres.id', $plante + $type,
            ],
            'intervalles_saisonniers' => [
                $this->avecRegle(IntervalleSaisonnier::query(), 'intervalles_saisonniers')
                    ->select(['intervalles_saisonniers.*', ...$this->colonnesPlante(), 'types_intervention.libelle as type_intervention']),
                'intervalles_saisonniers.id', $plante + $type,
            ],
            'interventions' => [
                $this->avecType($this->avecPlante(Intervention::query(), 'interventions'), 'interventions')
                    ->leftJoin('utilisateurs', 'utilisateurs.id', '=', 'interventions.auteur_id')
                    ->select(['interventions.*', ...$this->colonnesPlante(), 'types_intervention.libelle as type_intervention', 'utilisateurs.nom_affiche as auteur']),
                'interventions.id', $plante + $type + ['auteur' => null],
            ],
            'echeances' => [
                $this->avecRegle(EcheanceMaterialisee::query(), 'echeances')->select(['echeances.*', ...$this->colonnesPlante(), 'types_intervention.libelle as type_intervention']),
                'echeances.id', $plante + $type,
            ],
            'photos' => [
                $this->avecPlante(Photo::query(), 'photos')->select(['photos.*', ...$this->colonnesPlante()]),
                'photos.id', $plante,
            ],
            // Ceux du jardin, et les fournis que ses règles ou ses interventions
            // utilisent ; le filtre `TypesVisibles` écarte ceux d'un autre jardin.
            'types_intervention' => [
                TypeIntervention::query()
                    ->where(fn (Builder $q) => $q->whereNotNull('types_intervention.jardin_id')
                        ->orWhereIn('types_intervention.id', Regle::query()->select('type_intervention_id'))
                        ->orWhereIn('types_intervention.id', Intervention::query()->select('type_intervention_id')))
                    ->select('types_intervention.*')
                    ->selectRaw('types_intervention.jardin_id is null as fourni'),
                'types_intervention.id', ['fourni' => null],
            ],
            'fonds_de_plan' => [
                FondDePlan::query()->select('fonds_de_plan.*'),
                'fonds_de_plan.id',
                ['image' => fn (array $l): ?string => is_string($l['fichier']) ? 'plans/'.basename($l['fichier']) : null],
            ],
        ];
    }

    /**
     * La plante d'une ligne, du même jardin.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $requete
     * @return Builder<T>
     */
    private function avecPlante(Builder $requete, string $table): Builder
    {
        return $requete->leftJoin('plantes', fn (JoinClause $j) => $j->on('plantes.id', '=', "{$table}.plante_id")->on('plantes.jardin_id', '=', "{$table}.jardin_id"));
    }

    /**
     * Le type d'intervention d'une ligne.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $requete
     * @return Builder<T>
     */
    private function avecType(Builder $requete, string $table): Builder
    {
        return $requete->leftJoin('types_intervention', 'types_intervention.id', '=', "{$table}.type_intervention_id");
    }

    /**
     * La règle d'une ligne, du même jardin, et par elle sa plante et son type.
     *
     * @template T of Model
     *
     * @param  Builder<T>  $requete
     * @return Builder<T>
     */
    private function avecRegle(Builder $requete, string $table): Builder
    {
        return $this->avecType(
            $requete->leftJoin('regles', fn (JoinClause $j) => $j->on('regles.id', '=', "{$table}.regle_id")->on('regles.jardin_id', '=', "{$table}.jardin_id"))
                ->leftJoin('plantes', fn (JoinClause $j) => $j->on('plantes.id', '=', 'regles.plante_id')->on('plantes.jardin_id', '=', 'regles.jardin_id')),
            'regles',
        );
    }

    /** @return list<string> */
    private function colonnesPlante(): array
    {
        return ['plantes.nom as plante', 'plantes.code_court as plante_code_court'];
    }

    /** @return array<string, mixed> le jardin, avec le libellé de son profil climatique */
    private function jardin(): array
    {
        /** @var array<string, mixed> */
        return (array) Jardin::query()
            ->leftJoin('profils_climatiques', 'profils_climatiques.id', '=', 'jardins.profil_climatique_id')
            ->where('jardins.id', $this->courant->id())
            ->select('jardins.*', 'profils_climatiques.libelle as profil_climatique')
            ->toBase()
            ->first();
    }

    /** @return resource */
    private function temporaire()
    {
        $flux = fopen(self::TEMPORAIRE, 'w+b');
        if ($flux === false) {
            throw new \RuntimeException('Fichier temporaire de l\'export impossible à ouvrir.');
        }

        return $flux;
    }

    private function encoder(mixed $valeur): string
    {
        return json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /** @param  resource  $flux */
    private function ajouter(ZipStream $zip, string $nom, $flux): void
    {
        rewind($flux);
        $zip->addFileFromStream($nom, $flux);
        fclose($flux);
    }
}
