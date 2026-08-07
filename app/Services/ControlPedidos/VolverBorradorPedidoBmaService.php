<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class VolverBorradorPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeVolverABorrador()) {
            throw new \RuntimeException('Solo se puede volver a borrador desde pesaje pendiente.');
        }

        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus borrador.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $estatusNuevo) {
            $estatusAnterior = $pedido->estatus;
            $datos = [
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            ];

            // Cancela consulta pendiente; conserva pesaje ya respondido.
            // estatus_envio NOT NULL (default 'completo'): no usar null.
            if ($pedido->estatus_envio === PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE) {
                $datos['estatus_envio'] = $pedido->tienePesajeRespondido()
                    ? PedidoBma::ESTATUS_ENVIO_PESAJE_LISTO
                    : PedidoBma::ESTATUS_ENVIO_COMPLETO;
                if (! $pedido->tienePesajeRespondido()) {
                    $datos['pesaje_solicitado_at'] = null;
                }
            }

            $pedido->update($datos);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusAnterior->id,
                $estatusNuevo->id,
                'Pedido conservado como borrador (pre-venta).',
                AccionesHistorialPedidoBma::VOLVER_BORRADOR
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'cajas.tipoCaja', 'cajas.tipoGuia', 'tipoCaja',
            ]);
        });
    }
}
