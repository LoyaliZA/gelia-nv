<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda por cualquier envío de un Multienvío: quien teclea el folio, la remisión o el rastreo de un envío
 * encuentra también a sus hermanos. No concede permisos: la consulta base de cada bandeja ya restringe lo visible.
 */
final class BusquedaGrupoMultienvio
{
    /** @param  list<string>  $columnas */
    public static function orHermano(Builder $q, string $termino, array $columnas = ['folio', 'folio_remision', 'numero_remision']): void
    {
        $q->orWhereIn('multienvio_nacional_id', PedidoBma::query()
            ->select('multienvio_nacional_id')
            ->whereNotNull('multienvio_nacional_id')
            ->where(function (Builder $m) use ($termino, $columnas) {
                foreach ($columnas as $i => $columna) {
                    $m->{$i === 0 ? 'where' : 'orWhere'}($columna, 'like', "%{$termino}%");
                }
            }));
    }
}
