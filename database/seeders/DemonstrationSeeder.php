<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use LogicException;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\Domaine\RegleReference;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Comptes\Jardins\CreerJardin;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Jardin\Localisation\EnregistrerPosition;
use Src\Jardin\Plantes\CreerPlante;
use Src\Journal\Saisie\EnregistrerIntervention;
use Src\Journal\Saisie\SaisieIntervention;
use Src\Support\Horloge;

/**
 * Le jeu de démonstration (chapitre 4 §5, D-14, V125) : un jardin fictif de
 * 500 plantes, 470 en points, 20 haies en lignes et 10 pelouses en polygones,
 * sur des coordonnées inventées, avec deux ans d'interventions. Tout passe par
 * les actions du domaine (`CreerJardin`, `CreerPlante`, `EnregistrerPosition`,
 * `EnregistrerIntervention`), comme une saisie : règles copiées de la
 * bibliothèque, codes courts, échéances soldées. Relancé, il supprime puis
 * recrée ce seul jardin. Jamais en production.
 */
class DemonstrationSeeder extends Seeder
{
    public const NOM = 'Démonstration';

    public const COURRIEL = 'demonstration@exemple.invalid';

    /**
     * Le point fictif autour duquel tout est placé, [lng, lat] : un nombre rond
     * choisi pour l'exemple, jamais la position d'un jardin réel (C4, RG-28).
     */
    public const CENTRE = [2.5, 46.5];

    /** Profil « Tempéré », décalage nul (CDC §5.4). */
    private const PROFIL = 3;

    /** Nom de zone => catégories d'espèces qui y poussent, en points. */
    private const ZONES_EN_POINTS = [
        'Verger haut' => ['fruitier'],
        'Verger bas' => ['fruitier'],
        'Petits fruits' => ['petits_fruits'],
        'Treille' => ['vigne'],
        'Massifs' => ['arbuste', 'ornement'],
        'Bosquet' => ['arbuste', 'ornement'],
    ];

    private const ZONE_HAIES = 'Haies';

    private const ZONE_PELOUSES = 'Pelouses';

    private const POINTS = 470;

    private const LIGNES = 20;

    private const POLYGONES = 10;

    /** Un mètre, à peu près, en degrés à cette latitude. */
    private const METRE = 0.00001;

    public function run(CreerJardin $creerJardin, CreerPlante $creerPlante, EnregistrerPosition $position, EnregistrerIntervention $enregistrer, Horloge $horloge): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Le jardin de démonstration ne se crée jamais en production.');
        }

        $this->call(ReferenceSeeder::class);
        if (! Espece::query()->exists()) {
            Artisan::call('bibliotheque:charger');
        }

        $proprietaire = Utilisateur::query()->firstOrCreate(
            ['courriel' => self::COURRIEL],
            ['mot_de_passe_hache' => Str::random(40), 'nom_affiche' => 'Démonstration'],
        );
        Jardin::query()->where('nom', self::NOM)->where('proprietaire_id', $proprietaire->id)->delete();
        $creerJardin->executer($proprietaire, self::NOM, self::PROFIL);

        $plantes = $this->planter($creerPlante, $position);
        $this->historique($plantes, $proprietaire, $enregistrer, $horloge);
    }

    /**
     * Les 500 plantes, chacune placée sur le plan.
     *
     * @return list<Plante> dans l'ordre de création
     */
    private function planter(CreerPlante $creerPlante, EnregistrerPosition $position): array
    {
        $zones = [];
        foreach ([...array_keys(self::ZONES_EN_POINTS), self::ZONE_HAIES, self::ZONE_PELOUSES] as $nom) {
            $zones[$nom] = Zone::create(['nom' => $nom])->id;
        }

        $plantes = [];
        // Les points : les zones à tour de rôle, les espèces de la zone à tour de rôle,
        // sur une grille de 4 m, une bande de 30 m de large par zone.
        $parZone = array_map(fn (array $categories) => Espece::query()->whereIn('categorie', $categories)->orderBy('cle')->get()->all(), self::ZONES_EN_POINTS);
        $nomsZones = array_keys(self::ZONES_EN_POINTS);
        for ($i = 0; $i < self::POINTS; $i++) {
            $z = $i % count($nomsZones);
            $rang = intdiv($i, count($nomsZones));
            $especes = $parZone[$nomsZones[$z]];
            $plante = $this->creer($creerPlante, $zones[$nomsZones[$z]], $especes[$rang % count($especes)], $rang + 1);
            $position->depuisPlan($plante, ['type' => 'Point', 'coordinates' => $this->point(-90 + 30 * $z + 4 * ($rang % 7), -80 + 4 * intdiv($rang, 7))]);
            $plantes[] = $plante;
        }

        // Les haies : des lignes de 25 m, en bordure nord et est.
        $haies = Espece::query()->where('categorie', 'haie')->orderBy('cle')->get()->all();
        for ($i = 0; $i < self::LIGNES; $i++) {
            $plante = $this->creer($creerPlante, $zones[self::ZONE_HAIES], $haies[$i % count($haies)], $i + 1);
            $debut = $i < 10 ? [-100 + 25 * $i, 100] : [100, 100 - 25 * ($i - 10)];
            $fin = $i < 10 ? [$debut[0] + 24, 100] : [100, $debut[1] - 24];
            $position->depuisPlan($plante, ['type' => 'LineString', 'coordinates' => [$this->point(...$debut), $this->point(...$fin)]]);
            $plantes[] = $plante;
        }

        // Les pelouses : des carrés de 15 m, au sud.
        $pelouse = Espece::query()->where('categorie', 'gazon')->orderBy('cle')->firstOrFail();
        for ($i = 0; $i < self::POLYGONES; $i++) {
            $plante = $this->creer($creerPlante, $zones[self::ZONE_PELOUSES], $pelouse, $i + 1);
            [$x, $y] = [-100 + 20 * $i, -100];
            $anneau = [$this->point($x, $y), $this->point($x + 15, $y), $this->point($x + 15, $y - 15), $this->point($x, $y - 15), $this->point($x, $y)];
            $position->depuisPlan($plante, ['type' => 'Polygon', 'coordinates' => [$anneau]]);
            $plantes[] = $plante;
        }

        return $plantes;
    }

    private function creer(CreerPlante $creerPlante, string $zone, Espece $espece, int $numero): Plante
    {
        // Une espèce à variantes (RG-51) prend la première : sinon aucune règle ne serait copiée.
        $variante = RegleReference::query()->where('espece_id', $espece->id)->whereNotNull('variante')->orderBy('variante')->value('variante');

        return $creerPlante->executer([
            'nom' => "{$espece->nom_commun} {$numero}",
            'zone_id' => $zone,
            'espece_id' => $espece->id,
            'variante' => is_string($variante) ? $variante : null,
        ]);
    }

    /**
     * Deux ans d'interventions, jusqu'à aujourd'hui : chaque règle R2 faite au
     * début de sa fenêtre, chaque R1 à son intervalle dans ses saisons. Une
     * plante sur cinq a manqué la dernière saison, pour que le tableau de bord
     * ait de quoi montrer. Une saisie par jour et par type, l'horloge posée au
     * soir de ce jour (saisie sur le terrain, solde à cette date).
     *
     * @param  list<Plante>  $plantes
     */
    private function historique(array $plantes, Utilisateur $auteur, EnregistrerIntervention $enregistrer, Horloge $horloge): void
    {
        $aujourdhui = $horloge->aujourdhui();
        $debut = $aujourdhui->modify('-2 years +1 day');
        $rangs = array_flip(array_map(fn (Plante $p) => $p->id, $plantes));

        /** @var array<string, array<int, list<string>>> $saisies date => type => plantes */
        $saisies = [];
        $regles = Regle::query()->where('est_active', true)->with(['fenetres', 'intervalles'])->get();
        foreach ($regles as $regle) {
            $omise = $rangs[$regle->plante_id] % 5 === 0;
            foreach ($this->dates($regle, $debut, $aujourdhui) as $date) {
                if ($omise && $date > $aujourdhui->modify('-1 year')) {
                    continue;
                }
                $saisies[$date->format('Y-m-d')][$regle->type_intervention_id][] = $regle->plante_id;
            }
        }
        ksort($saisies);

        $avant = Date::getTestNow();
        try {
            foreach ($saisies as $jour => $parType) {
                Date::setTestNow(new \DateTimeImmutable("{$jour} 18:00:00", new \DateTimeZone('Europe/Paris')));
                foreach ($parType as $type => $ids) {
                    $enregistrer->executer(new SaisieIntervention(
                        lotSaisieId: count($ids) > 1 ? (string) Str::uuid() : null,
                        typeId: $type,
                        faiteLe: $jour,
                        note: null,
                        dureeMin: null,
                        position: null,
                        lignes: array_map(fn (string $p) => ['id' => (string) Str::uuid(), 'plante_id' => $p], $ids),
                    ), $auteur);
                }
            }
        } finally {
            Date::setTestNow($avant);
        }
    }

    /** @return list<\DateTimeImmutable> les jours où la règle a été suivie, entre $debut et $fin */
    private function dates(Regle $regle, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $dates = [];
        if ($regle->type_regle === 'R1') {
            for ($jour = $debut; $jour <= $fin; $jour = $jour->modify('+1 day')) {
                $mois = (int) $jour->format('n');
                // Une saison à intervalle nul est une saison sans passage.
                foreach ($regle->intervalles as $saison) {
                    $dedans = $saison->mois_debut <= $saison->mois_fin
                        ? $mois >= $saison->mois_debut && $mois <= $saison->mois_fin
                        : $mois >= $saison->mois_debut || $mois <= $saison->mois_fin;
                    if ($dedans && $saison->intervalle_jours > 0 && (int) $jour->format('z') % $saison->intervalle_jours === 0) {
                        $dates[] = $jour;
                    }
                }
            }

            return $dates;
        }

        $fenetre = $regle->fenetres->sortBy('ordre')->first();
        if ($fenetre === null) {
            return [];
        }
        for ($annee = (int) $debut->format('Y'); $annee <= (int) $fin->format('Y'); $annee++) {
            // Quelques jours après l'ouverture de la fenêtre, comme on s'y met.
            $jour = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $annee, $fenetre->debut_mois, $fenetre->debut_jour)))->modify('+3 days');
            if ($jour >= $debut && $jour <= $fin) {
                $dates[] = $jour;
            }
        }

        return $dates;
    }

    /**
     * [lng, lat] à $x mètres à l'est et $y mètres au nord du centre.
     *
     * @return array{float, float}
     */
    private function point(float|int $x, float|int $y): array
    {
        return [round(self::CENTRE[0] + $x * self::METRE * 1.45, 7), round(self::CENTRE[1] + $y * self::METRE, 7)];
    }
}
