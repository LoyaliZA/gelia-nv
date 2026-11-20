<?php

namespace App\Support\ControlPedidos\Nacional;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCajaRemision;
use RuntimeException;

/** Sin seguro, varias cajas no exigen desglose. Con seguro, cada remisión cabe en una sola caja. */
final class BarreraSeguroCajaNacional
{
    public static function assert(PedidoBma $pedido): void
    {
        if (! CapturaNacional::es($pedido) || ! $pedido->aplica_seguro) {
            return;
        }

        $pedido->loadMissing('cajas');
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $caja) => $caja->estaActiva());
        if ($activas->count() <= 1) {
            return;
        }

        $filas = PedidoBmaCajaRemision::query()
            ->whereIn('pedido_bma_caja_id', $activas->pluck('id'))
            ->where('activa', true)
            ->get();
        $porCaja = $filas->groupBy('pedido_bma_caja_id');
        foreach ($activas as $caja) {
            $asignadas = $porCaja->get($caja->id, collect());
            if ($asignadas->isEmpty() || $asignadas->contains(fn (PedidoBmaCajaRemision $fila) => ! $fila->cabe)) {
                throw new RuntimeException('El seguro de varias cajas queda bloqueado hasta asignar cada remisión a una caja.');
            }
        }

        $vistas = [];
        foreach ($filas as $fila) {
            $clave = $fila->pedido_bma_id ? 'p'.$fila->pedido_bma_id : 'f'.$fila->folio_externo;
            if (isset($vistas[$clave])) {
                throw new RuntimeException('Una remisión no se divide entre cajas.');
            }
            $vistas[$clave] = true;
        }
    }
}
