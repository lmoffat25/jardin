<?php

namespace Src\Comptes\Domaine;

use Database\Factories\UtilisateurFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Un compte (chapitre 4 §3.1) : il se connecte par son courriel, et n'accède
 * qu'aux jardins de ses adhésions (RG-20). Pas de jeton « se souvenir de moi » :
 * la table n'en a pas.
 *
 * @property string $id
 * @property string $courriel
 * @property string $mot_de_passe_hache
 * @property string|null $nom_affiche
 */
class Utilisateur extends Authenticatable
{
    /** @use HasFactory<UtilisateurFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'utilisateurs';

    protected $guarded = ['id'];

    protected $hidden = ['mot_de_passe_hache'];

    protected $authPasswordName = 'mot_de_passe_hache';

    protected $rememberTokenName = '';

    /** @return HasMany<Adhesion, $this> */
    public function adhesions(): HasMany
    {
        return $this->hasMany(Adhesion::class);
    }

    protected static function newFactory(): UtilisateurFactory
    {
        return UtilisateurFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mot_de_passe_hache' => 'hashed',
            'cree_le' => 'immutable_datetime',
            'derniere_connexion_le' => 'immutable_datetime',
            'supprime_le' => 'immutable_datetime',
        ];
    }
}
