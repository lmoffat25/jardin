<?php

// V73, critère 6 : le 01/04/2027, le premier calcul écrit une échéance
// `manquee` avec son explication et son décalage appliqué ; un second calcul
// n'écrit rien de plus (RG-07, D3). Seules les manquées s'écrivent : une
// échéance à venir se calcule, elle ne s'écrit pas (S10).

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-06');

/** Le calcul du jardin courant, puis l'écriture de ses manquées ; rend le calcul et le nombre de lignes écrites. */
function calculerEtMaterialiser(Jardin $jardin): array
{
    $calculees = app(CalculEcheances::class)->pourJardin($jardin->fresh() ?? $jardin);

    return [$calculees, app(MaterialiserManquees::class)->executer($calculees)];
}

/** @param  Collection<int, EcheanceCalculee>  $calculees */
function etatsCalcules(Collection $calculees): array
{
    return $calculees->map(fn (EcheanceCalculee $e) => [$e->echeance->etat->value, $e->echeance->dueLe->format('Y-m-d')])->all();
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);

    // Un jardin « Doux » (-7 jours), un pommier à tailler du 01/02 au 31/03, règle posée à l'automne 2026.
    $this->travelTo(new DateTimeImmutable('2026-10-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardin = Jardin::factory()->create(['profil_climatique_id' => ProfilClimatique::query()->where('libelle', 'Doux')->value('id')]);
    app(JardinCourant::class)->positionner($this->jardin->id);
    $this->regle = Regle::factory()->for(Plante::factory()->state(['nom' => 'Pommier du verger']))->create();
    Fenetre::factory()->for($this->regle)->create();
});

it('écrit la taille non soldée au 01/04/2027 une seule fois, avec sa phrase et son décalage', function () {
    $this->travelTo(new DateTimeImmutable('2027-04-01 08:00:00', new DateTimeZone('Europe/Paris')));

    [$premier, $ecrites] = calculerEtMaterialiser($this->jardin);

    expect(etatsCalcules($premier))->toBe([['manquee', '2027-01-25'], ['a_venir', '2028-01-25']])
        ->and($ecrites)->toBe(1);

    $manquee = EcheanceMaterialisee::query()->sole();
    expect($manquee->only(['jardin_id', 'regle_id', 'plante_id', 'etat']))->toBe([
        'jardin_id' => $this->jardin->id,
        'regle_id' => $this->regle->id,
        'plante_id' => $this->regle->plante_id,
        'etat' => 'manquee',
    ])
        ->and($manquee->due_le->format('Y-m-d'))->toBe('2027-01-25')
        ->and($manquee->getAttribute('fenetre_debut')->format('Y-m-d'))->toBe('2027-01-25')
        ->and($manquee->getAttribute('fenetre_fin')->format('Y-m-d'))->toBe('2027-03-24')
        ->and($manquee->getAttribute('optimal_debut'))->toBeNull()
        ->and($manquee->getAttribute('decalage_climatique_applique_j'))->toBe(-7)
        ->and($manquee->explication)->toBe('Taille d\'entretien/fructification · Pommier du verger : règle de la bibliothèque, fenêtre févr.–mars, avancée de 7 jours pour votre climat.');

    [$second, $ecrites] = calculerEtMaterialiser($this->jardin);

    expect($ecrites)->toBe(0)
        ->and(EcheanceMaterialisee::query()->count())->toBe(1)
        // Close, elle n'est plus rendue : l'échéance suivante l'est (RG-07).
        ->and(etatsCalcules($second))->toBe([['a_venir', '2028-01-25']]);
});

it('n\'écrit rien tant qu\'aucune échéance n\'est manquée', function () {
    $this->travelTo(new DateTimeImmutable('2027-03-01 08:00:00', new DateTimeZone('Europe/Paris')));

    [$calculees, $ecrites] = calculerEtMaterialiser($this->jardin);

    expect(etatsCalcules($calculees))->toBe([['ouverte', '2027-01-25']])
        ->and($ecrites)->toBe(0)
        ->and(EcheanceMaterialisee::query()->count())->toBe(0);
});

it('écrit chaque manquée d\'une règle oubliée deux hivers, dans l\'ordre', function () {
    $this->travelTo(new DateTimeImmutable('2028-05-01 08:00:00', new DateTimeZone('Europe/Paris')));

    [, $ecrites] = calculerEtMaterialiser($this->jardin);

    expect($ecrites)->toBe(2)
        ->and(EcheanceMaterialisee::query()->orderBy('due_le')->pluck('due_le')->map->format('Y-m-d')->all())->toBe(['2027-01-25', '2028-01-25']);
});
