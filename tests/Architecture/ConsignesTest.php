<?php

// V17 : un agent trouve la consigne dans le dépôt (chapitre 6 §10, CDC §8.8).

use Symfony\Component\Yaml\Yaml;

it('a le modèle de demande de fusion à six titres', function () {
    preg_match_all('/^## (.+)$/m', (string) file_get_contents(base_path('.github/pull_request_template.md')), $titres);

    expect($titres[1])->toBe([
        'Ce que ça change',
        'Critères du CDC concernés',
        'Tests ajoutés',
        'Attendus de tests modifiés',
        'Décisions prises, et ce qui a été écarté',
        'Vérification locale',
    ]);
});

it('met à jour les trois écosystèmes chaque semaine, groupés', function () {
    $mises = Yaml::parseFile(base_path('.github/dependabot.yml'))['updates'];

    expect(array_column($mises, 'package-ecosystem'))->toEqualCanonicalizing(['composer', 'npm', 'github-actions']);
    foreach ($mises as $mise) {
        expect($mise['schedule']['interval'])->toBe('weekly')
            ->and($mise['groups'] ?? [])->not->toBeEmpty();
    }
});

it('a ses fichiers de règles', function (string $fichier) {
    expect(base_path(".claude/rules/{$fichier}"))->toBeFile();
})->with(['git.md', 'tests.md', 'documentation.md']);

it('donne dans AGENTS.md les neuf règles, les commandes et l\'arrêt', function () {
    $agents = (string) file_get_contents(base_path('AGENTS.md'));

    preg_match('/^## Les règles de travail\n(.*?)^## /ms', $agents, $section);
    preg_match_all('/^(\d+)\. /m', $section[1] ?? '', $regles);
    expect($regles[1])->toBe(array_map('strval', range(1, 9)));

    foreach (['setup', 'moteur', 'statique', 'test', 'balayage', 'e2e', 'mutation', 'criteres', 'verifier'] as $cible) {
        expect($agents)->toContain("`make {$cible}`");
    }
    expect($agents)->toContain('## Quand s\'arrêter')->toContain('production');
});
