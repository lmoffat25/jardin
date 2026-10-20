<?php

// V11 : les frontières du chapitre 3 §9 sont des tests (A2, C7). Le moteur ne
// connaît ni le framework ni l'horloge ; aucun code de src/ n'écrit de SQL par
// la façade DB.

use Symfony\Component\Process\Process;

const INTERDITS_MOTEUR = ['Illuminate', 'Carbon', 'now', 'time', 'date'];
const REQUETES_BRUTES = ['table', 'select', 'statement', 'insert', 'update', 'delete'];

arch('le moteur n\'utilise ni le framework, ni Carbon, ni l\'horloge')
    ->expect('Src\Echeances\Moteur')
    ->not->toUse(INTERDITS_MOTEUR);

/** @return list<string> appels DB::<requête brute> trouvés, « fichier:ligne DB::méthode » */
function requetesBrutes(string $racine): array
{
    $trouves = [];
    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS));
    foreach ($fichiers as $fichier) {
        if ($fichier->getExtension() !== 'php') {
            continue;
        }
        $jetons = array_values(array_filter(
            token_get_all(file_get_contents($fichier->getPathname())),
            fn ($j) => ! is_array($j) || ! in_array($j[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        foreach ($jetons as $i => $jeton) {
            $suivant = $jetons[$i + 1] ?? null;
            $methode = $jetons[$i + 2] ?? null;
            if (is_array($jeton) && in_array($jeton[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)
                && preg_match('/(^|\\\\)DB$/', $jeton[1])
                && is_array($suivant) && $suivant[0] === T_DOUBLE_COLON
                && is_array($methode) && in_array(strtolower($methode[1]), REQUETES_BRUTES, true)) {
                $trouves[] = $fichier->getPathname().':'.$jeton[2].' DB::'.$methode[1];
            }
        }
    }

    return $trouves;
}

it('ne trouve aucune requête brute par la façade DB dans src/', function () {
    expect(requetesBrutes(base_path('src')))->toBe([]);
});

it('détecte une requête brute dans un fichier témoin', function () {
    $racine = sys_get_temp_dir().'/frontieres-'.bin2hex(random_bytes(4));
    mkdir($racine);
    file_put_contents($racine.'/Temoin.php', "<?php\nuse Illuminate\\Support\\Facades\\DB;\nDB::select('select 1');\n\\DB::table('x');\n");

    $trouves = requetesBrutes($racine);
    array_map('unlink', glob($racine.'/*'));
    rmdir($racine);

    expect($trouves)->toHaveCount(2);
});

it('fait échouer la suite quand un fichier témoin interdit entre dans le moteur', function (string $contenu) {
    $dossier = base_path('src/Echeances/Moteur');
    $creeDossier = ! is_dir($dossier);
    $creeDossier && mkdir($dossier, 0777, true);
    $nom = 'TemoinInterdit'.bin2hex(random_bytes(3));
    file_put_contents("$dossier/$nom.php", sprintf($contenu, $nom));

    try {
        $pest = new Process(['vendor/bin/pest', '--filter=le moteur n', 'tests/Architecture/FrontieresTest.php'], base_path());
        $pest->run();
    } finally {
        unlink("$dossier/$nom.php");
        $creeDossier && rmdir($dossier) && @rmdir(dirname($dossier));
    }

    expect($pest->getExitCode())->not->toBe(0, 'fichier témoin interdit non détecté');
})->with([
    'Illuminate' => ["<?php\nnamespace Src\\Echeances\\Moteur;\nuse Illuminate\\Support\\Collection;\nfinal class %s { public function f(): Collection { return new Collection(); } }\n"],
    'Carbon' => ["<?php\nnamespace Src\\Echeances\\Moteur;\nuse Carbon\\CarbonImmutable;\nfinal class %s { public function f(): CarbonImmutable { return CarbonImmutable::now(); } }\n"],
    'time()' => ["<?php\nnamespace Src\\Echeances\\Moteur;\nfinal class %s { public function f(): int { return time(); } }\n"],
    'date()' => ["<?php\nnamespace Src\\Echeances\\Moteur;\nfinal class %s { public function f(): string { return date('Y'); } }\n"],
    'now()' => ["<?php\nnamespace Src\\Echeances\\Moteur;\nfinal class %s { public function f(): mixed { return now(); } }\n"],
]);
