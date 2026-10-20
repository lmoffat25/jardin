<?php

namespace Src\Jardin\Plantes;

use Illuminate\Support\Facades\DB;
use Src\Bibliotheque\Domaine\Espece;
use Src\Bibliotheque\ReglesTypes\AppliquerReglesTypes;
use Src\Jardin\Domaine\Plante;
use Src\Jardin\Domaine\Zone;
use Src\Jardin\Identification\AttribuerCodeCourt;
use Src\Jardin\Localisation\EnregistrerPosition;

/**
 * Crée une plante du jardin courant (parcours P4, RG-13, RG-39) : son code
 * court et la copie des règles de son espèce, dans une seule transaction.
 * La saisie est validée par l'appelant (zone par `ZoneDuJardin`) ; la zone et
 * l'espèce sont relues ici, la zone par le filtre du jardin courant. Créée
 * sur le terrain, elle reçoit la position du téléphone en point `gps` (RG-15,
 * V111).
 *
 * @phpstan-import-type Ajustement from AppliquerReglesTypes
 *
 * @phpstan-type Saisie array{nom: string, zone_id: string, espece_id?: int|null, variante?: string|null, repere?: string|null, variete?: string|null, plantee_le?: string|null, regles_ecartees?: list<string>, ajustements?: array<string, Ajustement>, position?: array{lng: float|int|string, lat: float|int|string, precision_m?: float|int|string|null}|null}
 */
final class CreerPlante
{
    public function __construct(
        private readonly AttribuerCodeCourt $codeCourt,
        private readonly AppliquerReglesTypes $reglesTypes,
        private readonly EnregistrerPosition $position,
    ) {}

    /** @param  Saisie  $saisie */
    public function executer(array $saisie): Plante
    {
        return DB::transaction(function () use ($saisie): Plante {
            $zone = Zone::query()->whereNull('archivee_le')->findOrFail($saisie['zone_id']);
            $espece = isset($saisie['espece_id']) ? Espece::query()->findOrFail($saisie['espece_id']) : null;

            $plante = Plante::create([
                'zone_id' => $zone->id,
                'code_court' => $this->codeCourt->pour($espece),
                'nom' => $saisie['nom'],
                'espece_id' => $espece?->id,
                'variante' => $saisie['variante'] ?? null,
                'cycle' => $espece->cycle ?? 'perenne',
                'repere' => $saisie['repere'] ?? null,
                'variete' => $saisie['variete'] ?? null,
                'plantee_le' => $saisie['plantee_le'] ?? null,
            ]);

            $position = $saisie['position'] ?? null;
            if ($position !== null) {
                $this->position->depuisCreation($plante, [
                    'lng' => (float) $position['lng'],
                    'lat' => (float) $position['lat'],
                    'precision_m' => isset($position['precision_m']) ? (float) $position['precision_m'] : null,
                ]);
            }

            if ($espece !== null) {
                $this->reglesTypes->executer($plante, $espece, $saisie['regles_ecartees'] ?? [], $saisie['ajustements'] ?? []);
            }

            return $plante;
        });
    }
}
