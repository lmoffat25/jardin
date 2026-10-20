<?php

namespace Src\Echeances\TableauDeBord;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Comptes\Domaine\Jardin;
use Src\Jardin\Domaine\Zone;
use Src\Support\Jardin\JardinCourant;

/**
 * Le détail d'un groupe du tableau de bord, chargé à la demande (V77, CDC
 * §8.7) : la zone est cherchée par le scope du jardin courant, `404` sinon.
 */
final class DetailGroupeController
{
    public function __construct(
        private readonly RegrouperEcheances $regrouper,
        private readonly JardinCourant $jardinCourant,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{zone: string, type: string, periode: string} $saisie */
        $saisie = $request->validate([
            'zone' => ['required', 'uuid'],
            'type' => ['required', 'integer'],
            'periode' => ['required', 'string', function (string $attribut, mixed $valeur, \Closure $echec) {
                if (! is_string($valeur) || ! RegrouperEcheances::periodeConnue($valeur)) {
                    $echec('Période inconnue.');
                }
            }],
        ]);

        $zone = Zone::query()->findOrFail($saisie['zone']);

        return response()->json($this->regrouper->detail(
            Jardin::query()->findOrFail($this->jardinCourant->id()),
            $zone->id,
            (int) $saisie['type'],
            $saisie['periode'],
        ));
    }
}
