<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\SaldosAFavor\RegistrarIncidenciaSafService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use Illuminate\Support\Facades\DB;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;

class ValidarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private RegistrarPagoPedidoBmaService $pagos,
        private RegistrarIncidenciaSafService $incidencias,
    ) {}

    /**
     * @return array{pedido: PedidoBma, resumen: array, incidencia_id: ?int}
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId): array
    {
        if (!$pedido->esAuditablePorAuxiliar()) {
            throw new \RuntimeException('Solo se puede validar el pago de pedidos pendientes de revisión.');
        }

        if ($pedido->documentos()->where('tipo', 'comprobante')->count() === 0
            && $pedido->pagosExhibicion()->count() === 0) {
            throw new \RuntimeException('El pedido no tiene comprobantes ni exhibiciones de pago.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido->update([
                'pago_validado_at' => now(),
                'pago_validado_por_id' => $usuarioId,
            ]);

            $resumen = $this->pagos->resumenPago($pedido->fresh());
            $incidenciaId = null;

            $tieneExhibiciones = PedidoBmaPago::where('pedido_bma_id', $pedido->id)->exists();
            if ($tieneExhibiciones && (float) $resumen['pendiente'] > 0.01) {
                $inc = $this->incidencias->handle(
                    'pago_parcial_validado',
                    sprintf(
                        'Pago validado con faltante en pedido %s. Pendiente: %s. Exhibiciones registradas.',
                        $pedido->folio,
                        number_format((float) $resumen['pendiente'], 2, '.', '')
                    ),
                    $pedido->cliente_id ? (int) $pedido->cliente_id : null,
                    $pedido->id,
                    null,
                    $usuarioId
                );
                $incidenciaId = $inc->id;
            }

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
            ]);

            return [
                'pedido' => $pedidoFresh,
                'resumen' => $resumen,
                'incidencia_id' => $incidenciaId,
            ];
        });
    }
}
