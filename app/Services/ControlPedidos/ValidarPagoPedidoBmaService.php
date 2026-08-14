<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class ValidarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private RegistrarPagoPedidoBmaService $pagos,
    ) {}

    /**
     * @return array{pedido: PedidoBma, resumen: array, incidencia_id: ?int}
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId): array
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede validar el pago de pedidos pendientes de revisión.');
        }

        $this->pagos->assertPagoListoParaAvanzar($pedido, RegistrarPagoPedidoBmaService::FASE_VALIDAR);

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido->update([
                'pago_validado_at' => now(),
                'pago_validado_por_id' => $usuarioId,
            ]);

            $resumen = $this->pagos->resumenPago($pedido->fresh());

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $pedido->estatus,
                $pedido->estatus,
                'Pago validado por auxiliar.',
                AccionesHistorialPedidoBma::VALIDACION_PAGO
            );

            $pedidoFresh = $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                'pagosExhibicion.banco',
            ]);

            return [
                'pedido' => $pedidoFresh,
                'resumen' => $resumen,
                'incidencia_id' => null,
            ];
        });
    }
}
