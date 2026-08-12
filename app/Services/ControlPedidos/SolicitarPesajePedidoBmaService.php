<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class SolicitarPesajePedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'origen']);

        if (! $pedido->puedeSolicitarPesaje()) {
            throw new \RuntimeException('Este pedido no puede solicitar pesaje en su estado actual.');
        }

        if (! $pedido->tienePdfPedido()) {
            throw new \InvalidArgumentException('Debe adjuntar el PDF o una foto del pedido antes de solicitar el pesaje.');
        }

        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus de pesaje pendiente.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $estatusNuevo) {
            $estatusAnterior = $pedido->estatus;

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'pesaje_solicitado_at' => now(),
                'pesaje_respondido_at' => null,
                'pesaje_respondido_por_id' => null,
                'motivo_repesaje' => null,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusAnterior->id,
                $estatusNuevo->id,
                'Consulta de pesaje enviada a CEDIS.',
                AccionesHistorialPedidoBma::SOLICITUD_PESAJE
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
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja',
            ]);
        });
    }
}
