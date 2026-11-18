<?php

namespace App\Support\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

/**
 * Límite compartido del motor: primer cierre de consulta.
 * Verdadero si consulta_cerrada_at está lleno O existe historial accion=cierre_consulta
 * (tabla pedido_bma_historial_estados, sin filtrar bitácora). Reabrir no restaura permiso.
 */
final class ConsultaPedidoYaCerradaAlgunaVez
{
    public static function es(PedidoBma $pedido): bool
    {
        if ($pedido->consultaCerrada()) {
            return true;
        }

        if ($pedido->relationLoaded('historial')) {
            return $pedido->historial->contains(
                static fn ($fila) => ($fila->accion ?? null) === AccionesHistorialPedidoBma::CIERRE_CONSULTA
            );
        }

        return $pedido->historial()
            ->where('accion', AccionesHistorialPedidoBma::CIERRE_CONSULTA)
            ->exists();
    }
}
