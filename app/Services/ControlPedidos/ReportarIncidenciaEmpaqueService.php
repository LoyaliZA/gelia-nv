<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;

class ReportarIncidenciaEmpaqueService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(PedidoBma $pedido, int $usuarioId, string $detalle): PedidoBma
    {
        if (!$pedido->puedeReportarIncidencia()) {
            throw new \RuntimeException('Solo se puede reportar incidencia en pedidos pendientes de empaque.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId, $detalle) {
            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS)
                ?? CatalogoEstatusPedido::porCodigo('ROJO');

            if (!$estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus INCIDENCIA_CEDIS.');
            }

            $pedido->update([
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'detalle_incidencia_empaque' => $detalle,
                'incidencia_empaque_at' => now(),
                'incidencia_empaque_por_id' => $usuarioId,
            ]);

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $estatusAnterior,
                $estatusNuevo,
                "Incidencia de empaque: {$detalle}"
            );

            return $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'empacadoPor', 'incidenciaEmpaquePor',
            ]);
        });
    }
}
