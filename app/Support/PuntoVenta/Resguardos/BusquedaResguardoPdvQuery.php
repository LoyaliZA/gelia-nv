<?php

namespace App\Support\PuntoVenta\Resguardos;

use Illuminate\Database\Eloquent\Builder;

final class BusquedaResguardoPdvQuery
{
    public static function aplicar(Builder $query, string $termino): void
    {
        $termino = trim($termino);
        if ($termino === '') {
            return;
        }

        $like = '%'.$termino.'%';

        $query->where(function (Builder $q) use ($like, $termino) {
            $q->where('snapshot_folio', 'like', $like)
                ->orWhere('snapshot_cliente_nombre', 'like', $like)
                ->orWhereHas('bultos', function (Builder $bultos) use ($termino) {
                    $bultos->where('codigo_etiqueta', $termino)
                        ->orWhere('folio', 'like', '%'.$termino.'%');
                })
                ->orWhereHas('pedido', function (Builder $pedido) use ($like, $termino) {
                    $pedido->where('folio', 'like', $like)
                        ->orWhere('folio_remision', 'like', $like);

                    if (is_numeric($termino)) {
                        $pedido->orWhere('id', (int) $termino);
                    }
                })
                ->orWhereHas('cliente', function (Builder $cliente) use ($like, $termino) {
                    $cliente->where('nombre', 'like', $like);

                    if (is_numeric($termino)) {
                        $cliente->orWhere('numero_cliente', 'like', $like);
                    }
                });
        });
    }
}
