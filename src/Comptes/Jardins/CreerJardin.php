<?php

namespace Src\Comptes\Jardins;

use Illuminate\Support\Facades\DB;
use Src\Comptes\Domaine\Adhesion;
use Src\Comptes\Domaine\Jardin;
use Src\Comptes\Domaine\Utilisateur;
use Src\Support\Jardin\JardinCourant;

/**
 * Crée un jardin et l'adhésion `proprietaire` de son créateur (RG-20, RG-22),
 * dans une seule transaction, puis en fait le jardin courant. Seul le code de
 * commune est écrit, jamais des coordonnées (RG-28, D-48).
 */
final class CreerJardin
{
    public function __construct(private readonly JardinCourant $jardinCourant) {}

    public function executer(Utilisateur $u, string $nom, int $profilId, ?string $communeInsee = null): Jardin
    {
        $jardin = DB::transaction(function () use ($u, $nom, $profilId, $communeInsee): Jardin {
            $jardin = Jardin::create([
                'proprietaire_id' => $u->id,
                'nom' => $nom,
                'profil_climatique_id' => $profilId,
                'commune_insee' => $communeInsee,
            ]);
            Adhesion::create(['utilisateur_id' => $u->id, 'jardin_id' => $jardin->id, 'role' => 'proprietaire']);

            return $jardin;
        });

        session()->put('jardin_id', $jardin->id);
        $this->jardinCourant->positionner($jardin->id);

        return $jardin;
    }
}
