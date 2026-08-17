<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Support\Facades\DB;

class ReabrirEnvioPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        $pedido->loadMissing('estatus');

        if (! $pedido->puedeReabrirEnvio()) {
            throw new \RuntimeException('Solo se puede reabrir un pedido marcado como enviado.');
        }

        MaquinaEstadosPedidoBma::assertTransicion(
            $pedido->estatus?->fase_ciclo,
            CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO
        );

        $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PENDIENTE_DE_ENVIO);
        if (! $estatusNuevo) {
            throw new \RuntimeException('No se encontró el estatus PENDIENTE_DE_ENVIO.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $estatusNuevo) {
            $estatusAnterior = $pedido->estatus;

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            ]);

            $pedido->cajas()->update([
                'estatus_recoleccion' => \App\Models\ControlPedidos\PedidoBmaCaja::ESTATUS_PENDIENTE,
                'recolectada_at' => null,
                'recolectada_por_id' => null,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                'Envío reabierto; pendiente de recolección (la paquetería no recogió).',
                AccionesHistorialPedidoBma::REABRIR_ENVIO
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'vendedor',
            ]);
        });
    }
}
