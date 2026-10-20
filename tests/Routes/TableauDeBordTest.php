<?php

// V76 (critère 3, RG-06, D-47, écrans B4 et T1) : à toute date, le tableau de
// bord rend les échéances dues aujourd'hui, cette semaine et ce mois, groupées
// par zone, chacune avec sa phrase et sa clé `(regle_id, due_le)` ; sans
// session, la connexion ; rien de B ; en base, seules les manquées s'écrivent.
// V80 (CDC §3.9, RG-40) : ce qui n'est pas dû reste au planning, à son mois.

use Database\Factories\RegleFactory;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\ProfilClimatique;
use Src\Comptes\Domaine\Utilisateur;
use Src\Echeances\Domaine\EcheanceMaterialisee;
use Src\Echeances\Domaine\Fenetre;
use Src\Echeances\Domaine\IntervalleSaisonnier;
use Src\Echeances\Domaine\Regle;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;

uses(RefreshDatabase::class)->group('critere-03');

/**
 * Le jardin témoin, « Doux » (-7 jours) : au verger, un pommier à tailler du
 * 01/02 au 31/03 et un cuivre du 01/11 au 15/12 ; à la pelouse, une tonte tous
 * les 7 jours de mars à octobre. Rend son propriétaire.
 */
function jardinDuTableau(): Utilisateur
{
    $utilisateur = Utilisateur::factory()->create(['courriel' => 'tableau@exemple.test', 'mot_de_passe_hache' => 'phrase-temoin']);
    $jardin = Jardin::factory()->create([
        'proprietaire_id' => $utilisateur->id,
        'profil_climatique_id' => ProfilClimatique::query()->where('libelle', 'Doux')->value('id'),
    ]);
    Adhesion::create(['utilisateur_id' => $utilisateur->id, 'jardin_id' => $jardin->id, 'role' => 'proprietaire']);
    app(JardinCourant::class)->positionner($jardin->id);

    $verger = Zone::factory()->create(['nom' => 'Verger']);
    $pommier = Plante::factory()->create(['zone_id' => $verger->id, 'nom' => 'Pommier du fond', 'code_court' => 'FRU-001']);
    Fenetre::factory()->for(Regle::factory()->for($pommier))->create();
    Fenetre::factory()->for(Regle::factory()->for($pommier)->state([
        'type_intervention_id' => RegleFactory::typeFourni('Traitement cuivre'),
    ]))->create(['debut_jour' => 1, 'debut_mois' => 11, 'fin_jour' => 15, 'fin_mois' => 12]);

    $pelouse = Zone::factory()->create(['nom' => 'Pelouse']);
    $gazon = Plante::factory()->create(['zone_id' => $pelouse->id, 'nom' => 'Gazon de l\'entrée', 'code_court' => 'PEL-001']);
    IntervalleSaisonnier::factory()->for(Regle::factory()->r1()->for($gazon))->create();

    app()->forgetScopedInstances();

    return $utilisateur;
}

/**
 * Les échéances d'une période des props, en « Zone · Type · Plante · état ».
 *
 * @param  list<array<string, mixed>>  $zones
 * @return list<string>
 */
function lignesDuTableau(array $zones): array
{
    $lignes = [];
    foreach ($zones as $bloc) {
        foreach ($bloc['echeances'] as $e) {
            expect($e['plante']['zone']['nom'])->toBe($bloc['zone']['nom']);
            $lignes[] = "{$bloc['zone']['nom']} · {$e['type']['libelle']} · {$e['plante']['nom']} · {$e['etat']}";
        }
    }

    return $lignes;
}

/** @return array<string, mixed> les props de la page du tableau de bord, à la date donnée */
function tableauAu(Utilisateur $u, string $date): array
{
    test()->travelTo(new DateTimeImmutable("{$date} 08:00:00", new DateTimeZone('Europe/Paris')));
    app()->forgetScopedInstances();

    $props = [];
    test()->withoutVite()->actingAs($u)->get('/')
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$props) {
            $page->component('Bureau/TableauDeBord');
            $props = $page->toArray()['props'];
        });

    return $props;
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    $this->travelTo(new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('Europe/Paris')));
    $this->proprietaire = jardinDuTableau();
});

it('rend, en automne, la tonte aujourd\'hui, le cuivre ce mois et la taille au planning', function () {
    $props = tableauAu($this->proprietaire, '2026-10-10');

    expect(lignesDuTableau($props['aujourdhui']))->toBe(['Pelouse · Tonte · Gazon de l\'entrée · ouverte'])
        ->and(lignesDuTableau($props['semaine']))->toBe([])
        ->and(lignesDuTableau($props['mois']))->toBe(['Verger · Traitement cuivre · Pommier du fond · a_venir'])
        ->and(array_column($props['planning'], 'mois'))->toBe(['2027-01'])
        ->and(lignesDuTableau($props['planning'][0]['zones']))->toBe(['Verger · Taille d\'entretien/fructification · Pommier du fond · a_venir']);

    $cuivre = $props['mois'][0]['echeances'][0];
    $regle = Regle::query()->withoutGlobalScopes()->where('type_intervention_id', $cuivre['type']['id'])->sole();
    expect($cuivre['cle'])->toBe(['regle_id' => $regle->id, 'due_le' => '2026-10-25'])
        ->and($cuivre['periode'])->toBe(['debut' => '2026-10-25', 'fin' => '2026-12-08', 'optimal_debut' => null, 'optimal_fin' => null])
        ->and($cuivre['plante'])->toMatchArray(['nom' => 'Pommier du fond', 'code_court' => 'FRU-001'])
        ->and($cuivre['explication'])->toBe('Traitement cuivre · Pommier du fond : règle de la bibliothèque, fenêtre nov.–déc., avancée de 7 jours pour votre climat.');
});

it('rend, en hiver, la taille cette semaine, le cuivre aujourd\'hui, la tonte reportée au planning', function () {
    $props = tableauAu($this->proprietaire, '2026-11-20');
    expect(lignesDuTableau($props['aujourdhui']))->toBe(['Verger · Traitement cuivre · Pommier du fond · ouverte']);

    $props = tableauAu($this->proprietaire, '2027-01-20');

    expect(lignesDuTableau($props['aujourdhui']))->toBe([])
        ->and(lignesDuTableau($props['semaine']))->toBe(['Verger · Taille d\'entretien/fructification · Pommier du fond · a_venir'])
        ->and($props['semaine'][0]['echeances'][0]['cle']['due_le'])->toBe('2027-01-25')
        // La tonte de janvier est reportée à l'ouverture de sa saison : au planning, pas aujourd'hui (V80, RG-40).
        ->and(array_column($props['planning'], 'mois'))->toBe(['2027-02', '2027-10'])
        ->and(lignesDuTableau($props['planning'][0]['zones']))->toBe(['Pelouse · Tonte · Gazon de l\'entrée · a_venir']);
});

it('rend, au printemps, la tonte aujourd\'hui ; la taille oubliée est écrite manquée, et seule elle', function () {
    $avant = DeuxJardins::empreinte(Jardin::query()->sole()->id);

    $props = tableauAu($this->proprietaire, '2027-04-15');

    expect(lignesDuTableau($props['aujourdhui']))->toBe(['Pelouse · Tonte · Gazon de l\'entrée · ouverte'])
        ->and(collect($props['planning'])->flatMap(fn ($m) => lignesDuTableau($m['zones']))->all())
        ->toContain('Verger · Taille d\'entretien/fructification · Pommier du fond · a_venir');

    $ecrites = EcheanceMaterialisee::query()->withoutGlobalScopes()->get();
    expect($ecrites->pluck('etat')->all())->toBe(['manquee', 'manquee'])
        ->and($ecrites->pluck('due_le')->map->format('Y-m-d')->sort()->values()->all())->toBe(['2026-10-25', '2027-01-25']);

    $apres = DeuxJardins::empreinte(Jardin::query()->sole()->id);
    expect(array_diff_key($apres, ['echeances' => 0]))->toBe(array_diff_key($avant, ['echeances' => 0]));

    // Un second affichage n'écrit rien de plus.
    tableauAu($this->proprietaire, '2027-04-15');
    expect(EcheanceMaterialisee::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('n\'a pas d\'échéance du jour qui ne soit pas due (V80)', function (string $date) {
    $props = tableauAu($this->proprietaire, $date);

    foreach (['aujourdhui'] as $periode) {
        foreach ($props[$periode] as $bloc) {
            foreach ($bloc['echeances'] as $e) {
                expect($e['etat'])->toBeIn(['ouverte', 'optimale', 'urgente']);
            }
        }
    }
    foreach (['semaine', 'mois'] as $periode) {
        foreach ($props[$periode] as $bloc) {
            foreach ($bloc['echeances'] as $e) {
                expect($e['etat'])->toBe('a_venir');
            }
        }
    }
    foreach ($props['planning'] as $mois) {
        foreach ($mois['zones'] as $bloc) {
            foreach ($bloc['echeances'] as $e) {
                expect($e['etat'])->toBe('a_venir')
                    ->and(substr($e['cle']['due_le'], 0, 7))->toBe($mois['mois']);
            }
        }
    }
})->with(['2026-10-10', '2027-01-05', '2027-04-15', '2027-07-01']);

it('rend la liste du terrain : les échéances du jour, zones par nom', function () {
    app(JardinCourant::class)->positionner(Jardin::query()->sole()->id);
    $verger = Zone::query()->where('nom', 'Verger')->sole();
    $herbe = Plante::factory()->create(['zone_id' => $verger->id, 'nom' => 'Herbe du verger', 'code_court' => 'PEL-002']);
    IntervalleSaisonnier::factory()->for(Regle::factory()->r1()->for($herbe))->create();
    app()->forgetScopedInstances();
    $this->travelTo(new DateTimeImmutable('2026-10-10 08:00:00', new DateTimeZone('Europe/Paris')));

    $this->withoutVite()->actingAs($this->proprietaire)->get('/terrain')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Terrain/AFaire')
            ->count('aujourdhui', 2)
            ->where('aujourdhui.0.zone.nom', 'Pelouse')
            ->where('aujourdhui.0.echeances.0.type.libelle', 'Tonte')
            ->where('aujourdhui.1.zone.nom', 'Verger')
            ->where('aujourdhui.1.echeances.0.plante.nom', 'Herbe du verger')
            ->missing('semaine'));
});

it('arrive sur le tableau de bord après la connexion', function () {
    $this->withoutVite()->followingRedirects()
        ->post('/connexion', ['courriel' => 'tableau@exemple.test', 'mot_de_passe' => 'phrase-temoin'])
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Bureau/TableauDeBord'));
});

it('renvoie vers la connexion sans session', function (string $chemin) {
    $this->get($chemin)->assertRedirect('/connexion');
})->with(['/', '/terrain', '/echeances/groupe?zone=x&type=1&periode=aujourdhui']);

it('ne montre rien de B, ni au tableau, ni au terrain, ni au détail d\'un groupe', function () {
    $jardins = new DeuxJardins;
    app(JardinCourant::class)->positionner($jardins->b->id);
    foreach (Plante::query()->get() as $plante) {
        IntervalleSaisonnier::factory()->for(Regle::factory()->r1()->for($plante))->create();
    }
    $zoneB = Zone::query()->firstOrFail();
    app()->forgetScopedInstances();
    $this->travelTo(new DateTimeImmutable('2027-05-10 08:00:00', new DateTimeZone('Europe/Paris')));

    foreach (['/', '/terrain'] as $chemin) {
        $corps = (string) $this->withoutVite()->actingAs($jardins->desDeux)
            ->withSession(['jardin_id' => $jardins->a->id])
            ->get($chemin)->assertOk()->getContent();
        expect($corps)->not->toContain(DeuxJardins::MARQUEUR);
        // Le jardin B lui-même est dans les props partagées, à bon droit : il adhère aux deux.
        foreach (array_slice($jardins->identifiantsB, 1) as $id) {
            expect($corps)->not->toContain($id);
        }
    }

    app()->forgetScopedInstances();
    $this->actingAs($jardins->desDeux)->withSession(['jardin_id' => $jardins->a->id])
        ->getJson('/echeances/groupe?'.http_build_query(['zone' => $zoneB->id, 'type' => RegleFactory::typeFourni('Tonte'), 'periode' => 'aujourdhui']))
        ->assertNotFound();
});

it('refuse un détail de groupe sans période connue', function () {
    $zone = Zone::query()->withoutGlobalScopes()->where('nom', 'Verger')->value('id');

    $this->actingAs($this->proprietaire)
        ->getJson('/echeances/groupe?'.http_build_query(['zone' => $zone, 'type' => 1, 'periode' => 'demain']))
        ->assertUnprocessable();
});
