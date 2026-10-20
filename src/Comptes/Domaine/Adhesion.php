<?php

namespace Src\Comptes\Domaine;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le lien d'un utilisateur à un jardin, avec son rôle (RG-20). Lue par
 * utilisateur, pour savoir quels jardins il peut ouvrir : elle ne passe donc
 * pas par le filtre du jardin courant (liste du test V34).
 *
 * @property string $id
 * @property string $utilisateur_id
 * @property string $jardin_id
 * @property string $role 'proprietaire' | 'membre'
 */
class Adhesion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'adhesions';

    protected $guarded = ['id'];

    /** @return BelongsTo<Utilisateur, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /** @return BelongsTo<Jardin, $this> */
    public function jardin(): BelongsTo
    {
        return $this->belongsTo(Jardin::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invite_le' => 'immutable_datetime',
            'accepte_le' => 'immutable_datetime',
        ];
    }
}
