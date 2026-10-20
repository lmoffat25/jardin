<?php

namespace Src\Bibliotheque\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Src\Bibliotheque\Domaine\SourceRegle;

/**
 * Interroge chaque adresse de `sources_regle` et liste celles qui ne répondent
 * pas (V66, C14, chapitre 6 §6.7) : `HEAD`, puis `GET` si le `HEAD` échoue
 * (bien des serveurs le refusent), dix secondes au plus chacun. Lancée à la
 * main par `make sources`, jamais par la CI : un site tiers lent ou tombé ne
 * doit pas bloquer une fusion.
 */
final class VerifierSources extends Command
{
    private const DELAI_S = 10;

    protected $signature = 'bibliotheque:verifier-sources';

    protected $description = 'Interroge chaque source de la bibliothèque et liste celles qui ne répondent pas';

    public function handle(): int
    {
        /** @var list<string> $urls */
        $urls = SourceRegle::query()->distinct()->orderBy('url')->pluck('url')->all();
        $echecs = [];
        foreach ($urls as $url) {
            $raison = $this->interroger($url);
            if ($raison !== null) {
                $echecs[] = $url;
                $this->line("échec : {$url} ({$raison})");
            }
        }

        $this->line(sprintf('sources : %d interrogées, %d en échec', count($urls), count($echecs)));

        return $echecs === [] ? self::SUCCESS : self::FAILURE;
    }

    /** La raison de l'échec, ou null si l'adresse répond. */
    private function interroger(string $url): ?string
    {
        $raison = null;
        foreach (['head', 'get'] as $methode) {
            try {
                /** @var Response $reponse */
                $reponse = Http::timeout(self::DELAI_S)->connectTimeout(self::DELAI_S)->{$methode}($url);
                if ($reponse->successful()) {
                    return null;
                }
                $raison = 'HTTP '.$reponse->status();
            } catch (ConnectionException $e) {
                $raison = 'injoignable : '.$e->getMessage();
            }
        }

        return $raison;
    }
}
