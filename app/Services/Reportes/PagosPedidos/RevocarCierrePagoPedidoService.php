<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;

class RevocarCierrePagoPedidoService
{
    public function ejecutar(PedidoBma $pedido, int $usuarioId, ?string $motivo = null): ?PedidoBmaCierrePago
    {
        $cierre = PedidoBmaCierrePago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado', PedidoBmaCierrePago::ESTADO_VIGENTE)
            ->first();

        if (! $cierre) {
            return null;
        }

        $cierre->update([
            'estado' => PedidoBmaCierrePago::ESTADO_REVOCADO,
            'revocado_at' => now(),
            'revocado_por_id' => $usuarioId,
            'motivo_revocacion' => $motivo ?? 'Validación revocada por rechazo de exhibiciones',
        ]);

        return $cierre->fresh();
    }
}
