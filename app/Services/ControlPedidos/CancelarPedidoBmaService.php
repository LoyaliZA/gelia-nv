<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use Illuminate\Support\Facades\DB;

class CancelarPedidoBmaService
{
    public const MOTIVOS = [
        'cliente_desiste' => 'Cliente desiste',
        'error_captura' => 'Error de captura',
        'sin_stock' => 'Sin stock / no se puede surtir',
        'pago_no_concluye' => 'Pago no concluye',
        'cambio_pedido' => 'Se arma otro pedido',
        'otro' => 'Otro',
    ];

    public const RESOLUCION_NINGUNA = 'ninguna';

    public const RESOLUCION_PENDIENTE = 'pendiente_resolucion';

    public function __construct(
        private RegistrarHistorialPedidoService $historialService,
        private SincronizarAplicacionesPedidoSafService $safPedido,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  array{
     *   motivo: string,
     *   comentario?: string|null,
     *   resolucion_financiera?: string|null
     * }  $datos
     */
    public function ejecutar(PedidoBma $pedido, int $usuarioId, array $datos): PedidoBma
    {
        $pedido->loadMissing(['estatus', 'pagosExhibicion', 'documentos']);

        if ($pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_CANCELADO || $pedido->cancelado_at) {
            return $pedido;
        }

        if (! $pedido->puedeCancelarDirecto()) {
            throw new \RuntimeException(
                'Este pedido ya pasó un hito irreversible (guía/envío). La cancelación requiere autorización y queda fuera de esta acción.'
            );
        }

        $motivo = (string) ($datos['motivo'] ?? '');
        if (! isset(self::MOTIVOS[$motivo])) {
            throw new \InvalidArgumentException('Seleccione un motivo de cancelación válido.');
        }

        $comentario = trim((string) ($datos['comentario'] ?? ''));
        if ($motivo === 'otro' && $comentario === '') {
            throw new \InvalidArgumentException('El motivo «Otro» requiere comentario.');
        }

        $totalPagos = (float) $pedido->pagosExhibicion->sum('monto');
        $resolucion = (string) ($datos['resolucion_financiera'] ?? '');
        if ($totalPagos > 0.01) {
            if ($resolucion === '') {
                $resolucion = self::RESOLUCION_PENDIENTE;
            }
            if (! in_array($resolucion, [self::RESOLUCION_PENDIENTE, self::RESOLUCION_NINGUNA], true)) {
                $resolucion = self::RESOLUCION_PENDIENTE;
            }
        } else {
            $resolucion = self::RESOLUCION_NINGUNA;
        }

        $estatusCancelado = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_CANCELADO)
            ?? CatalogoEstatusPedido::porCodigo('CANCELADO');
        if (! $estatusCancelado) {
            throw new \RuntimeException('No existe el estatus CANCELADO en el catálogo.');
        }

        return DB::transaction(function () use (
            $pedido, $usuarioId, $motivo, $comentario, $resolucion, $estatusCancelado, $totalPagos
        ) {
            // Releer bajo lock lógico de negocio: si otro proceso canceló, salir.
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->loadMissing('estatus');
            if ($pedido->cancelado_at || $pedido->estatus?->fase_ciclo === CatalogoEstatusPedido::FASE_CANCELADO) {
                return $pedido;
            }

            $estatusAnteriorId = $pedido->catalogo_estatus_pedido_id;
            $faseAnterior = $pedido->estatus?->fase_ciclo;
            MaquinaEstadosPedidoBma::assertTransicion(
                $faseAnterior,
                CatalogoEstatusPedido::FASE_CANCELADO
            );

            $this->safPedido->liberarReservasPendientes($pedido, $usuarioId);

            // Liberar apartado de resguardo (sin borrar evidencias).
            if ($pedido->es_resguardo && $pedido->resguardo_apartado_at) {
                $pedido->resguardo_apartado_at = null;
                $pedido->resguardo_apartado_por_id = null;
            }

            $pedido->fill([
                'catalogo_estatus_pedido_id' => $estatusCancelado->id,
                'motivo_cancelacion' => $motivo,
                'comentario_cancelacion' => $comentario !== '' ? $comentario : null,
                'resolucion_financiera_cancelacion' => $resolucion,
                'cancelado_por_id' => $usuarioId,
                'cancelado_at' => now(),
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_COMPLETO,
            ]);
            $pedido->save();

            $detalle = sprintf(
                'Pedido cancelado. Motivo: %s. Fase anterior: %s. Pagos registrados: $%s. Resolución financiera: %s.%s',
                self::MOTIVOS[$motivo],
                $faseAnterior ?? '—',
                number_format($totalPagos, 2, '.', ''),
                $resolucion,
                $comentario !== '' ? ' Comentario: '.$comentario : ''
            );

            $this->historialService->ejecutar(
                $pedido->id,
                $usuarioId,
                $estatusAnteriorId,
                $estatusCancelado->id,
                $detalle,
                AccionesHistorialPedidoBma::CANCELACION
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_cancelado',
                'Pedido cancelado',
                [],
                $usuarioId,
                false,
                ['url' => '/control-pedidos']
            );

            return $pedido->fresh(['cliente', 'estatus', 'canceladoPor', 'pagosExhibicion']);
        });
    }

    /**
     * Preview de efectos para la UI de confirmación.
     *
     * @return array{puede:bool, motivo_bloqueo:?string, productos:list<string>, total_pagos:float, saf_aplicado:float, es_resguardo:bool, tiene_apartado:bool, fase:?string}
     */
    public function preview(PedidoBma $pedido): array
    {
        $pedido->loadMissing(['estatus', 'pagosExhibicion', 'safAplicaciones']);

        if (! $pedido->puedeCancelarDirecto()) {
            return [
                'puede' => false,
                'motivo_bloqueo' => 'El pedido ya pasó un hito (guía/envío). Requiere autorización.',
                'productos' => [],
                'total_pagos' => 0.0,
                'saf_aplicado' => 0.0,
                'es_resguardo' => (bool) $pedido->es_resguardo,
                'tiene_apartado' => (bool) $pedido->resguardo_apartado_at,
                'fase' => $pedido->estatus?->fase_ciclo,
            ];
        }

        $saf = (float) $pedido->safAplicaciones
            ->where('estado', '!=', 'liberado')
            ->sum('monto');

        return [
            'puede' => true,
            'motivo_bloqueo' => null,
            'productos' => [
                'Se liberarán reservas de saldo a favor pendientes.',
                $pedido->es_resguardo ? 'Se liberará el apartado de resguardo (si aplica).' : 'Pedido normal: no hay apartado CEDIS.',
                'El pedido permanecerá consultable como Cancelado.',
            ],
            'total_pagos' => (float) $pedido->pagosExhibicion->sum('monto'),
            'saf_aplicado' => $saf,
            'es_resguardo' => (bool) $pedido->es_resguardo,
            'tiene_apartado' => (bool) $pedido->resguardo_apartado_at,
            'fase' => $pedido->estatus?->fase_ciclo,
        ];
    }
}
