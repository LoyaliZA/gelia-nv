<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class LiberarResguardoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->es_resguardo) {
            throw new \InvalidArgumentException('Este pedido no está en resguardo.');
        }

        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede liberar resguardo en pedidos pendientes de revisión.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatus = $pedido->estatus;

            $pedido->update(['es_resguardo' => false]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                'Resguardo liberado por el auxiliar.'
            );

            return $pedido->fresh(['cliente', 'estatus', 'origen', 'documentos', 'almacen', 'banco']);
        });
    }
}
