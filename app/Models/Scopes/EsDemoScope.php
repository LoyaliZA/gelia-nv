<?php

namespace App\Models\Scopes;

use App\Services\Demo\AlcanceDemo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Recorta filas demo o reales según AlcanceDemo.
 * User no lo usa: login y Sanctum resuelven la cuenta antes de fijar el contexto.
 */
class EsDemoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $alcance = app(AlcanceDemo::class);
        if (! $alcance->aplicaFiltro()) {
            return;
        }

        $builder->where($model->getTable().'.es_demo', $alcance->soloDemo());
    }
}
