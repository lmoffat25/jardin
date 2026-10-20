<?php

namespace Src\Bibliotheque\Domaine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Une espèce de la bibliothèque (CDC §5, chapitre 4 §3.3), repérée par la clé
 * stable de son fichier : référentiel partagé par tous les jardins, écrit par
 * le seul chargement de la bibliothèque. Le modèle refuse toute autre écriture.
 *
 * @property int $id
 * @property int|null $famille_id
 * @property string $cle
 * @property string $nom_commun
 * @property string|null $nom_latin
 * @property string $categorie
 * @property string $cycle
 */
class Espece extends Model
{
    public $timestamps = false;

    protected $table = 'especes';

    protected $guarded = [];

    /** @return BelongsTo<FamilleBotanique, $this> */
    public function famille(): BelongsTo
    {
        return $this->belongsTo(FamilleBotanique::class, 'famille_id');
    }

    /** @return HasMany<RegleReference, $this> */
    public function reglesReference(): HasMany
    {
        return $this->hasMany(RegleReference::class);
    }

    protected static function booted(): void
    {
        $refuser = static fn (): never => throw new LogicException('Les espèces sont en lecture seule : le chargement de la bibliothèque les écrit.');
        static::saving($refuser);
        static::deleting($refuser);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['id' => 'integer', 'famille_id' => 'integer', 'hauteur_adulte_cm' => 'integer'];
    }
}
