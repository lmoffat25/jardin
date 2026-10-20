<?php

namespace Src\Support\Jardin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Le filtre par jardin (D1, RG-21) : toute lecture d'un modèle métier est
 * bornée au jardin courant, sans qu'on l'écrive. Sans jardin courant, la
 * requête lève au lieu de tout rendre.
 */
final class JardinScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     *
     * @throws JardinCourantAbsent
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('jardin_id'), app(JardinCourant::class)->id());
    }
}
