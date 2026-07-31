<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class SolicitarPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen', 'comprobantes']);

        if (! $pedido->puedeSolicitarPesaje()) {
            throw new \RuntimeException('Este pedido no puede solicitar pesaje en su estado actual.');
        }

        if ($pedido->comprobantes()->count() === 0) {
            throw new \InvalidArgumentException('Debe adjuntar al menos un comprobante de pago antes de solicitar el pesaje.');
        }

        if (! $pedido->tienePdfPedido()) {
            throw new \InvalidArgumentException('Debe adjuntar el PDF del pedido antes de solicitar el pesaje.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatus = $pedido->estatus;

            $pedido->update([
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'pesaje_solicitado_at' => now(),
                'pesaje_respondido_at' => null,
                'pesaje_respondido_por_id' => null,
                'motivo_repesaje' => null,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatus->id,
                $estatus->id,
                'Consulta de pesaje enviada a CEDIS.'
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_consulta_pesaje',
                'Nueva consulta de pesaje pendiente',
                ['control_pedidos.cedis'],
                $usuarioId,
                false,
                ['url' => '/control-pedidos/cedis?tab=PENDIENTES_PESAJE']
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'tipoCaja',
            ]);
        });
    }
}
