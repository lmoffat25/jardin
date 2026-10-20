<?php

namespace Src\Jardin\Identification;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Src\Bibliotheque\Domaine\Espece;
use Src\Jardin\Domaine\CompteurCodeCourt;
use Src\Support\Jardin\JardinCourant;

/**
 * Le code court d'une plante neuve (RG-13, S8, D-03) : `FRU-014`, tiré du
 * compteur du jardin courant pour le préfixe de la catégorie de l'espèce,
 * `PLA` sans espèce. Jamais réattribué, même après archivage : on ne compte
 * pas les plantes, on incrémente le compteur sous verrou de ligne, dans la
 * transaction de création.
 */
final class AttribuerCodeCourt
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    public function pour(?Espece $espece): string
    {
        // Hors transaction, le verrou tomberait avant l'écriture du compteur.
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Le code court s\'attribue dans la transaction de création de la plante.');
        }

        $prefixe = self::prefixe($espece);
        // `insertOrIgnore` passe outre le modèle : le jardin se pose à la main.
        CompteurCodeCourt::query()->insertOrIgnore(['jardin_id' => $this->jardinCourant->id(), 'prefixe' => $prefixe, 'dernier' => 0]);
        $compteur = CompteurCodeCourt::query()->where('prefixe', $prefixe);
        $numero = (clone $compteur)->lockForUpdate()->sole()->dernier + 1;
        $compteur->update(['dernier' => $numero]);

        return sprintf('%s-%03d', $prefixe, $numero);
    }

    /** Les trois premières lettres de la catégorie, sans accent, en majuscules. */
    private static function prefixe(?Espece $espece): string
    {
        return $espece === null ? 'PLA' : Str::upper(Str::substr(Str::ascii($espece->categorie), 0, 3));
    }
}
