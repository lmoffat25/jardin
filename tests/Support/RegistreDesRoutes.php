<?php

namespace Tests\Support;

use Closure;
use Database\Factories\RegleFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;

/**
 * Toutes les routes de l'application (C15, V41, V128). Une route nouvelle
 * entre dans `entrees()` ou dans `EXCEPTIONS`, sinon `CloisonnementTest`
 * échoue en la nommant.
 *
 * Une route est désignée par sa méthode et son chemin (`POST
 * /jardins/{jardin}/basculer`), pas par son nom : la plupart n'en ont pas
 * (D-84).
 *
 * Chaque entrée déclare :
 * - `nature` : `lit` (montre une ligne désignée par un identifiant), `liste`
 *   (montre les lignes du jardin courant), `ecrit` (modifie quelque chose) ;
 * - `role` : le rôle minimal exigé dans le jardin courant (D-49) ; `membre`
 *   pour la lecture, `POST /interventions`, `POST /echeances/fait`,
 *   `POST /photos` et `GET /export`, `proprietaire` pour toute autre écriture ;
 * - `parametres` : les paramètres de la route, fabriqués à partir d'un jardin ;
 * - `charge` : pour `ecrit`, une charge valide, fabriquée à partir d'un jardin.
 */
final class RegistreDesRoutes
{
    /**
     * Les routes hors cloisonnement, avec leur raison. Relue à chaque
     * modification (C15).
     */
    public const EXCEPTIONS = [
        'GET /sante' => 'santé : ne lit aucun jardin',
        'GET /up' => 'santé du framework : ne lit aucun jardin',
        'GET /connexion' => 'connexion : session fermée, aucun jardin',
        'POST /connexion' => 'connexion : session fermée, aucun jardin',
        'POST /deconnexion' => 'déconnexion : ferme la session, n\'écrit dans aucun jardin',
        'GET /storage/{path}' => 'disque local du framework : URL signée seulement, aucun fichier de jardin n\'y est encore',
        'PUT /storage/{path}' => 'disque local du framework : URL signée seulement, aucun fichier de jardin n\'y est encore',
        'GET /especes' => 'bibliothèque : référence partagée en lecture seule, aucune ligne de jardin (V65)',
        'GET /especes/{espece}/regles-proposees' => 'bibliothèque : référence partagée en lecture seule ; du jardin courant, seul son profil décale les périodes (V65)',
        'GET /_inertia/devtools/entries' => 'outil de développement d\'Inertia, hors production',
        'GET /_inertia/devtools/entries/{id}' => 'outil de développement d\'Inertia, hors production',
    ];

    /** @return array<string, array{nature: 'lit'|'liste'|'ecrit', role: 'membre'|'proprietaire', parametres?: Closure(string): array<string, string>, charge?: Closure(string): array<string, mixed>}> */
    public static function entrees(): array
    {
        $jardin = fn (string $id): array => ['nom' => 'Jardin renommé', 'profil_climatique_id' => 2];
        $plante = fn (string $id): array => ['plante' => (string) Plante::query()->withoutGlobalScopes()->where('jardin_id', $id)->orderBy('nom')->value('id')];
        $zone = fn (string $id): string => (string) Zone::query()->withoutGlobalScopes()->where('jardin_id', $id)->orderBy('nom')->value('id');
        $regle = fn (string $id): string => (string) (Regle::query()->withoutGlobalScopes()->where('jardin_id', $id)->orderBy('id')->value('id') ?? '5e0c9a52-7a1d-4c3e-9f4b-0000000000ff');

        return [
            'GET /' => ['nature' => 'liste', 'role' => 'membre'],
            'GET /terrain' => ['nature' => 'liste', 'role' => 'membre'],
            // Sans paramètres, la validation refuse ; le détail d'une zone de B
            // rend 404 (TableauDeBordTest).
            'GET /echeances/groupe' => ['nature' => 'liste', 'role' => 'membre'],
            'GET /zones' => ['nature' => 'liste', 'role' => 'membre'],
            'POST /zones' => ['nature' => 'ecrit', 'role' => 'proprietaire', 'charge' => fn (string $id): array => ['nom' => 'Zone créée']],
            'PUT /zones/{zone}' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'parametres' => fn (string $id): array => ['zone' => $zone($id)],
                'charge' => fn (string $id): array => ['nom' => 'Zone renommée'],
            ],
            // Depuis A, la zone de B est refusée par `ZoneDuJardin` (CreerPlanteTest).
            'POST /plantes' => ['nature' => 'ecrit', 'role' => 'proprietaire', 'charge' => fn (string $id): array => ['nom' => 'Plante créée', 'zone_id' => $zone($id)]],
            'PUT /plantes/{plante}' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'parametres' => $plante,
                'charge' => fn (string $id): array => ['nom' => 'Plante renommée', 'zone_id' => $zone($id)],
            ],
            'POST /plantes/{plante}/archiver' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'parametres' => $plante,
                'charge' => fn (string $id): array => ['motif' => 'Arrachée'],
            ],
            'GET /plantes' => ['nature' => 'liste', 'role' => 'membre'],
            // Sans position ni zone, la liste est vide ; une zone de B ne rend
            // rien (ProchesTest).
            'GET /plantes/proches' => ['nature' => 'liste', 'role' => 'membre'],
            // Depuis A, la plante de B est introuvable : 404, sa géométrie
            // intacte (GeometrieTest).
            'PUT /plantes/{plante}/geometrie' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'parametres' => $plante,
                'charge' => fn (string $id): array => ['type' => 'Point', 'coordinates' => [4.8357, 45.764]],
            ],
            // Depuis A, une plante de B parmi les ids est introuvable : 422,
            // rien ne change (MasseTest).
            'PUT /plantes/lot' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'charge' => fn (string $id): array => ['ids' => [$plante($id)['plante']], 'repere' => 'Repère du lot'],
            ],
            'GET /plantes/{plante}' => ['nature' => 'lit', 'role' => 'membre', 'parametres' => $plante],
            'GET /plantes/{plante}/historique' => ['nature' => 'lit', 'role' => 'membre', 'parametres' => $plante],
            // Depuis A, la plante de B est introuvable : 422, rien n'est créé
            // (EnregistrerInterventionTest). Le même identifiant sert au
            // membre puis au propriétaire : le second envoi est un renvoi.
            'POST /interventions' => [
                'nature' => 'ecrit',
                'role' => 'membre',
                'charge' => fn (string $id): array => [
                    'type_intervention_id' => RegleFactory::typeFourni('Tonte'),
                    'faite_le' => '2026-08-30',
                    'lignes' => [['id' => '5e0c9a52-7a1d-4c3e-9f4b-000000000001', 'plante_id' => $plante($id)['plante']]],
                ],
            ],
            // Depuis A, une règle de B est introuvable : 404, rien n'est créé
            // (FaitTest). Les jardins témoins n'ont aucune règle : la charge
            // désigne la première, sinon une règle qui n'existe pas.
            'POST /echeances/fait' => [
                'nature' => 'ecrit',
                'role' => 'membre',
                'charge' => fn (string $id): array => [
                    'id' => '5e0c9a52-7a1d-4c3e-9f4b-000000000002',
                    'regle_id' => (string) (Regle::query()->withoutGlobalScopes()->where('jardin_id', $id)->orderBy('id')->value('id') ?? '5e0c9a52-7a1d-4c3e-9f4b-0000000000ff'),
                    'due_le' => '2026-08-30',
                ],
            ],
            // Depuis A, une règle de B est introuvable : 404, B intact
            // (CorrectionRegleTest, SuspendreTest). Les jardins témoins n'ont
            // aucune règle : la première, sinon une règle qui n'existe pas.
            'PUT /regles/{regle}' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'parametres' => fn (string $id): array => ['regle' => $regle($id)],
                'charge' => fn (string $id): array => ['periodicite_annees' => 2],
            ],
            'POST /echeances/suspendre' => [
                'nature' => 'ecrit',
                'role' => 'proprietaire',
                'charge' => fn (string $id): array => ['regle_id' => $regle($id), 'due_le' => '2026-08-30', 'motif' => 'Sautée'],
            ],
            // Les types fournis et ceux du jardin courant (TypesVisibles, D-55).
            'GET /types-intervention' => ['nature' => 'liste', 'role' => 'membre'],
            'POST /types-intervention' => ['nature' => 'ecrit', 'role' => 'proprietaire', 'charge' => fn (string $id): array => ['libelle' => 'Type créé', 'famille' => 'entretien']],
            'GET /jardins/nouveau' => ['nature' => 'liste', 'role' => 'membre'],
            // Crée un jardin neuf, dont le créateur est propriétaire : aucun
            // rôle n'est exigé dans le jardin courant.
            'POST /jardins' => ['nature' => 'ecrit', 'role' => 'membre', 'charge' => $jardin],
            'POST /jardins/{jardin}/basculer' => [
                'nature' => 'ecrit',
                'role' => 'membre',
                'parametres' => fn (string $id): array => ['jardin' => $id],
                'charge' => fn (string $id): array => [],
            ],
            'PUT /jardin' => ['nature' => 'ecrit', 'role' => 'proprietaire', 'charge' => $jardin],
            // Référence partagée, comme la bibliothèque : aucune ligne de
            // jardin. Le paramètre est un code de commune, pas un id de B ; la
            // table est vide dans ce test, d'où le 404.
            'GET /communes' => ['nature' => 'liste', 'role' => 'membre'],
            'GET /communes/{code_insee}/profil' => [
                'nature' => 'lit',
                'role' => 'membre',
                'parametres' => fn (string $id): array => ['code_insee' => '99101'],
            ],
        ];
    }

    /** La clé d'une route : sa première méthode hors `HEAD`, et son chemin. */
    public static function cle(Route $route): string
    {
        $methode = array_values(array_diff($route->methods(), ['HEAD']))[0];

        return $methode.' /'.ltrim($route->uri(), '/');
    }

    /**
     * Les routes de l'application qui ne sont ni au registre ni en exception.
     *
     * @return list<string>
     */
    public static function nonInscrites(): array
    {
        $connues = [...array_keys(self::entrees()), ...array_keys(self::EXCEPTIONS)];
        $cles = array_map(self::cle(...), Routes::getRoutes()->getRoutes());

        return array_values(array_unique(array_diff($cles, $connues)));
    }

    /**
     * Les entrées du registre qui ne correspondent plus à aucune route. Les
     * exceptions n'y sont pas : certaines n'existent que hors test (outils de
     * développement).
     *
     * @return list<string>
     */
    public static function orphelines(): array
    {
        $cles = array_map(self::cle(...), Routes::getRoutes()->getRoutes());

        return array_values(array_diff(array_keys(self::entrees()), $cles));
    }

    /**
     * Le chemin d'une entrée, ses paramètres remplis depuis le jardin donné.
     *
     * @param  array{parametres?: Closure(string): array<string, string>}  $entree
     */
    public static function chemin(string $cle, array $entree, string $jardinId): string
    {
        $chemin = explode(' ', $cle, 2)[1];
        foreach (($entree['parametres'] ?? fn () => [])($jardinId) as $nom => $valeur) {
            $chemin = str_replace('{'.$nom.'}', $valeur, $chemin);
        }

        return $chemin;
    }
}
