<?php

// V64 : la structure de la bibliothèque se teste comme du code (B-T4 à B-T9,
// B-T11, B2, B3, chap. 6 §6.7, D-52). Les fichiers du dépôt passent ; chaque
// contrôle échoue sur une donnée fautive témoin, qui nomme le fichier et le
// chemin de la clé en cause. B-T1 à B-T3 et B-T12 à B-T14 portent sur des
// valeurs agronomiques et arrivent avec elles, au 4c.

use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Bibliotheque\Chargement\LireBibliotheque;
use Src\Bibliotheque\Domaine\TypeIntervention;
use Tests\Support\TemoinBibliotheque;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceSeeder::class);
});

/**
 * Lit le dossier avec les types fournis du jeu de référence.
 *
 * @return list<array<string, mixed>>
 */
function lireBibliotheque(string $dossier): array
{
    /** @var array<string, string> $types */
    $types = TypeIntervention::query()->withoutGlobalScopes()->whereNull('jardin_id')->pluck('famille', 'libelle')->all();

    return (new LireBibliotheque)->lire($dossier, $types);
}

/**
 * Le fruitier témoin, faussé par la fonction.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $fausser
 * @return list<array<string, mixed>>
 */
function fruitierFausse(Closure $fausser): array
{
    return [$fausser(TemoinBibliotheque::fruitier())];
}

it('lit les fichiers du dépôt sans erreur', function () {
    expect(lireBibliotheque(base_path('bibliotheque')))->toBeArray();
});

it('lit le témoin : deux espèces, une règle chacune', function () {
    $especes = lireBibliotheque(TemoinBibliotheque::complet());

    expect(array_column($especes, 'cle'))->toBe(['fruitier-temoin', 'pelouse-temoin'])
        ->and($especes[0]['fichier'])->toBe('fruitiers.yaml');
});

it('accepte une fenêtre de haie d\'un butoir à l\'autre, sept mois (B-T4)', function () {
    $haie = TemoinBibliotheque::fruitier();
    $haie['categorie'] = 'haie';
    $haie['regles'][0]['type_intervention'] = 'Taille de haie';
    $haie['regles'][0]['fenetres'][0] = [
        'debut' => '16/08', 'fin' => '15/03', 'optimal' => ['01/10', '15/11'], 'butoirs' => ['16/08', '15/03'],
    ];

    expect(lireBibliotheque(TemoinBibliotheque::dossier(['haies' => [$haie]])))->toHaveCount(1);
});

it('accepte une fenêtre qui passe le 1er janvier, son optimale aussi (B-T6)', function () {
    $especes = fruitierFausse(function (array $e) {
        $e['regles'][0]['fenetres'][0] = ['debut' => '15/11', 'fin' => '15/02', 'optimal' => ['20/12', '10/01']];

        return $e;
    });

    expect(lireBibliotheque(TemoinBibliotheque::dossier(['fruitiers' => $especes])))->toHaveCount(1);
});

it('refuse une donnée fautive, en nommant fichier et clé', function (Closure $fausser, string $chemin, string $raison) {
    $dossier = TemoinBibliotheque::dossier(['fruitiers' => fruitierFausse($fausser), 'pelouses' => [TemoinBibliotheque::pelouse()]], ['marchand.exemple.test']);

    expect(fn () => lireBibliotheque($dossier))
        ->toThrow(RuntimeException::class, "fruitiers.yaml : {$chemin} : {$raison}");
})->with([
    // B-T4 : une fenêtre dure de 14 jours à 5 mois, 9 mois avec un butoir.
    'B-T4 fenêtre de 10 jours' => [function (array $e) {
        $e['regles'][0]['fenetres'][0] = ['debut' => '01/02', 'fin' => '10/02'];

        return $e;
    }, 'especes[0].regles[0].fenetres[0]', 'dure 10 jours, moins de 14 (B-T4)'],
    'B-T4 fenêtre de 6 mois' => [function (array $e) {
        $e['regles'][0]['fenetres'][0] = ['debut' => '01/01', 'fin' => '30/06'];

        return $e;
    }, 'especes[0].regles[0].fenetres[0]', 'dure plus de 5 mois (B-T4)'],
    'B-T4 fenêtre à butoir de 10 mois' => [function (array $e) {
        $e['regles'][0]['fenetres'][0] = ['debut' => '01/03', 'fin' => '15/01', 'butoirs' => ['01/03', '15/01']];

        return $e;
    }, 'especes[0].regles[0].fenetres[0]', 'dure plus de 9 mois (B-T4)'],
    // B-T5 : un intervalle non nul est entre 5 et 21 jours.
    'B-T5 intervalle de 90 jours' => [function (array $e) {
        $e['categorie'] = 'gazon';
        $e['regles'][0] = TemoinBibliotheque::pelouse()['regles'][0];
        $e['regles'][0]['intervalles'][0]['jours'] = 90;

        return $e;
    }, 'especes[0].regles[0].intervalles[0].jours', 'hors de 5 à 21 jours (B-T5)'],
    'B-T5 intervalle de 3 jours' => [function (array $e) {
        $e['categorie'] = 'gazon';
        $e['regles'][0] = TemoinBibliotheque::pelouse()['regles'][0];
        $e['regles'][0]['intervalles'][1]['jours'] = 3;

        return $e;
    }, 'especes[0].regles[0].intervalles[1].jours', 'hors de 5 à 21 jours (B-T5)'],
    'R1 toujours hors saison' => [function (array $e) {
        $e['categorie'] = 'gazon';
        $e['regles'][0] = TemoinBibliotheque::pelouse()['regles'][0];
        $e['regles'][0]['intervalles'][0]['jours'] = 0;

        return $e;
    }, 'especes[0].regles[0].intervalles', 'aucune saison à intervalle non nul'],
    'R1 deux saisons qui partagent un mois' => [function (array $e) {
        $e['categorie'] = 'gazon';
        $e['regles'][0] = TemoinBibliotheque::pelouse()['regles'][0];
        $e['regles'][0]['intervalles'][1]['mois_debut'] = 9;

        return $e;
    }, 'especes[0].regles[0].intervalles[1]', 'le mois 9 est déjà dans une autre saison'],
    // B-T6 : la période optimale est incluse dans sa fenêtre.
    'B-T6 optimale qui déborde' => [function (array $e) {
        $e['regles'][0]['fenetres'][0]['optimal'] = ['15/03', '15/04'];

        return $e;
    }, 'especes[0].regles[0].fenetres[0].optimal', 'hors de sa fenêtre (B-T6)'],
    // B-T7 : pas deux règles d'un même type pour une même variante (RG-51).
    'B-T7 deux règles du même type' => [function (array $e) {
        $e['regles'][1] = $e['regles'][0];
        $e['regles'][1]['cle'] = 'fruitier-temoin-taille-bis';

        return $e;
    }, 'especes[0].regles[1].type_intervention', 'déjà donné par la règle fruitier-temoin-taille (B-T7)'],
    'B-T7 variante qui double une règle commune' => [function (array $e) {
        $e['regles'][1] = $e['regles'][0];
        $e['regles'][1]['cle'] = 'fruitier-temoin-taille-variante';
        $e['regles'][1]['variante'] = 'palisse';

        return $e;
    }, 'especes[0].regles[1].type_intervention', 'déjà donné par la règle fruitier-temoin-taille (B-T7)'],
    // B-T8, B2 : une source au moins, deux pour une règle vérifiée.
    'B2 vérifiée à une source' => [function (array $e) {
        array_pop($e['regles'][0]['sources']);

        return $e;
    }, 'especes[0].regles[0].sources', 'règle verifiee à moins de deux sources (B2)'],
    'B2 règle sans source' => [function (array $e) {
        $e['regles'][0]['confiance'] = 'indicative';
        $e['regles'][0]['sources'] = [];

        return $e;
    }, 'especes[0].regles[0].sources', 'règle sans source (B1)'],
    'B2 source sans URL' => [function (array $e) {
        unset($e['regles'][0]['sources'][1]['url']);

        return $e;
    }, 'especes[0].regles[0].sources[1].url', 'absente'],
    'B2 source sans libellé' => [function (array $e) {
        unset($e['regles'][0]['sources'][0]['libelle']);

        return $e;
    }, 'especes[0].regles[0].sources[0].libelle', 'absente'],
    'B2 source sans date de consultation' => [function (array $e) {
        unset($e['regles'][0]['sources'][0]['consultee_le']);

        return $e;
    }, 'especes[0].regles[0].sources[0].consultee_le', 'absente'],
    // B-T9 : une confiance explicite.
    'B-T9 sans confiance' => [function (array $e) {
        unset($e['regles'][0]['confiance']);

        return $e;
    }, 'especes[0].regles[0].confiance', 'absente'],
    'B-T9 confiance inconnue' => [function (array $e) {
        $e['regles'][0]['confiance'] = 'probable';

        return $e;
    }, 'especes[0].regles[0].confiance', 'ni verifiee ni indicative (B-T9)'],
    // B-T11 : un type d'intervention applicable à l'espèce.
    'B-T11 tonte d\'un fruitier' => [function (array $e) {
        $e['regles'][0]['type_intervention'] = 'Tonte';

        return $e;
    }, 'especes[0].regles[0].type_intervention', 'Tonte ne s\'applique pas à la catégorie fruitier (B-T11)'],
    'B-T11 taille d\'une pelouse' => [function (array $e) {
        $e['categorie'] = 'gazon';

        return $e;
    }, 'especes[0].regles[0].type_intervention', 'Taille d\'entretien/fructification ne s\'applique pas à la catégorie gazon (B-T11)'],
    // B3 : aucun domaine de sources-exclues.yaml, sous-domaines compris.
    'B3 domaine exclu' => [function (array $e) {
        $e['regles'][0]['sources'][1]['url'] = 'https://boutique.marchand.exemple.test/taille';

        return $e;
    }, 'especes[0].regles[0].sources[1].url', 'domaine exclu marchand.exemple.test (B3)'],
    // Structure : dates réelles, clés connues.
    'date irréelle' => [function (array $e) {
        $e['regles'][0]['fenetres'][0]['debut'] = '30/02';

        return $e;
    }, 'especes[0].regles[0].fenetres[0].debut', 'date JJ/MM irréelle'],
    'clé inconnue' => [function (array $e) {
        $e['regles'][0]['fenetre'] = $e['regles'][0]['fenetres'];

        return $e;
    }, 'especes[0].regles[0].fenetre', 'clé inconnue'],
]);

it('refuse une même clé d\'espèce dans deux fichiers', function () {
    $dossier = TemoinBibliotheque::dossier(['a' => [TemoinBibliotheque::pelouse()], 'b' => [TemoinBibliotheque::pelouse()]]);

    expect(fn () => lireBibliotheque($dossier))
        ->toThrow(RuntimeException::class, 'b.yaml : especes[0].cle : pelouse-temoin déjà donnée dans a.yaml');
});
