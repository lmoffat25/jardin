<?php

// V72, critère 13 : deux jardins en base, l'un « Frais », l'autre « Très doux »,
// avec la même plante et la même règle de taille : les fenêtres calculées sont
// décalées de 22 jours, et chaque explication dit le décalage appliqué.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\EcheanceCalculee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Support\Jardin\JardinCourant;

uses(RefreshDatabase::class)->group('critere-13');

/** Un jardin du profil nommé, un pommier, sa taille du 01/02 au 31/03 ; rend la seule échéance calculée. */
function tailleDuPommier(string $profil): EcheanceCalculee
{
    $jardin = Jardin::factory()->create([
        'profil_climatique_id' => ProfilClimatique::query()->where('libelle', $profil)->value('id'),
    ]);
    app(JardinCourant::class)->positionner($jardin->id);
    $regle = Regle::factory()->for(Plante::factory()->state(['nom' => 'Pommier']))->create();
    Fenetre::factory()->for($regle)->create();

    return app(CalculEcheances::class)->pourJardin($jardin->fresh() ?? $jardin)->sole();
}

it('décale de 22 jours la taille d\'un jardin « Frais » sur celle d\'un jardin « Très doux »', function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-10-20 10:00:00', new DateTimeZone('Europe/Paris')));

    $frais = tailleDuPommier('Frais');
    $tresDoux = tailleDuPommier('Très doux');

    expect($frais->echeance->periode->debut->format('Y-m-d'))->toBe('2027-02-08')
        ->and($frais->echeance->periode->fin->format('Y-m-d'))->toBe('2027-04-07')
        ->and($tresDoux->echeance->periode->debut->format('Y-m-d'))->toBe('2027-01-17')
        ->and($tresDoux->echeance->periode->fin->format('Y-m-d'))->toBe('2027-03-16')
        ->and($tresDoux->echeance->periode->debut->diff($frais->echeance->periode->debut)->days)->toBe(22)
        ->and($tresDoux->echeance->periode->fin->diff($frais->echeance->periode->fin)->days)->toBe(22);

    $phrase = fn (EcheanceCalculee $e) => $e->echeance->explication->enPhrase($e->type->libelle, $e->plante->nom);
    expect($phrase($frais))->toContain('décalée de 7 jours pour votre climat')
        ->and($phrase($tresDoux))->toContain('avancée de 15 jours pour votre climat');
});
