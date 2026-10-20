<?php

namespace Src\Comptes\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /export` (V122, écran B12) : l'archive du jardin courant, envoyée au
 * fil de son écriture. Ouverte au membre, comme toute lecture (D-49).
 */
final class ExportController
{
    public function exporter(ExporterJardin $exporter): StreamedResponse
    {
        return response()->streamDownload(function () use ($exporter): void {
            $sortie = fopen('php://output', 'wb');
            if ($sortie === false) {
                throw new \RuntimeException('Sortie de l\'export impossible à ouvrir.');
            }
            $exporter->ecrire($sortie);
        }, $exporter->nomDuFichier(), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }
}
