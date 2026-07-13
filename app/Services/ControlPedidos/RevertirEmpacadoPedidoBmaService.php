<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class RevertirEmpacadoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId): PedidoBma
    {
        if (!$pedido->puedeRevertirEmpacado()) {
            throw new \RuntimeException('Solo se puede revertir un pedido recién empacado sin guía asignada.');
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
                'empacado_at' => null,
                'empacado_por_id' => null,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                'Empaque revertido; pedido devuelto a pendiente.'
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'incidenciaEmpaquePor',
            ]);
        });
    }
}
