<?php

namespace Tests\Support;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;

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
        'GET /_inertia/devtools/entries' => 'outil de développement d\'Inertia, hors production',
        'GET /_inertia/devtools/entries/{id}' => 'outil de développement d\'Inertia, hors production',
    ];

    /** @return array<string, array{nature: 'lit'|'liste'|'ecrit', role: 'membre'|'proprietaire', parametres?: Closure(string): array<string, string>, charge?: Closure(string): array<string, mixed>}> */
    public static function entrees(): array
    {
        $jardin = fn (string $id): array => ['nom' => 'Jardin renommé', 'profil_climatique_id' => 2];

        return [
            'GET /' => ['nature' => 'liste', 'role' => 'membre'],
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
