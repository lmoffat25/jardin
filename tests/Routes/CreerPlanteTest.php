<?php

// V83 (RG-39, RG-09, RG-51, P4, écrans B7 et T3) : nom, zone et espèce créent
// la plante, son code court, et une copie de chaque règle de référence en
// vigueur de l'espèce (fenêtres, butoirs, intervalles, confiance, lien
// `regle_reference_id`), sauf celles que la demande écarte ; sans espèce, la
// plante n'a pas de règle. Zone absente, archivée ou de B, nom déjà pris dans
// la zone, espèce inconnue : erreur, rien n'est créé, ni dans A ni dans B.
//
// V84 (P4, RG-09) : une valeur ajustée dans la demande (périodicité, bornes de
// fenêtre, intervalle) est celle de la règle copiée, qui garde son lien vers la
// référence et la version 1.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\Domaine\RegleReference;
use Src\Comptes\Domaine\Utilisateur;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;
use Tests\Support\DeuxJardins;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

/**
 * Un verger témoin : une taille à deux fenêtres bornées par des butoirs, une
 * récolte. Valeurs inventées (RG-28).
 *
 * @return array<string, mixed>
 */
function vergerTemoin(string $debutHiver = '01/12', bool $avecRecolte = true): array
{
    $source = [
        ['url' => 'https://institut.exemple.test/verger', 'libelle' => 'Institut témoin', 'consultee_le' => '2026-10-20'],
        ['url' => 'https://conservatoire.exemple.test/verger', 'libelle' => 'Conservatoire témoin', 'consultee_le' => '2026-10-20'],
    ];

    $verger = [
        'cle' => 'verger-temoin',
        'nom_commun' => 'Verger témoin',
        'famille' => 'Famille témoin',
        'categorie' => 'fruitier',
        'regles' => [
            [
                'cle' => 'verger-temoin-taille', 'type_intervention' => 'Taille d\'entretien/fructification', 'type_regle' => 'R2',
                'confiance' => 'verifiee', 'sources' => $source, 'periodicite_annees' => 1,
                'fenetres' => [
                    ['debut' => $debutHiver, 'fin' => '28/02', 'optimal' => ['15/12', '31/01'], 'butoirs' => ['20/11', '10/03'], 'libelle' => 'Hiver'],
                    ['debut' => '01/07', 'fin' => '15/08', 'butoirs' => [null, '31/08'], 'libelle' => 'En vert'],
                ],
            ],
            [
                'cle' => 'verger-temoin-recolte', 'type_intervention' => 'Récolte', 'type_regle' => 'R2',
                'confiance' => 'indicative', 'sources' => $source, 'periodicite_annees' => 1,
                'fenetres' => [['debut' => '01/09', 'fin' => '15/10']],
            ],
        ],
    ];
    if (! $avecRecolte) {
        array_pop($verger['regles']);
    }

    return $verger;
}

function chargerBibliotheque(string $debutHiver = '01/12', bool $avecRecolte = true): void
{
    test()->artisan('bibliotheque:charger', ['dossier' => TemoinBibliotheque::dossier([
        'fruitiers' => [vergerTemoin($debutHiver, $avecRecolte)],
        'pelouses' => [TemoinBibliotheque::pelouse()],
        'petits-fruits' => [TemoinBibliotheque::framboisier()],
    ])])->assertSuccessful();
}

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
    chargerBibliotheque();
    $this->jardins = new DeuxJardins;
    $this->zone = DB::table('zones')->where('nom', 'A Verger')->value('id');
});

/** Le propriétaire de A, dans A, comme d'une requête neuve. */
function proprietaireDansA(): Utilisateur
{
    app()->forgetScopedInstances();
    test()->withSession(['jardin_id' => test()->jardins->a->id]);

    return test()->jardins->proprietaireA;
}

function idEspece(string $cle): int
{
    return Espece::query()->where('cle', $cle)->value('id');
}

function referenceEnVigueur(string $cle): RegleReference
{
    return RegleReference::query()->where('cle', $cle)->whereNull('remplacee_le')->sole();
}

/** @return list<array<string, mixed>> les fenêtres d'une règle, par ordre, sans identifiants */
function fenetresDe(string $regleId): array
{
    return DB::table('fenetres')->where('regle_id', $regleId)->orderBy('ordre')->get()
        ->map(fn ($f) => array_diff_key((array) $f, array_flip(['id', 'regle_id', 'jardin_id'])))->all();
}

/** @return array<string, int> le nombre de lignes de chaque table de l'inventaire */
function comptes(): array
{
    return collect(['plantes', 'regles', 'fenetres', 'intervalles_saisonniers', 'compteurs_codes_courts'])
        ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
}

it('crée la plante, son code, et une copie des règles en vigueur de son espèce', function () {
    $reponse = $this->actingAs(proprietaireDansA())->post('/plantes', [
        'nom' => 'Pommier du fond', 'zone_id' => $this->zone, 'espece_id' => idEspece('verger-temoin'),
        'repere' => 'Près du puits', 'variete' => 'Reinette témoin', 'plantee_le' => '2019-11-15',
    ])->assertSessionHasNoErrors();

    $plante = DB::table('plantes')->where('nom', 'Pommier du fond')->sole();
    $reponse->assertRedirect("/plantes/{$plante->id}");
    expect([$plante->jardin_id, $plante->zone_id, $plante->code_court, $plante->repere, $plante->variete, $plante->plantee_le])
        ->toBe([$this->jardins->a->id, $this->zone, 'FRU-001', 'Près du puits', 'Reinette témoin', '2019-11-15']);

    $regles = DB::table('regles')->where('plante_id', $plante->id)->get()->keyBy('regle_reference_id');
    expect($regles)->toHaveCount(2);
    $taille = $regles[referenceEnVigueur('verger-temoin-taille')->id];
    $recolte = $regles[referenceEnVigueur('verger-temoin-recolte')->id];
    expect([$taille->jardin_id, $taille->type_regle, $taille->periodicite_annees, $taille->intervalle_jours, $taille->confiance, $taille->origine, $taille->est_active, $taille->version, $taille->remplacee_le])
        ->toBe([$this->jardins->a->id, 'R2', 1, null, 'verifiee', 'bibliotheque', true, 1, null])
        ->and($taille->type_intervention_id)->toBe(referenceEnVigueur('verger-temoin-taille')->type_intervention_id)
        ->and([$recolte->confiance, $recolte->origine, $recolte->version])->toBe(['indicative', 'bibliotheque', 1]);

    expect(fenetresDe($taille->id))->toBe([
        [
            'debut_jour' => 1, 'debut_mois' => 12, 'fin_jour' => 28, 'fin_mois' => 2,
            'optimal_debut_jour' => 15, 'optimal_debut_mois' => 12, 'optimal_fin_jour' => 31, 'optimal_fin_mois' => 1,
            'butoir_debut_jour' => 20, 'butoir_debut_mois' => 11, 'butoir_fin_jour' => 10, 'butoir_fin_mois' => 3,
            'libelle' => 'Hiver', 'ordre' => 1,
        ],
        [
            'debut_jour' => 1, 'debut_mois' => 7, 'fin_jour' => 15, 'fin_mois' => 8,
            'optimal_debut_jour' => null, 'optimal_debut_mois' => null, 'optimal_fin_jour' => null, 'optimal_fin_mois' => null,
            'butoir_debut_jour' => null, 'butoir_debut_mois' => null, 'butoir_fin_jour' => 31, 'butoir_fin_mois' => 8,
            'libelle' => 'En vert', 'ordre' => 2,
        ],
    ]);
    expect(DB::table('fenetres')->pluck('jardin_id')->unique()->all())->toBe([$this->jardins->a->id]);
});

it('copie les intervalles saisonniers d\'une règle R1', function () {
    $this->actingAs(proprietaireDansA())->post('/plantes', ['nom' => 'Pelouse ouest', 'zone_id' => $this->zone, 'espece_id' => idEspece('pelouse-temoin')])
        ->assertSessionHasNoErrors();

    $regle = DB::table('regles')->sole();
    // Le plus court intervalle non nul de ses saisons (D-101).
    expect([$regle->type_regle, $regle->intervalle_jours, $regle->periodicite_annees, $regle->confiance, $regle->regle_reference_id])
        ->toBe(['R1', 7, null, 'indicative', referenceEnVigueur('pelouse-temoin-tonte')->id])
        ->and(DB::table('plantes')->where('nom', 'Pelouse ouest')->value('code_court'))->toBe('GAZ-001');
    expect(DB::table('intervalles_saisonniers')->where('regle_id', $regle->id)->orderBy('mois_debut')->get(['jardin_id', 'mois_debut', 'mois_fin', 'intervalle_jours'])->map(fn ($i) => (array) $i)->all())
        ->toBe([
            ['jardin_id' => $this->jardins->a->id, 'mois_debut' => 4, 'mois_fin' => 9, 'intervalle_jours' => 7],
            ['jardin_id' => $this->jardins->a->id, 'mois_debut' => 10, 'mois_fin' => 3, 'intervalle_jours' => 0],
        ]);
});

it('ne copie que la version en vigueur d\'une règle de référence, rien d\'une règle retirée', function () {
    chargerBibliotheque('05/12', avecRecolte: false);
    expect(DB::table('regles_reference')->where('cle', 'verger-temoin-taille')->count())->toBe(2)
        ->and(DB::table('regles_reference')->where('cle', 'verger-temoin-recolte')->whereNull('remplacee_le')->exists())->toBeFalse();

    $this->actingAs(proprietaireDansA())->post('/plantes', ['nom' => 'Poirier', 'zone_id' => $this->zone, 'espece_id' => idEspece('verger-temoin')])
        ->assertSessionHasNoErrors();

    $reference = referenceEnVigueur('verger-temoin-taille');
    expect($reference->version)->toBe(2);
    $taille = DB::table('regles')->where('regle_reference_id', $reference->id)->sole();
    expect(fenetresDe($taille->id)[0]['debut_jour'])->toBe(5)
        ->and(DB::table('regles')->count())->toBe(1);
});

it('n\'écrit pas les règles que la demande écarte', function () {
    $this->actingAs(proprietaireDansA())->post('/plantes', [
        'nom' => 'Pommier', 'zone_id' => $this->zone, 'espece_id' => idEspece('verger-temoin'),
        'regles_ecartees' => ['verger-temoin-recolte'],
    ])->assertSessionHasNoErrors();

    expect(DB::table('regles')->pluck('regle_reference_id')->all())->toBe([referenceEnVigueur('verger-temoin-taille')->id]);
});

it('crée une plante sans espèce, sans règle', function () {
    $this->actingAs(proprietaireDansA())->post('/plantes', ['nom' => 'Inconnue du talus', 'zone_id' => $this->zone])
        ->assertSessionHasNoErrors();

    expect(DB::table('plantes')->where('nom', 'Inconnue du talus')->value('code_court'))->toBe('PLA-001')
        ->and(DB::table('regles')->count())->toBe(0);
});

it('rend l\'identifiant et le code en JSON', function () {
    $reponse = $this->actingAs(proprietaireDansA())->postJson('/plantes', ['nom' => 'Pommier', 'zone_id' => $this->zone, 'espece_id' => idEspece('verger-temoin')])
        ->assertCreated();

    expect($reponse->json())->toBe(['id' => DB::table('plantes')->where('nom', 'Pommier')->value('id'), 'code_court' => 'FRU-001']);
});

it('copie les règles communes et celles de la variante choisie', function () {
    $this->actingAs(proprietaireDansA())->postJson('/plantes', [
        'nom' => 'Framboisiers du mur', 'zone_id' => $this->zone, 'espece_id' => idEspece('framboisier-temoin'), 'variante' => 'remontant',
    ])->assertCreated();

    expect(DB::table('plantes')->where('nom', 'Framboisiers du mur')->value('variante'))->toBe('remontant')
        ->and(DB::table('regles')->pluck('regle_reference_id')->sort()->values()->all())
        ->toBe([referenceEnVigueur('framboisier-temoin-recolte')->id, referenceEnVigueur('framboisier-temoin-taille-remontant')->id]);
});

it('refuse une saisie fautive, et ne crée rien', function (Closure $saisie, string $champ) {
    app(JardinCourant::class)->positionner($this->jardins->a->id);
    $archivee = Zone::factory()->create(['nom' => 'Ancien verger', 'archivee_le' => now()])->id;
    $avant = comptes();
    $valide = ['nom' => 'Pommier', 'zone_id' => $this->zone, 'espece_id' => idEspece('verger-temoin')];

    $this->actingAs(proprietaireDansA())->postJson('/plantes', $saisie($valide, $archivee))
        ->assertStatus(422)
        ->assertJsonValidationErrors($champ);

    expect(comptes())->toBe($avant);
})->with([
    'zone absente' => [fn (array $v) => array_diff_key($v, ['zone_id' => 1]), 'zone_id'],
    'zone qui n\'est pas un identifiant' => [fn (array $v) => [...$v, 'zone_id' => 'verger'], 'zone_id'],
    'zone archivée' => [fn (array $v, string $archivee) => [...$v, 'zone_id' => $archivee], 'zone_id'],
    'nom vide' => [fn (array $v) => [...$v, 'nom' => ''], 'nom'],
    'nom déjà pris dans la zone' => [fn (array $v) => [...$v, 'nom' => 'A Plante Verger 1'], 'nom'],
    'espèce inconnue' => [fn (array $v) => [...$v, 'espece_id' => 999999], 'espece_id'],
    'variante sans variantes' => [fn (array $v) => [...$v, 'variante' => 'remontant'], 'variante'],
    'variante manquante' => [fn (array $v) => [...$v, 'espece_id' => idEspece('framboisier-temoin')], 'variante'],
    'variante inconnue' => [fn (array $v) => [...$v, 'espece_id' => idEspece('framboisier-temoin'), 'variante' => 'grimpant'], 'variante'],
    'règle écartée inconnue' => [fn (array $v) => [...$v, 'regles_ecartees' => ['pelouse-temoin-tonte']], 'regles_ecartees'],
    'règle écartée sans espèce' => [fn (array $v) => [...array_diff_key($v, ['espece_id' => 1]), 'regles_ecartees' => ['verger-temoin-recolte']], 'regles_ecartees'],
    'ajustement d\'une règle inconnue' => [fn (array $v) => [...$v, 'ajustements' => ['pelouse-temoin-tonte' => ['intervalle_jours' => 5]]], 'ajustements'],
    'ajustement d\'une règle écartée' => [fn (array $v) => [...$v, 'regles_ecartees' => ['verger-temoin-recolte'], 'ajustements' => ['verger-temoin-recolte' => ['periodicite_annees' => 2]]], 'ajustements'],
    'périodicité nulle' => [fn (array $v) => [...$v, 'ajustements' => ['verger-temoin-taille' => ['periodicite_annees' => 0]]], 'ajustements.verger-temoin-taille.periodicite_annees'],
    'intervalle sur une R2' => [fn (array $v) => [...$v, 'ajustements' => ['verger-temoin-taille' => ['intervalle_jours' => 10]]], 'ajustements.verger-temoin-taille'],
    'borne mal formée' => [fn (array $v) => [...$v, 'ajustements' => ['verger-temoin-taille' => ['fenetres' => [1 => ['debut' => '31/02']]]]], 'ajustements.verger-temoin-taille.fenetres.1.debut'],
    'saisons qui se chevauchent' => [fn (array $v) => [...$v, 'espece_id' => idEspece('pelouse-temoin'), 'ajustements' => ['pelouse-temoin-tonte' => ['intervalles' => [
        ['mois_debut' => 3, 'mois_fin' => 10, 'jours' => 10], ['mois_debut' => 10, 'mois_fin' => 2, 'jours' => 0],
    ]]]], 'ajustements.pelouse-temoin-tonte.intervalles'],
    'saisons sans intervalle' => [fn (array $v) => [...$v, 'espece_id' => idEspece('pelouse-temoin'), 'ajustements' => ['pelouse-temoin-tonte' => ['intervalles' => [
        ['mois_debut' => 1, 'mois_fin' => 12, 'jours' => 0],
    ]]]], 'ajustements.pelouse-temoin-tonte.intervalles'],
    'fenêtre inconnue' => [fn (array $v) => [...$v, 'ajustements' => ['verger-temoin-taille' => ['fenetres' => [3 => ['debut' => '01/01']]]]], 'ajustements.verger-temoin-taille.fenetres'],
]);

it('refuse une zone de B, et ne crée rien ni dans A ni dans B', function () {
    $zoneB = DB::table('zones')->where('jardin_id', $this->jardins->b->id)->value('id');
    $avantA = DeuxJardins::empreinte($this->jardins->a->id);
    $avantB = DeuxJardins::empreinte($this->jardins->b->id);

    $this->actingAs(proprietaireDansA())->postJson('/plantes', ['nom' => 'Pommier', 'zone_id' => $zoneB, 'espece_id' => idEspece('verger-temoin')])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['zone_id' => 'Zone introuvable dans ce jardin.']);

    expect(DeuxJardins::empreinte($this->jardins->a->id))->toBe($avantA)
        ->and(DeuxJardins::empreinte($this->jardins->b->id))->toBe($avantB);
});

it('accepte dans une zone un nom pris dans une autre', function () {
    $this->actingAs(proprietaireDansA())->postJson('/plantes', ['nom' => 'A Plante Potager 1', 'zone_id' => $this->zone])->assertCreated();
});

it('écrit les valeurs ajustées dans la règle copiée, qui garde son lien et la version 1', function () {
    $this->actingAs(proprietaireDansA())->postJson('/plantes', [
        'nom' => 'Pommier', 'zone_id' => $this->zone, 'espece_id' => idEspece('verger-temoin'),
        'ajustements' => ['verger-temoin-taille' => ['periodicite_annees' => 2, 'fenetres' => [1 => ['debut' => '10/12', 'fin' => '15/02']]]],
    ])->assertCreated();

    $taille = DB::table('regles')->where('regle_reference_id', referenceEnVigueur('verger-temoin-taille')->id)->sole();
    expect([$taille->periodicite_annees, $taille->origine, $taille->version, $taille->est_active])->toBe([2, 'ajustee', 1, true]);
    [$hiver, $enVert] = fenetresDe($taille->id);
    // Bornes déplacées : l'optimale de la référence ne vaut plus, les butoirs restent (D-101).
    expect(array_intersect_key($hiver, array_flip(['debut_jour', 'debut_mois', 'fin_jour', 'fin_mois', 'optimal_debut_jour', 'optimal_fin_jour', 'butoir_debut_jour', 'butoir_fin_jour', 'libelle'])))
        ->toBe(['debut_jour' => 10, 'debut_mois' => 12, 'fin_jour' => 15, 'fin_mois' => 2, 'optimal_debut_jour' => null, 'optimal_fin_jour' => null, 'butoir_debut_jour' => 20, 'butoir_fin_jour' => 10, 'libelle' => 'Hiver'])
        ->and([$enVert['debut_jour'], $enVert['debut_mois'], $enVert['fin_jour'], $enVert['fin_mois']])->toBe([1, 7, 15, 8]);

    $recolte = DB::table('regles')->where('regle_reference_id', referenceEnVigueur('verger-temoin-recolte')->id)->sole();
    expect([$recolte->periodicite_annees, $recolte->origine])->toBe([1, 'bibliotheque']);
});

it('écrit l\'intervalle et les saisons ajustés d\'une règle R1', function () {
    $this->actingAs(proprietaireDansA())->postJson('/plantes', [
        'nom' => 'Pelouse', 'zone_id' => $this->zone, 'espece_id' => idEspece('pelouse-temoin'),
        'ajustements' => ['pelouse-temoin-tonte' => ['intervalle_jours' => 10, 'intervalles' => [
            ['mois_debut' => 3, 'mois_fin' => 10, 'jours' => 10],
            ['mois_debut' => 11, 'mois_fin' => 2, 'jours' => 0],
        ]]],
    ])->assertCreated();

    $regle = DB::table('regles')->sole();
    expect([$regle->intervalle_jours, $regle->origine, $regle->version, $regle->regle_reference_id])
        ->toBe([10, 'ajustee', 1, referenceEnVigueur('pelouse-temoin-tonte')->id])
        ->and(DB::table('intervalles_saisonniers')->orderBy('mois_debut')->get(['mois_debut', 'mois_fin', 'intervalle_jours'])->map(fn ($i) => array_values((array) $i))->all())
        ->toBe([[3, 10, 10], [11, 2, 0]]);
});
