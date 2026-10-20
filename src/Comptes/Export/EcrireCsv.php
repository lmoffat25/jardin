<?php

namespace Src\Comptes\Export;

/**
 * Un CSV de l'export (D-61, D-112) : UTF-8 avec BOM, pour qu'un tableur
 * l'ouvre sans demander l'encodage ; `;` entre les champs, le séparateur
 * qu'attend un tableur réglé en français ; l'en-tête en première ligne.
 */
final class EcrireCsv
{
    private const BOM = "\u{FEFF}";

    /**
     * @param  resource  $flux
     * @param  list<string>  $entetes
     */
    public function __construct(private $flux, array $entetes)
    {
        fwrite($this->flux, self::BOM);
        $this->ligne($entetes);
    }

    /** @param  list<mixed>  $valeurs */
    public function ligne(array $valeurs): void
    {
        fputcsv($this->flux, array_map(self::valeur(...), $valeurs), ';', '"', '');
    }

    /** Vide pour `null`, `oui` / `non` pour un booléen, le texte de Postgres sinon. */
    public static function valeur(mixed $valeur): string
    {
        return match (true) {
            $valeur === null => '',
            $valeur === true => 'oui',
            $valeur === false => 'non',
            is_scalar($valeur) => (string) $valeur,
            default => (string) json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
