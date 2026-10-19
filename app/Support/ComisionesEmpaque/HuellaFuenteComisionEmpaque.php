<?php

namespace App\Support\ComisionesEmpaque;

class HuellaFuenteComisionEmpaque
{
    public static function de(int|string $cantidad, mixed $empacadoAt, mixed $canceladoAt, mixed $tipoId): string
    {
        return hash('sha256', json_encode([
            'cantidad' => (int) $cantidad,
            'empacado_at' => (string) $empacadoAt,
            'cancelado' => (string) $canceladoAt,
            'tipo' => $tipoId ?: null,
        ]));
    }

    /**
     * Hecho revisado de una cancelación. No es la huella de la captura manual:
     * esa describe el registro y no cambia cuando el pedido se cancela otra vez.
     */
    public static function deCancelacion(int $pedidoId, mixed $canceladoAt): string
    {
        return hash('sha256', json_encode([
            'hecho' => 'cancelacion',
            'pedido_bma_id' => $pedidoId,
            'cancelado_at' => (string) $canceladoAt,
        ]));
    }
}
