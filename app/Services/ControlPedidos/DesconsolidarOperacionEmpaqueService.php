<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\OperacionEmpaque;
use App\Models\ControlPedidos\PedidoBma;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class DesconsolidarOperacionEmpaqueService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
    ) {}

    public function ejecutar(OperacionEmpaque $operacion, int $usuarioId): void
    {
        if (!$operacion->estaAbierta()) {
            throw new \RuntimeException('Solo se puede desconsolidar una operación abierta (aún no empacada).');
        }

        DB::transaction(function () use ($operacion, $usuarioId) {
            $operacion->loadMissing('miembros.pedido');

            foreach ($operacion->miembros as $miembro) {
                $pedido = $miembro->pedido;
                if (!$pedido) {
                    continue;
                }

                $pedido->update(['estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO]);

                $estatusId = $pedido->catalogo_estatus_pedido_id;
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuarioId,
                    $estatusId,
                    $estatusId,
                    "Desconsolidado de operación {$operacion->folio_operacion}.",
                    AccionesHistorialPedidoBma::DESCONSOLIDACION
                );
            }

            $operacion->miembros()->delete();
            $operacion->delete();
        });
    }
}
