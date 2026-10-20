<?php

namespace Src\Bibliotheque\TypesIntervention;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Src\Support\Jardin\JardinCourant;
use Src\Support\Jardin\JardinCourantAbsent;

/**
 * Les types d'intervention visibles du jardin courant (D-55) : les fournis
 * (`jardin_id` nul) et les siens, jamais ceux d'un autre jardin. Comme
 * `JardinScope`, sans jardin courant la requête lève au lieu de tout rendre ;
 * le chargeur et le jeu de référence s'en passent explicitement.
 */
final class TypesVisibles implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     *
     * @throws JardinCourantAbsent
     */
    public function apply(Builder $builder, Model $model): void
    {
        $jardin = app(JardinCourant::class)->id();
        $colonne = $model->qualifyColumn('jardin_id');

        $builder->where(fn (Builder $q) => $q->whereNull($colonne)->orWhere($colonne, $jardin));
    }
}
