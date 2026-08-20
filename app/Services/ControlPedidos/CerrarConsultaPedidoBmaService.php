<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;

class CerrarConsultaPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen', 'vendedor']);

        $actor = \App\Models\User::find($usuarioId);
        if (! $actor || ! VisibilidadPedidoBma::puedeMutarComoVendedora($actor, $pedido)) {
            throw new \RuntimeException('Solo la vendedora del pedido puede cerrar la consulta.');
        }

        if (! $pedido->puedeCerrarConsulta()) {
            throw new \RuntimeException(
                'No se puede cerrar la consulta: se requiere respuesta vigente de CEDIS y que no haya actualización pendiente.'
            );
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatus = $pedido->estatus;
            $pedido->update([
                'consulta_cerrada_at' => now(),
                'consulta_cerrada_por_id' => $usuarioId,
                'consulta_actualizacion_pendiente' => false,
            ]);

            $label = $pedido->esConsultaMercancia() ? 'Consulta de mercancía' : 'Consulta de pesaje';
            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                "{$label} cerrada por Ventas (cliente confirma piezas).",
                AccionesHistorialPedidoBma::CIERRE_CONSULTA
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'origen',
            ]);
        });
    }
}
