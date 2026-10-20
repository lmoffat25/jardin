<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;

/**
 * Deux jardins complets, A et B, pour les tests de cloisonnement (V41) et de
 * rôles (V128). Les noms des zones et des plantes de B portent `MARQUEUR-B` :
 * un corps de réponse qui le contient a laissé passer B. Le nom du jardin B,
 * lui, n'en porte pas, parce que les props partagées le montrent, à bon droit,
 * à qui adhère aux deux.
 *
 * Toutes les données sont inventées (C4, RG-28). Le test charge
 * `ReferenceSeeder` avant (profil 3).
 */
final class DeuxJardins
{
    public const MARQUEUR = 'MARQUEUR-B';

    public readonly Jardin $a;

    public readonly Jardin $b;

    /** Propriétaire de A, sans adhésion à B. */
    public readonly Utilisateur $proprietaireA;

    /** Propriétaire de B, sans adhésion à A. */
    public readonly Utilisateur $proprietaireB;

    /** Membre de A, sans adhésion à B. */
    public readonly Utilisateur $membreA;

    /** Propriétaire de A et membre de B. */
    public readonly Utilisateur $desDeux;

    /** @var list<string> les identifiants des lignes de B : jardin, zones, plantes */
    public readonly array $identifiantsB;

    public function __construct()
    {
        $this->proprietaireA = Utilisateur::factory()->create();
        $this->proprietaireB = Utilisateur::factory()->create();
        $this->membreA = Utilisateur::factory()->create();
        $this->desDeux = Utilisateur::factory()->create();

        $this->a = Jardin::factory()->create(['nom' => 'Jardin A', 'proprietaire_id' => $this->proprietaireA->id, 'cree_le' => '2026-01-01 00:00:00']);
        $this->b = Jardin::factory()->create(['nom' => 'Jardin B', 'proprietaire_id' => $this->proprietaireB->id, 'cree_le' => '2026-02-01 00:00:00']);

        $this->adherer($this->proprietaireA, $this->a, 'proprietaire');
        $this->adherer($this->membreA, $this->a, 'membre');
        $this->adherer($this->desDeux, $this->a, 'proprietaire');
        $this->adherer($this->proprietaireB, $this->b, 'proprietaire');
        $this->adherer($this->desDeux, $this->b, 'membre');

        $this->peupler($this->a, 'A');
        $this->identifiantsB = [$this->b->id, ...$this->peupler($this->b, self::MARQUEUR)];

        // Le jardin courant d'une requête est posé par le middleware, pas ici.
        app()->forgetScopedInstances();
    }

    /**
     * L'empreinte d'un jardin : un `md5` par table, de toutes ses lignes, lues
     * hors de tout filtre. Couvre la ligne du jardin, chaque table qui porte un
     * `jardin_id`, et les comptes de ses adhésions.
     *
     * @return array<string, string>
     */
    public static function empreinte(string $jardinId): array
    {
        $tables = ['jardins' => DB::table('jardins')->where('id', $jardinId)];
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (Schema::hasColumn($table, 'jardin_id')) {
                $tables[$table] = DB::table($table)->where('jardin_id', $jardinId);
            }
        }
        $tables['utilisateurs'] = DB::table('utilisateurs')
            ->whereIn('id', DB::table('adhesions')->where('jardin_id', $jardinId)->select('utilisateur_id'));

        ksort($tables);

        return array_map(
            fn ($requete) => md5((string) json_encode($requete->get()->map(fn ($l) => (array) $l)->sortBy(fn ($l) => json_encode($l))->values())),
            $tables,
        );
    }

    private function adherer(Utilisateur $u, Jardin $jardin, string $role): void
    {
        Adhesion::create(['utilisateur_id' => $u->id, 'jardin_id' => $jardin->id, 'role' => $role]);
    }

    /**
     * Deux zones et trois plantes dans le jardin ; rend leurs identifiants.
     *
     * @return list<string>
     */
    private function peupler(Jardin $jardin, string $prefixe): array
    {
        app(JardinCourant::class)->positionner($jardin->id);

        $identifiants = [];
        foreach (['Potager', 'Verger'] as $i => $nomZone) {
            $zone = Zone::factory()->create(['nom' => "{$prefixe} {$nomZone}"]);
            $identifiants[] = $zone->id;
            foreach (range(1, $i + 1) as $n) {
                $plante = Plante::factory()->create([
                    'zone_id' => $zone->id,
                    'nom' => "{$prefixe} Plante {$nomZone} {$n}",
                ]);
                $identifiants[] = $plante->id;
            }
        }

        return $identifiants;
    }
}
