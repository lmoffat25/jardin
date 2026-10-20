<?php

// V131 (critère 21, versant serveur, D-65, écran T7) : depuis une position, les
// échéances du jour sont rendues dans l'ordre du plus proche voisin, de plante
// en plante ; les plantes sans position suivent, groupées par zone dans
// l'ordre alphabétique. Sans position, le même groupement par zone, sans tri
// par distance. Aucune échéance de B. Rien n'est écrit en base.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Src\Echeances\Calcul\CalculEcheances;
use Src\Echeances\Calcul\MaterialiserManquees;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Journal\Domaine\Intervention;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-21');

/** Le point d'où part la tournée, au milieu d'un verger inventé. */
const DEPART_TOURNEE = ['lng' => 4.83500, 'lat' => 45.76400];

/** Un pas vers le nord : environ 5,56 mètres. */
const PAS_TOURNEE = 0.00005;

/** Une plante située à `$pas` pas au nord du départ (au sud si négatif). */
function situerEnTournee(string $jardin, string $plante, float $pas): void
{
    DB::table('geometries')->insert([
        'id' => (string) Str::uuid(), 'jardin_id' => $jardin, 'plante_id' => $plante, 'forme' => 'point',
        'coordonnees' => json_encode(['type' => 'Point', 'coordinates' => [DEPART_TOURNEE['lng'], DEPART_TOURNEE['lat'] + $pas * PAS_TOURNEE]]),
        'source' => 'gps', 'relevee_le' => '2026-06-01 12:00:00+02',
    ]);
}

/**
 * Une règle R2 sur la plante, d'une fenêtre `[jour, mois, jour, mois]` ; par
 * défaut, la taille du 01/02 au 31/03.
 */
function regleEnTournee(Plante $plante, string $type = 'Taille d\'entretien/fructification', array $fenetre = [1, 2, 31, 3], array $regle = []): void
{
    [$dj, $dm, $fj, $fm] = $fenetre;
    Fenetre::factory()->for(Regle::factory()->for($plante)->state(['type_intervention_id' => RegleFactory::typeFourni($type), ...$regle]))
        ->create(['debut_jour' => $dj, 'debut_mois' => $dm, 'fin_jour' => $fj, 'fin_mois' => $fm]);
}

/** La tournée demandée par le membre de A depuis A. */
function tournee(array $parametres = []): TestResponse
{
    app()->forgetScopedInstances();

    return test()->actingAs(test()->jardins->membreA)
        ->withSession(['jardin_id' => test()->jardins->a->id])
        ->getJson('/tournee?'.http_build_query($parametres));
}

/** La tournée en « Zone · Plante · Type ». */
function etapes(TestResponse $reponse): array
{
    return array_map(
        fn (array $e) => "{$e['plante']['zone']['nom']} · {$e['plante']['nom']} · {$e['type']['libelle']}",
        $reponse->json('tournee'),
    );
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    // Le 10 mars : la taille (février-mars) et le cuivre de mars-avril sont
    // dus ; le cuivre de janvier est manqué ; celui de novembre est à venir.
    $this->travelTo(new DateTimeImmutable('2027-03-10 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->jardins = new DeuxJardins;
    $a = $this->jardins->a->id;
    app(JardinCourant::class)->positionner($a);
    $verger = Zone::query()->where('nom', 'A Verger')->sole();
    $allee = Zone::factory()->create(['nom' => 'A Allée']);

    // Situées : du départ, le cerisier est à 1 pas au nord, l'abricotier à
    // 2 pas au sud, le prunier à 3,5 pas au nord. Par distance au départ :
    // cerisier, abricotier, prunier ; de plante en plante : cerisier, prunier
    // (2,5 pas), abricotier (5,5 pas).
    foreach (['Cerisier' => 1, 'Abricotier' => -2, 'Prunier' => 3.5] as $nom => $pas) {
        $plante = Plante::factory()->create(['nom' => $nom, 'zone_id' => $verger->id]);
        situerEnTournee($a, $plante->id, $pas);
        regleEnTournee($plante);
        $this->{$nom} = $plante;
    }
    regleEnTournee($this->Cerisier, 'Traitement cuivre', [1, 3, 30, 4]);

    // Sans position, chacune dans sa zone ; l'allée est créée la dernière.
    $rosier = Plante::factory()->create(['nom' => 'Rosier de l\'allée', 'zone_id' => $allee->id]);
    regleEnTournee($rosier);
    // Le cuivre de janvier, fait l'an dernier, ne l'a pas été cette année : manqué.
    regleEnTournee($rosier, 'Traitement cuivre', [1, 1, 20, 1], ['creee_le' => '2026-01-01 00:00:00+01']);
    Intervention::factory()->create(['plante_id' => $rosier->id, 'type_intervention_id' => RegleFactory::typeFourni('Traitement cuivre'), 'faite_le' => '2026-01-10']);
    regleEnTournee(Plante::query()->where('nom', 'A Plante Potager 1')->sole());
    regleEnTournee(Plante::query()->where('nom', 'A Plante Verger 2')->sole());
    regleEnTournee(Plante::query()->where('nom', 'A Plante Verger 1')->sole(), 'Traitement cuivre', [1, 11, 15, 12]);

    // Archivée, au départ même : elle ne se propose plus.
    $archivee = Plante::factory()->create(['nom' => 'Poirier arraché', 'zone_id' => $verger->id, 'etat' => 'archivee', 'archivee_le' => '2027-01-05 10:00:00+01', 'motif_archivage' => 'Arraché']);
    situerEnTournee($a, $archivee->id, 0);
    regleEnTournee($archivee);

    // Les plantes de B sont dues et posées au départ même : aucune ne doit sortir.
    app(JardinCourant::class)->positionner($this->jardins->b->id);
    foreach (Plante::query()->get() as $planteB) {
        situerEnTournee($this->jardins->b->id, $planteB->id, 0);
        regleEnTournee($planteB);
    }
    app()->forgetScopedInstances();
});

it('ordonne les échéances du jour de plante en plante depuis la position, puis les plantes sans position par zone', function () {
    $reponse = tournee(DEPART_TOURNEE)->assertOk();

    expect(etapes($reponse))->toBe([
        'A Verger · Cerisier · Taille d\'entretien/fructification',
        'A Verger · Cerisier · Traitement cuivre',
        'A Verger · Prunier · Taille d\'entretien/fructification',
        'A Verger · Abricotier · Taille d\'entretien/fructification',
        'A Allée · Rosier de l\'allée · Taille d\'entretien/fructification',
        'A Potager · A Plante Potager 1 · Taille d\'entretien/fructification',
        'A Verger · A Plante Verger 2 · Taille d\'entretien/fructification',
    ]);

    // La distance est celle du pas de la tournée : du départ, puis de la plante précédente.
    $distances = array_map(fn (array $e) => $e['distance_m'] ?? null, $reponse->json('tournee'));
    expect($distances)->toBe([6, 0, 14, 31, null, null, null]);

    $premiere = $reponse->json('tournee.0');
    expect(array_keys($premiere))->toBe(['cle', 'plante', 'type', 'distance_m'])
        ->and($premiere['cle'])->toBe(['regle_id' => Regle::query()->where('plante_id', $this->Cerisier->id)->where('type_intervention_id', RegleFactory::typeFourni('Taille d\'entretien/fructification'))->value('id'), 'due_le' => '2027-02-01'])
        ->and($premiere['plante'])->toBe(['id' => $this->Cerisier->id, 'nom' => 'Cerisier', 'code_court' => $this->Cerisier->code_court, 'zone' => ['id' => $this->Cerisier->zone_id, 'nom' => 'A Verger']])
        ->and(array_keys($reponse->json('tournee.4')))->toBe(['cle', 'plante', 'type']);
});

it('sans position, groupe toute la tournée par zone, sans distance', function () {
    $reponse = tournee()->assertOk();

    expect(etapes($reponse))->toBe([
        'A Allée · Rosier de l\'allée · Taille d\'entretien/fructification',
        'A Potager · A Plante Potager 1 · Taille d\'entretien/fructification',
        // L'ordre naturel de l'application (strnatcasecmp) ignore les espaces.
        'A Verger · Abricotier · Taille d\'entretien/fructification',
        'A Verger · A Plante Verger 2 · Taille d\'entretien/fructification',
        'A Verger · Cerisier · Taille d\'entretien/fructification',
        'A Verger · Cerisier · Traitement cuivre',
        'A Verger · Prunier · Taille d\'entretien/fructification',
    ]);
    foreach ($reponse->json('tournee') as $e) {
        expect($e)->not->toHaveKey('distance_m');
    }
});

it('départage deux plantes à la même distance par leur nom', function () {
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $verger = Zone::query()->where('nom', 'A Verger')->value('id');
    // Créées dans l'ordre inverse du nom, au même point, à 4,5 pas au nord : à
    // un pas du prunier, avant l'abricotier.
    foreach (['Myrtillier', 'Groseillier'] as $nom) {
        $plante = Plante::factory()->create(['nom' => $nom, 'zone_id' => $verger]);
        situerEnTournee($this->jardins->a->id, $plante->id, 4.5);
        regleEnTournee($plante);
    }

    expect(array_slice(array_column(array_column(tournee(DEPART_TOURNEE)->assertOk()->json('tournee'), 'plante'), 'nom'), 0, 6))
        ->toBe(['Cerisier', 'Cerisier', 'Prunier', 'Groseillier', 'Myrtillier', 'Abricotier']);
});

it('ne propose aucune échéance de B, même posée au départ', function () {
    $reponse = tournee(DEPART_TOURNEE)->assertOk();

    expect($reponse->getContent())->not->toContain(DeuxJardins::MARQUEUR)
        ->and(count($reponse->json('tournee')))->toBe(7);
});

it('n\'écrit rien en base, pas même la manquée du jour', function () {
    // Le témoin : la liste du jour, elle, écrit la manquée du rosier.
    app()->forgetScopedInstances();
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    expect(app(MaterialiserManquees::class)->executer(app(CalculEcheances::class)->pourJardin($this->jardins->a)))->toBe(1);
    EcheanceMaterialisee::query()->delete();
    app()->forgetScopedInstances();

    $avant = [DeuxJardins::empreinte($this->jardins->a->id), DeuxJardins::empreinte($this->jardins->b->id)];

    tournee(DEPART_TOURNEE)->assertOk();
    tournee()->assertOk();

    expect([DeuxJardins::empreinte($this->jardins->a->id), DeuxJardins::empreinte($this->jardins->b->id)])->toBe($avant);
});

it('refuse une position à moitié ou hors bornes', function (array $parametres, string $champ) {
    tournee($parametres)->assertUnprocessable()->assertJsonValidationErrors($champ);
})->with([
    'latitude seule' => [['lat' => 45.764], 'lng'],
    'longitude hors bornes' => [['lng' => 181, 'lat' => 45.764], 'lng'],
]);
