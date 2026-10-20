<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Le jeu de référence, chargé à chaque installation et rejouable (chapitre 4 §5) :
 * les 5 profils climatiques (CDC §5.4) et les types d'intervention fournis (CDC §3.7).
 * En DB::table : les modèles naissent aux 2b et 4a (D-38).
 */
class ReferenceSeeder extends Seeder
{
    /** id => [libellé, décalage en jours] ; l'id donne aussi l'ordre d'affichage. */
    private const PROFILS = [
        1 => ['Très doux', -15],
        2 => ['Doux', -7],
        3 => ['Tempéré', 0],
        4 => ['Frais', 7],
        5 => ['Montagne', 21],
    ];

    /** famille => libellés fournis (D-78). */
    private const TYPES = [
        'taille' => ['Taille de formation', 'Taille d\'entretien/fructification', 'Taille en vert', 'Rabattage', 'Taille de haie', 'Éclaircissage des fruits'],
        'traitement' => ['Traitement cuivre', 'Huile blanche', 'Purin', 'Anti-limaces', 'Badigeon', 'Chaulage'],
        'sol' => ['Amendement', 'Compost', 'Paillage', 'Désherbage', 'Binage'],
        'eau' => ['Arrosage'],
        'entretien' => ['Tonte', 'Débroussaillage', 'Ramassage des feuilles'],
        'cycle' => ['Semis', 'Plantation', 'Repiquage', 'Tuteurage', 'Palissage', 'Protection hivernale'],
        'recolte' => ['Récolte'],
    ];

    public function run(): void
    {
        $profils = [];
        foreach (self::PROFILS as $id => [$libelle, $decalage]) {
            $profils[] = ['id' => $id, 'libelle' => $libelle, 'decalage_jours' => $decalage, 'ordre_affichage' => $id];
        }
        DB::table('profils_climatiques')->upsert($profils, ['id'], ['libelle', 'decalage_jours', 'ordre_affichage']);

        // updateOrInsert, pas upsert : l'upsert de Laravel ne sait pas viser l'index
        // unique partiel des types fournis (D-38).
        foreach (self::TYPES as $famille => $libelles) {
            foreach ($libelles as $libelle) {
                DB::table('types_intervention')->updateOrInsert(
                    ['jardin_id' => null, 'libelle' => $libelle],
                    ['famille' => $famille],
                );
            }
        }
    }
}
