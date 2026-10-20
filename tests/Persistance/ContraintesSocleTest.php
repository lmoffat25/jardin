<?php

// V24, V25 : la base refuse un jardin sans profil (RG-23), un rôle inconnu et une
// adhésion double (RG-20), un code court répété dans un jardin et un nom répété
// dans une zone (RG-13, S8) ; une règle de référence sans confiance (S9, RG-31).

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/** Une ligne de plante prête à insérer. */
function plante(string $jardin, string $zone, string $code, string $nom): array
{
    return ['id' => (string) Str::uuid(), 'jardin_id' => $jardin, 'zone_id' => $zone, 'code_court' => $code, 'nom' => $nom];
}

/** Une adhésion prête à insérer. */
function adhesion(string $utilisateur, string $jardin, string $role): array
{
    return ['id' => (string) Str::uuid(), 'utilisateur_id' => $utilisateur, 'jardin_id' => $jardin, 'role' => $role];
}

it('refuse un jardin sans profil climatique', function () {
    refusee(fn () => DB::table('jardins')->insert([
        'id' => (string) Str::uuid(),
        'proprietaire_id' => nouvelUtilisateur(),
        'nom' => 'Sans profil',
    ]));
});

it('donne à un jardin un quota de photos de 2 Go par défaut', function () {
    expect(DB::table('jardins')->where('id', nouveauJardin())->value('quota_photos_mo'))->toBe(2048);
});

it('refuse une adhésion dont le rôle n\'est ni proprietaire ni membre', function () {
    $jardin = nouveauJardin();

    refusee(fn () => DB::table('adhesions')->insert(adhesion(nouvelUtilisateur(), $jardin, 'invite')));
});

it('refuse une seconde adhésion du même utilisateur au même jardin', function () {
    $jardin = nouveauJardin();
    $utilisateur = nouvelUtilisateur();
    DB::table('adhesions')->insert(adhesion($utilisateur, $jardin, 'membre'));
    DB::table('adhesions')->insert(adhesion($utilisateur, nouveauJardin(), 'proprietaire'));

    refusee(fn () => DB::table('adhesions')->insert(adhesion($utilisateur, $jardin, 'proprietaire')));
});

it('accepte un même code court dans deux jardins, et le refuse deux fois dans un jardin', function () {
    $premier = nouveauJardin();
    $second = nouveauJardin();
    $zone = nouvelleZone($premier);
    DB::table('plantes')->insert(plante($premier, $zone, 'FRU-014', 'Pommier'));
    DB::table('plantes')->insert(plante($second, nouvelleZone($second), 'FRU-014', 'Poirier'));

    refusee(fn () => DB::table('plantes')->insert(plante($premier, nouvelleZone($premier, 'Verger'), 'FRU-014', 'Cerisier')));
});

it('accepte un même nom dans deux zones, et le refuse deux fois dans une zone', function () {
    $jardin = nouveauJardin();
    $potager = nouvelleZone($jardin, 'Potager');
    DB::table('plantes')->insert(plante($jardin, $potager, 'PLA-001', 'Rosier du portail'));
    DB::table('plantes')->insert(plante($jardin, nouvelleZone($jardin, 'Verger'), 'PLA-002', 'Rosier du portail'));

    refusee(fn () => DB::table('plantes')->insert(plante($jardin, $potager, 'PLA-003', 'Rosier du portail')));
});

it('refuse une règle de référence sans niveau de confiance', function () {
    $espece = DB::table('especes')->insertGetId(['nom_commun' => 'Espèce témoin', 'categorie' => 'fruitier']);
    $type = DB::table('types_intervention')->insertGetId(['libelle' => 'Geste témoin', 'famille' => 'taille']);
    $regle = ['espece_id' => $espece, 'type_intervention_id' => $type, 'type_regle' => 'R2', 'parametres' => '{}'];

    refusee(fn () => DB::table('regles_reference')->insert($regle));
    DB::table('regles_reference')->insert($regle + ['confiance' => 'indicative']);
    expect(DB::table('regles_reference')->count())->toBe(1);
});
