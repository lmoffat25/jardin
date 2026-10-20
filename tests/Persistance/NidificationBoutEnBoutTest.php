<?php

// V70, critère 4 : la règle de haie de la bibliothèque, chargée en base, copiée
// sur une plante de chacun des cinq jardins témoins (un par profil), n'est
// jamais proposée du 16/03 au 15/08 par le calcul complet : le pont, puis la
// matérialisation des manquées, tous les trois jours sur trois ans. Dans les
// jardins de rang impair la haie est taillée dès qu'elle est proposée, dans les
// autres jamais.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Bibliotheque\Domaine\RegleReference;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Echeances\Moteur\Etat;
use Src\Jardin\Domaine\Plante;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-04');

/**
 * « JJ/MM » => [jour, mois]
 *
 * @return array{int, int}
 */
function jourMoisDe(string $jjmm): array
{
    [$jour, $mois] = array_map('intval', explode('/', $jjmm));

    return [$jour, $mois];
}

/** Copie, sur une plante neuve du jardin courant, la version en vigueur d'une règle de référence. */
function copierRegleDeReference(RegleReference $reference, string $nomPlante): Regle
{
    $regle = Regle::factory()->for(Plante::factory()->state(['nom' => $nomPlante]))->create([
        'regle_reference_id' => $reference->id,
        'type_intervention_id' => $reference->type_intervention_id,
        'type_regle' => $reference->type_regle,
        'periodicite_annees' => $reference->parametres['periodicite_annees'],
        'confiance' => $reference->confiance,
    ]);
    foreach ($reference->parametres['fenetres'] as $ordre => $f) {
        $colonnes = ['ordre' => $ordre + 1, 'libelle' => $f['libelle'] ?? null];
        foreach (['debut' => $f['debut'], 'fin' => $f['fin']] as $borne => $jjmm) {
            [$colonnes["{$borne}_jour"], $colonnes["{$borne}_mois"]] = jourMoisDe($jjmm);
        }
        foreach (['optimal' => $f['optimal'] ?? null, 'butoir' => $f['butoirs'] ?? null] as $prefixe => $paire) {
            if ($paire !== null) {
                [$colonnes["{$prefixe}_debut_jour"], $colonnes["{$prefixe}_debut_mois"]] = jourMoisDe($paire[0]);
                [$colonnes["{$prefixe}_fin_jour"], $colonnes["{$prefixe}_fin_mois"]] = jourMoisDe($paire[1]);
            }
        }
        Fenetre::factory()->for($regle)->create($colonnes);
    }

    return $regle;
}

/** La période touche-t-elle une nidification, du 16/03 au 15/08 d'une année qu'elle couvre ? */
function toucheLaNidification(DateTimeInterface $debut, DateTimeInterface $fin): bool
{
    foreach (range((int) $debut->format('Y'), (int) $fin->format('Y')) as $annee) {
        if ($debut->format('Y-m-d') <= "{$annee}-08-15" && $fin->format('Y-m-d') >= "{$annee}-03-16") {
            return true;
        }
    }

    return false;
}

it('ne propose jamais la haie de la bibliothèque du 16/03 au 15/08, dans aucun des cinq jardins témoins', function () {
    $this->seed(ReferenceSeeder::class);
    $this->artisan('bibliotheque:charger')->assertSuccessful();
    $haies = RegleReference::query()->whereNull('remplacee_le')->whereIn('cle', ['haie-persistante-taille', 'haie-caduque-taille'])->orderBy('cle')->get();
    expect($haies)->toHaveCount(2);

    $this->travelTo(new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $jardins = ProfilClimatique::query()->orderBy('ordre_affichage')->get()->map(function (ProfilClimatique $profil) use ($haies) {
        $jardin = Jardin::factory()->create(['profil_climatique_id' => $profil->id]);
        app(JardinCourant::class)->positionner($jardin->id);
        foreach ($haies as $haie) {
            copierRegleDeReference($haie, "Haie {$haie->cle}");
        }

        return $jardin;
    })->values();
    expect($jardins)->toHaveCount(5);

    // Tous les trois jours sur trois ans, et les veilles et lendemains de la nidification.
    $jours = [];
    for ($jour = new DateTimeImmutable('2026-01-01'); $jour < new DateTimeImmutable('2029-01-01'); $jour = $jour->modify('+3 days')) {
        $jours[] = $jour->format('Y-m-d');
    }
    foreach ([2026, 2027, 2028] as $annee) {
        array_push($jours, "{$annee}-03-15", "{$annee}-03-16", "{$annee}-08-15", "{$annee}-08-16");
    }
    $jours = array_values(array_unique($jours));
    sort($jours);

    $proposees = 0;
    foreach ($jours as $jour) {
        $this->travelTo(new DateTimeImmutable("{$jour} 08:00:00", new DateTimeZone('Europe/Paris')));
        $annee = substr($jour, 0, 4);
        $enNidification = $jour >= "{$annee}-03-16" && $jour <= "{$annee}-08-15";

        foreach ($jardins as $i => $jardin) {
            app(JardinCourant::class)->positionner($jardin->id);
            $calculees = app(CalculEcheances::class)->pourJardin($jardin);
            app(MaterialiserManquees::class)->executer($calculees);

            foreach ($calculees as $e) {
                /** @var EcheanceCalculee $e */
                $periode = $e->echeance->periode;
                $ou = "{$jour}, profil {$jardin->profil_climatique_id}, {$e->plante->nom} : {$e->echeance->etat->value} {$periode->debut->format('Y-m-d')} → {$periode->fin->format('Y-m-d')}";
                $proposee = ! in_array($e->echeance->etat, [Etat::AVenir, Etat::Manquee], true);

                expect(toucheLaNidification($periode->debut, $periode->fin))->toBeFalse($ou)
                    ->and($enNidification && $proposee)->toBeFalse($ou);

                if ($proposee) {
                    $proposees++;
                    if ($i % 2 === 1) {
                        Intervention::factory()->create(['plante_id' => $e->plante->id, 'type_intervention_id' => $e->type->id, 'faite_le' => $jour]);
                    }
                }
            }
        }
    }

    // Le parcours a proposé la haie, et laissé des manquées écrites hors nidification dans les jardins jamais taillés.
    expect($proposees)->toBeGreaterThan(0);
    foreach ($jardins as $i => $jardin) {
        app(JardinCourant::class)->positionner($jardin->id);
        $manquees = EcheanceMaterialisee::query()->get();
        if ($i % 2 === 0) {
            expect($manquees)->not->toBeEmpty("profil {$jardin->profil_climatique_id}");
        }
        foreach ($manquees as $manquee) {
            expect(toucheLaNidification($manquee->getAttribute('fenetre_debut'), $manquee->getAttribute('fenetre_fin')))->toBeFalse($manquee->explication);
        }
    }
});
