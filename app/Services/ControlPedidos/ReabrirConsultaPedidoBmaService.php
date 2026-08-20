<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;

class ReabrirConsultaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen', 'vendedor']);

        $actor = \App\Models\User::find($usuarioId);
        if (! $actor || ! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
            throw new \RuntimeException('Solo la vendedora del pedido puede reabrir la consulta.');
        }

        if (! $pedido->consultaCerrada()) {
            throw new \RuntimeException('La consulta no está cerrada.');
        }

        if (! $pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('El pedido ya no es editable en pre-venta.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatus = $pedido->estatus;
            $pedido->update([
                'consulta_cerrada_at' => null,
                'consulta_cerrada_por_id' => null,
            ]);

            $label = $pedido->esConsultaMercancia() ? 'Consulta de mercancía' : 'Consulta de pesaje';
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                "{$label} reabierta por Ventas.",
                AccionesHistorialPedidoBma::REABRIR_CONSULTA
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'origen',
            ]);
        });
    }
}
