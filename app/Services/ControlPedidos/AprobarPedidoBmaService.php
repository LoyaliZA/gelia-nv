<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class AprobarPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se pueden aprobar pedidos pendientes de revisión.');
        }

        if (!$pedido->tienePagoValidado()) {
            throw new \RuntimeException('Debe validar el pago antes de aprobar.');
        }

        if (!$pedido->tieneRemision()) {
            throw new \RuntimeException('Debe adjuntar la remisión PDF antes de aprobar.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_EN_CEDIS)
                ?? CatalogoEstatusPedido::porCodigo('AMARILLO');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus EN_CEDIS.');
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                'Pedido aprobado y enviado a Registro General.'
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
            ]);
        });
    }
}
