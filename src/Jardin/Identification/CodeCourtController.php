<?php

namespace Src\Jardin\Identification;

use Illuminate\Http\RedirectResponse;

/**
 * `GET /p/{code}` (V112, écrans T6 et B6) : l'adresse que porte le QR d'une
 * étiquette. Elle mène à la fiche de terrain, on la scanne au jardin. Un code
 * d'un autre jardin rend le même 404 qu'un code inexistant (critère 22).
 */
final class CodeCourtController
{
    public function ouvrir(string $code, ResoudreCodeCourt $resoudre): RedirectResponse
    {
        $plante = $resoudre->plante($code) ?? abort(404);

        return redirect("/plantes/{$plante->id}?contexte=terrain");
    }
}
