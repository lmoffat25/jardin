<?php

namespace Tests\Support;

use Database\Factories\RegleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Src\Comptes\Domaine\Utilisateur;
use ZipArchive;

/**
 * L'export d'un jardin vu du test (V122, V123, V124, V130) : le télécharger,
 * l'ouvrir, lire ses CSV, et un jardin qui remplit chacune des tables de
 * D-61. Toutes les données sont inventées (RG-28).
 */
final class Export
{
    /** Les CSV de l'archive, dans l'ordre de D-61. */
    public const CSV = [
        'zones', 'plantes', 'geometries', 'regles', 'fenetres', 'intervalles_saisonniers',
        'interventions', 'echeances', 'photos', 'types_intervention', 'fonds_de_plan',
    ];

    public const BOM = "\u{FEFF}";

    /** `GET /export` dans un jardin, par un utilisateur. */
    public static function telecharger(Utilisateur $u, string $jardinId): TestResponse
    {
        app()->forgetScopedInstances();

        return test()->actingAs($u)->withSession(['jardin_id' => $jardinId])->get('/export');
    }

    /**
     * Les fichiers de l'archive rendue, nom → contenu.
     *
     * @return array<string, string>
     */
    public static function fichiers(TestResponse $reponse): array
    {
        $chemin = tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($chemin, $reponse->streamedContent());
        $zip = new ZipArchive;
        if ($zip->open($chemin) !== true) {
            unlink($chemin);
            throw new RuntimeException('archive illisible');
        }
        $fichiers = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nom = (string) $zip->getNameIndex($i);
            $fichiers[$nom] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($chemin);

        return $fichiers;
    }

    /**
     * Les lignes d'un CSV de l'export, chacune indexée par l'en-tête. Le
     * fichier commence par le BOM et sépare ses champs par `;`.
     *
     * @return array{entetes: list<string>, lignes: list<array<string, string>>}
     */
    public static function csv(string $contenu): array
    {
        expect(str_starts_with($contenu, self::BOM))->toBeTrue('CSV sans BOM');
        $flux = fopen('php://memory', 'w+');
        fwrite($flux, substr($contenu, strlen(self::BOM)));
        rewind($flux);
        $entetes = fgetcsv($flux, null, ';', '"', '');
        $lignes = [];
        while (($champs = fgetcsv($flux, null, ';', '"', '')) !== false) {
            $lignes[] = array_combine($entetes, $champs);
        }
        fclose($flux);

        return ['entetes' => $entetes, 'lignes' => $lignes];
    }

    /**
     * Une valeur de la base telle que le CSV doit l'écrire : vide pour `null`,
     * `oui` / `non` pour un booléen, le texte de Postgres sinon.
     */
    public static function attendu(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => '',
            $valeur === true => 'oui',
            $valeur === false => 'non',
            default => (string) $valeur,
        };
    }

    /**
     * Un jardin dont chaque table de D-61 a des lignes : une zone et sa
     * parente, une plante avec espèce et une archivée sans espèce, une
     * géométrie, une règle en deux versions et une tonte, une fenêtre, un
     * intervalle, une intervention, une échéance soldée, une photo, un type du
     * jardin, un fond de plan avec son image sur le disque. Les noms portent
     * le préfixe. Rend les identifiants de ses lignes.
     *
     * @return list<string>
     */
    public static function peupler(string $jardin, string $prefixe, string $auteur): array
    {
        $j = ['jardin_id' => $jardin];
        $id = fn (): string => (string) Str::uuid();
        $espece = DB::table('especes')->where('nom_commun', 'Pommier témoin')->value('id')
            ?? DB::table('especes')->insertGetId(['cle' => 'pommier-temoin', 'nom_commun' => 'Pommier témoin', 'nom_latin' => 'Malus témoin', 'categorie' => 'arbre_fruitier']);

        $massifs = $id();
        $rosiers = $id();
        DB::table('zones')->insert([$j + ['id' => $massifs, 'nom' => "{$prefixe} Massifs"]]);
        DB::table('zones')->insert([$j + ['id' => $rosiers, 'nom' => "{$prefixe} Rosiers", 'zone_parente_id' => $massifs]]);

        $pommier = $id();
        $ancienne = $id();
        DB::table('plantes')->insert([
            $j + [
                'id' => $pommier, 'zone_id' => $rosiers, 'code_court' => 'EXP-'.substr($pommier, 0, 4), 'nom' => "{$prefixe} Pommier",
                'espece_id' => $espece, 'variete' => 'Reinette', 'plantee_le' => '2019-11-15', 'sante' => 'bonne',
                // Un séparateur, un guillemet et un saut de ligne : le CSV doit les tenir.
                'notes' => "Greffé ; \"porte-greffe\" M106\nà surveiller",
            ],
        ]);
        DB::table('plantes')->insert([
            $j + [
                'id' => $ancienne, 'zone_id' => $massifs, 'code_court' => 'EXA-'.substr($ancienne, 0, 4), 'nom' => "{$prefixe} Ancienne",
                'etat' => 'archivee', 'archivee_le' => '2026-03-01 09:00:00+00', 'motif_archivage' => 'Arrachée',
            ],
        ]);

        $geometrie = $id();
        DB::table('geometries')->insert($j + [
            'id' => $geometrie, 'plante_id' => $pommier, 'forme' => 'point',
            'coordonnees' => json_encode(['type' => 'Point', 'coordinates' => [4.8357, 45.764]]),
            'precision_m' => 4, 'source' => 'gps', 'relevee_le' => '2026-04-02 10:00:00+00',
        ]);

        $type = DB::table('types_intervention')->insertGetId($j + ['libelle' => "{$prefixe} Geste maison", 'famille' => 'entretien']);
        $tonte = RegleFactory::typeFourni('Tonte');

        $v1 = $id();
        $v2 = $id();
        $r1 = $id();
        // Une requête par ligne : les colonnes diffèrent d'une ligne à l'autre.
        foreach ([
            $j + ['id' => $v1, 'plante_id' => $pommier, 'type_intervention_id' => $type, 'type_regle' => 'R2', 'periodicite_annees' => 1, 'confiance' => 'indicative', 'origine' => 'libre', 'est_active' => false, 'version' => 1, 'remplacee_le' => '2026-02-01 08:00:00+00'],
            $j + ['id' => $v2, 'plante_id' => $pommier, 'type_intervention_id' => $type, 'type_regle' => 'R2', 'periodicite_annees' => 2, 'confiance' => 'indicative', 'origine' => 'ajustee', 'est_active' => true, 'version' => 2],
            $j + ['id' => $r1, 'plante_id' => $pommier, 'type_intervention_id' => $tonte, 'type_regle' => 'R1', 'intervalle_jours' => 7, 'confiance' => 'verifiee', 'origine' => 'bibliotheque'],
        ] as $regle) {
            DB::table('regles')->insert($regle);
        }

        $fenetre = $id();
        DB::table('fenetres')->insert($j + [
            'id' => $fenetre, 'regle_id' => $v2, 'debut_jour' => 1, 'debut_mois' => 11, 'fin_jour' => 15, 'fin_mois' => 3,
            'butoir_fin_jour' => 31, 'butoir_fin_mois' => 3, 'libelle' => "{$prefixe} Hiver",
        ]);
        $intervalle = $id();
        DB::table('intervalles_saisonniers')->insert($j + ['id' => $intervalle, 'regle_id' => $r1, 'mois_debut' => 4, 'mois_fin' => 9, 'intervalle_jours' => 7]);

        $intervention = $id();
        DB::table('interventions')->insert($j + [
            'id' => $intervention, 'plante_id' => $pommier, 'type_intervention_id' => $type, 'auteur_id' => $auteur,
            'faite_le' => '2026-01-20', 'duree_min' => 45, 'note' => "{$prefixe} Taillé court",
            'position_capturee' => json_encode(['lng' => 4.8357, 'lat' => 45.764, 'precision_m' => 6]),
            'quantite' => 1.5, 'unite' => 'kg',
        ]);
        $echeance = $id();
        DB::table('echeances')->insert($j + [
            'id' => $echeance, 'regle_id' => $v2, 'plante_id' => $pommier, 'due_le' => '2026-01-15',
            'fenetre_debut' => '2025-11-01', 'fenetre_fin' => '2026-03-15', 'etat' => 'soldee',
            'intervention_id' => $intervention, 'explication' => "{$prefixe} Taille d'hiver, tous les deux ans",
        ]);

        $photo = $id();
        DB::table('photos')->insert($j + [
            'id' => $photo, 'plante_id' => $pommier, 'empreinte' => hash('sha256', $photo),
            'chemin_original' => "photos/{$jardin}/".hash('sha256', $photo).'.jpg',
            'derivees_pretes' => true, 'taille_ko' => 812, 'largeur_px' => 1600, 'hauteur_px' => 1200, 'prise_le' => '2026-05-03',
        ]);

        $fond = $id();
        $image = "photos/{$jardin}/plans/".hash('sha256', $fond).'.png';
        Storage::disk('local')->put($image, "{$prefixe} image du plan");
        DB::table('fonds_de_plan')->insert($j + [
            'id' => $fond, 'nature' => 'image', 'fichier' => $image, 'actif' => true,
            'calage' => json_encode([['x' => 0, 'y' => 0, 'lng' => 4.835, 'lat' => 45.765], ['x' => 400, 'y' => 300, 'lng' => 4.837, 'lat' => 45.7635]]),
        ]);

        return [$massifs, $rosiers, $pommier, $ancienne, $geometrie, $v1, $v2, $r1, $fenetre, $intervalle, $intervention, $echeance, $photo, $fond];
    }
}
