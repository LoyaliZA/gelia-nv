<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Services\Reportes\PagosPedidos\RegistrarCierrePagoPedidoService;
use App\Services\SaldosAFavor\CoberturaPagoPedidoBmaService;
use App\Services\SaldosAFavor\RegistrarPagoPedidoBmaService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\CamposIncorrectosPedidoBma;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ValidarPagoPedidoBmaService
{
    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private RegistrarPagoPedidoBmaService $pagos,
        private AvanzarColaErroresPedidoBmaService $colaErroresService,
        private CoberturaPagoPedidoBmaService $cobertura,
        private PagosPedidoBmaConfig $pagosConfig,
        private RegistrarCierrePagoPedidoService $registrarCierre,
    ) {}

    /**
     * @return array{pedido: PedidoBma, resumen: array, incidencia_id: ?int}
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId): array
    {
        if (! $pedido->esAuditablePorAuxiliar()) {
            throw new RuntimeException('Solo se puede validar el pago de pedidos pendientes de revisión.');
        }

        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->pago_validado_at) {
                $resumen = $this->pagos->resumenPago($pedido);

                return [
                    'pedido' => $pedido->fresh([
                        'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                        'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                        'pagosExhibicion.banco',
                        'errores',
                    ]),
                    'resumen' => $resumen,
                    'incidencia_id' => null,
                ];
            }

            $this->pagos->assertPagoListoParaAvanzar($pedido, RegistrarPagoPedidoBmaService::FASE_VALIDAR);

            $resumen = $this->cobertura->calcular($pedido);
            if (! empty($resumen['bloqueos'])) {
                throw new RuntimeException($resumen['bloqueos'][0]);
            }

            PedidoBmaPago::query()
                ->where('pedido_bma_id', $pedido->id)
                ->activosParaCobertura()
                ->where('estado_revision', '!=', PedidoBmaPago::REVISION_VERIFICADO)
                ->update([
                    'estado_revision' => PedidoBmaPago::REVISION_VERIFICADO,
                    'revisado_por_id' => $usuarioId,
                    'revisado_at' => now(),
                ]);

            $pedido->update([
                'pago_validado_at' => now(),
                'pago_validado_por_id' => $usuarioId,
            ]);

            $this->registrarCierre->ejecutar($pedido->fresh(), $usuarioId);

            $antes = CamposIncorrectosPedidoBma::filtrar($pedido->campos_incorrectos ?? []);
            $restantes = $this->colaErroresService->quitarCampos(
                $pedido,
                ['pago_validado'],
                $usuarioId,
                'Pago revalidado'
            );
            if ($restantes !== $antes) {
                $pedido->update(
                    $restantes === []
                        ? $this->colaErroresService->attrsColaVacia()
                        : $this->colaErroresService->attrsColaPendiente($restantes)
                );
            }

            $resumen = $this->pagos->resumenPago($pedido->fresh());
            $diferencia = (string) ($resumen['diferencia'] ?? '0.00');
            $tolerancia = (string) ($resumen['tolerancia_aplicada'] ?? $this->pagosConfig->toleranciaMxn());

            $comentario = sprintf(
                'Pago validado por auxiliar. Diferencia %s MXN (tolerancia aplicada %s MXN).',
                $diferencia,
                $tolerancia
            );

            $this->historialService->registrarTransicion(
                $pedido->id,
                $usuarioId,
                $pedido->estatus,
                $pedido->estatus,
                $comentario,
                AccionesHistorialPedidoBma::VALIDACION_PAGO
            );

            $pedidoFresh = $pedido->fresh([
                'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                'pagosExhibicion.banco',
                'errores',
            ]);

            if ($pedidoFresh->esperando_pago_at) {
                app(MarcarEsperaPagoPedidoService::class)->salirPorPago(
                    $pedidoFresh,
                    \App\Models\User::findOrFail($usuarioId)
                );
                $pedidoFresh = $pedidoFresh->fresh([
                    'cliente', 'estatus', 'documentos', 'banco', 'almacen',
                    'paqueteria', 'tipoGuia', 'tipoCaja', 'zona', 'envioTienda', 'pagoValidadoPor',
                    'pagosExhibicion.banco',
                    'errores',
                ]);
            }

            return [
                'pedido' => $pedidoFresh,
                'resumen' => $resumen,
                'incidencia_id' => null,
            ];
        });
    }
}
