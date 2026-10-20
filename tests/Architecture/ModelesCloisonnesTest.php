<?php

// V34 : un modèle métier sans filtre par jardin ne passe pas (C7, RG-21, D-55).
// Toute classe de src/*/Domaine/ qui étend Model utilise AppartientAuJardin,
// sauf la liste référentiel ci-dessous, fermée.

use Illuminate\Database\Eloquent\Model;
use Src\Support\Jardin\AppartientAuJardin;

/**
 * Les modèles dispensés du trait, et pourquoi. Liste fermée ; une classe peut
 * ne pas exister encore.
 */
const MODELES_REFERENTIEL = [
    // Le compte et le jardin lui-même.
    'Src\Comptes\Domaine\Jardin',
    'Src\Comptes\Domaine\Adhesion',
    'Src\Comptes\Domaine\Utilisateur',
    // Référence, partagée par tous les jardins.
    'Src\Comptes\Domaine\ProfilClimatique',
    'Src\Comptes\Domaine\Commune', // 2f : référentiel importé (D-85)
    'Src\Bibliotheque\Domaine\FamilleBotanique',
    'Src\Bibliotheque\Domaine\Espece',
    'Src\Bibliotheque\Domaine\RegleReference',
    'Src\Bibliotheque\Domaine\SourceRegle',
    // Fournis (jardin_id NULL) et propres au jardin : filtre global à part (7c).
    'Src\Bibliotheque\Domaine\TypeIntervention',
];

/** @return list<class-string> les classes de src/<Module>/Domaine/, sous-dossiers compris */
function classesDuDomaine(): array
{
    $classes = [];
    foreach (glob(base_path('src/*/Domaine'), GLOB_ONLYDIR) ?: [] as $dossier) {
        $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS));
        foreach ($fichiers as $fichier) {
            if ($fichier->getExtension() !== 'php') {
                continue;
            }
            $relatif = substr($fichier->getPathname(), strlen(base_path('src/')), -4);
            $classe = 'Src\\'.str_replace('/', '\\', $relatif);
            if (class_exists($classe)) {
                $classes[] = $classe;
            }
        }
    }
    sort($classes);

    return $classes;
}

/**
 * @param  list<string>  $classes
 * @return list<string> les modèles hors liste qui n'utilisent pas le trait
 */
function modelesNonCloisonnes(array $classes): array
{
    return array_values(array_filter($classes, fn (string $classe) => is_subclass_of($classe, Model::class)
        && ! in_array($classe, MODELES_REFERENTIEL, true)
        && ! in_array(AppartientAuJardin::class, class_uses_recursive($classe), true)));
}

it('trouve les modèles du domaine', function () {
    expect(classesDuDomaine())->toContain('Src\Jardin\Domaine\Zone', 'Src\Jardin\Domaine\Plante');
});

it('ne trouve aucun modèle métier sans le filtre par jardin', function () {
    expect(modelesNonCloisonnes(classesDuDomaine()))->toBe([]);
});

it('signale un modèle témoin sans le trait, et pas un modèle de la liste', function () {
    $temoin = get_class(new class extends Model {});
    $cloisonne = get_class(new class extends Model
    {
        use AppartientAuJardin;
    });

    expect(modelesNonCloisonnes([$temoin, $cloisonne, 'Src\Comptes\Domaine\Jardin']))->toBe([$temoin]);
});
