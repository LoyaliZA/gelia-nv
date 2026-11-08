<?php

namespace App\Services\ControlPedidos;

use Illuminate\Database\Eloquent\Builder;

final class FiltrosBandejaOperativa
{
    public static function aplicar(Builder $query, array $filtros, bool $tareas = false): void
    {
        $fecha = $tareas ? 'solicitada_at' : 'COALESCE(pesaje_solicitado_at, pedidos_bma.created_at)';
        foreach (['desde' => '>=', 'hasta' => '<='] as $clave => $operador) {
            $valor = $filtros[$clave] ?? '';
            if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $valor)
                && checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
                $query->whereRaw("DATE($fecha) $operador ?", [$valor]);
            }
        }
        $vendedora = trim((string) ($filtros['vendedora'] ?? ''));
        if ($vendedora !== '') {
            $query->whereHas($tareas ? 'pedido.vendedor' : 'vendedor', fn ($q) => $q->where('name', 'like', "%{$vendedora}%"));
        }
    }
}
