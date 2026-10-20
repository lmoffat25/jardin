<?php

namespace Src\Jardin\Identification;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;

/**
 * `GET /zones/{zone}/etiquettes` (V113, V114, écran B10, critère 10) : une
 * étiquette par plante active de la zone, triées par code court, chacune avec
 * le QR de l'adresse absolue `/p/{code}`. Zone par le scope : une zone de B
 * rend 404.
 */
final class EtiquettesController
{
    public function afficher(string $zone, EncodeurQR $encodeur): Response
    {
        $zone = Str::isUuid($zone) ? Zone::query()->findOrFail($zone) : abort(404);

        $etiquettes = Plante::query()
            ->where('zone_id', $zone->id)
            ->where('etat', 'active')
            ->orderBy('code_court')
            ->get(['code_court', 'nom', 'repere'])
            ->map(fn (Plante $p): array => [
                'code_court' => $p->code_court,
                'nom' => $p->nom,
                'repere' => $p->repere,
                'qr_svg' => $encodeur->svg(url("/p/{$p->code_court}")),
            ]);

        return Inertia::render('Bureau/Etiquettes', [
            'zone' => ['id' => $zone->id, 'nom' => $zone->nom],
            'etiquettes' => $etiquettes,
        ]);
    }
}
