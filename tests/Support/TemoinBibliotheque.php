<?php

namespace Tests\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * Une bibliothèque témoin (V62 à V64) au format de `bibliotheque/` (D-19,
 * D-93) : des espèces et des valeurs inventées (RG-28), qui ne disent rien
 * de l'agronomie et passent les contrôles de structure. Chaque test part
 * d'une espèce valide et en fausse une seule clé.
 */
final class TemoinBibliotheque
{
    /**
     * Un fruitier témoin, une règle de taille R2 vérifiée par deux sources.
     *
     * @return array<string, mixed>
     */
    public static function fruitier(): array
    {
        return [
            'cle' => 'fruitier-temoin',
            'nom_commun' => 'Fruitier témoin',
            'nom_latin' => 'Arbor exemplaris',
            'famille' => 'Famille témoin',
            'categorie' => 'fruitier',
            'regles' => [[
                'cle' => 'fruitier-temoin-taille',
                'type_intervention' => 'Taille d\'entretien/fructification',
                'type_regle' => 'R2',
                'confiance' => 'verifiee',
                'sources' => [
                    ['url' => 'https://institut.exemple.test/fruitier', 'libelle' => 'Institut témoin', 'consultee_le' => '2026-10-20'],
                    ['url' => 'https://conservatoire.exemple.test/fruitier', 'libelle' => 'Conservatoire témoin', 'consultee_le' => '2026-10-20'],
                ],
                'fenetres' => [[
                    'debut' => '01/02', 'fin' => '31/03',
                    'optimal' => ['15/02', '15/03'],
                    'libelle' => 'Fin d\'hiver',
                ]],
                'periodicite_annees' => 1,
            ]],
        ];
    }

    /**
     * Une pelouse témoin, une règle de tonte R1 indicative à une source.
     *
     * @return array<string, mixed>
     */
    public static function pelouse(): array
    {
        return [
            'cle' => 'pelouse-temoin',
            'nom_commun' => 'Pelouse témoin',
            'famille' => 'Famille témoin',
            'categorie' => 'gazon',
            'regles' => [[
                'cle' => 'pelouse-temoin-tonte',
                'type_intervention' => 'Tonte',
                'type_regle' => 'R1',
                'confiance' => 'indicative',
                'sources' => [
                    ['url' => 'https://vulgarisation.exemple.test/tonte', 'libelle' => 'Fiche témoin', 'consultee_le' => '2026-10-20'],
                ],
                'intervalles' => [
                    ['mois_debut' => 4, 'mois_fin' => 9, 'jours' => 7],
                    ['mois_debut' => 10, 'mois_fin' => 3, 'jours' => 0],
                ],
            ]],
        ];
    }

    /**
     * Un petit fruit témoin à deux variantes de conduite (RG-51, V65) : une
     * récolte commune, un rabattage par variante, indicatifs à une source.
     *
     * @return array<string, mixed>
     */
    public static function framboisier(): array
    {
        $regle = fn (string $cle, string $type, string $debut, string $fin) => [
            'cle' => "framboisier-temoin-{$cle}", 'type_intervention' => $type, 'type_regle' => 'R2', 'confiance' => 'indicative',
            'sources' => [['url' => "https://vulgarisation.exemple.test/{$cle}", 'libelle' => 'Fiche témoin', 'consultee_le' => '2026-10-20']],
            'fenetres' => [['debut' => $debut, 'fin' => $fin]], 'periodicite_annees' => 1,
        ];

        return [
            'cle' => 'framboisier-temoin',
            'nom_commun' => 'Framboisier témoin',
            'famille' => 'Famille témoin',
            'categorie' => 'petits_fruits',
            'regles' => [
                $regle('recolte', 'Récolte', '01/07', '31/08'),
                ['variante' => 'remontant', ...$regle('taille-remontant', 'Rabattage', '01/11', '28/02')],
                ['variante' => 'non-remontant', ...$regle('taille-non-remontant', 'Rabattage', '01/08', '30/09')],
            ],
        ];
    }

    /**
     * Écrit les fichiers dans un dossier temporaire neuf, et le rend.
     *
     * @param  array<string, list<array<string, mixed>>>  $fichiers  nom sans extension => espèces
     * @param  list<string>  $exclus  domaines de `sources-exclues.yaml`
     */
    public static function dossier(array $fichiers, array $exclus = []): string
    {
        $dossier = sys_get_temp_dir().'/bibliotheque-'.bin2hex(random_bytes(6));
        mkdir($dossier);
        foreach ($fichiers as $nom => $especes) {
            file_put_contents("{$dossier}/{$nom}.yaml", Yaml::dump(['especes' => $especes], 8, 2));
        }
        $domaines = array_map(fn (string $d) => ['domaine' => $d, 'raison' => 'site marchand témoin'], $exclus);
        file_put_contents("{$dossier}/sources-exclues.yaml", Yaml::dump(['domaines' => $domaines], 4, 2));

        return $dossier;
    }

    /** Le dossier témoin habituel : un fruitier et une pelouse, deux fichiers. */
    public static function complet(): string
    {
        return self::dossier(['fruitiers' => [self::fruitier()], 'pelouses' => [self::pelouse()]]);
    }
}
