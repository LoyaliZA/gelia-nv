<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaAlertaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Vencimiento de espera de pago → solicitud de liberación (sin tocar inventario).
 */
class SolicitarLiberacionPorVencimientoEsperaService
{
    public function __construct(
        private CancelacionOperativaConfig $config,
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @return array{procesadas: int, alertas: int}
     */
    public function ejecutar(): array
    {
        $zona = $this->config->zonaHoraria();
        $ahora = Carbon::now($zona);
        $procesadas = 0;
        $alertas = 0;

        $tareas = PedidoBmaTareaPreparacion::query()
            ->with(['pedido.cliente', 'pedido.vendedor', 'pedido.estatus'])
            ->whereNotNull('espera_pago_at')
            ->whereNotNull('fecha_limite')
            ->where('fecha_limite', '<=', $ahora)
            ->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            ])
            ->get();

        foreach ($tareas as $tarea) {
            $ventana = $tarea->fecha_limite->timezone($zona)->format('Y-m-d');
            $clave = "tarea:{$tarea->id}:vencimiento_espera:{$ventana}";

            if (PedidoBmaAlertaPreparacion::query()->where('clave_unica', $clave)->exists()) {
                continue;
            }

            DB::transaction(function () use ($tarea, $clave, $ventana, &$procesadas, &$alertas) {
                $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->find($tarea->id);
                if (! $tarea || ! in_array($tarea->estado, [
                    PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                    PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                ], true)) {
                    return;
                }

                $usuarioId = $tarea->pedido?->vendedor_id ?? $tarea->solicitada_por_id ?? 1;

                $this->transicionService->ejecutar(
                    $tarea,
                    PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
                    (int) $usuarioId,
                    'vencimiento_espera_pago',
                    'Venció la espera de pago. Solicitud de liberación (sin cambio de inventario).',
                    ['clave_alerta' => $clave],
                    null,
                    null,
                    true
                );

                $pedido = PedidoBma::query()->find($tarea->pedido_bma_id);
                if ($pedido && $pedido->esperando_pago_at) {
                    $pedido->update(['esperando_pago_at' => null]);
                }

                PedidoBmaAlertaPreparacion::query()->create([
                    'clave_unica' => $clave,
                    'pedido_bma_id' => $tarea->pedido_bma_id,
                    'pedido_bma_tarea_preparacion_id' => $tarea->id,
                    'tipo' => 'vencimiento_espera',
                    'ventana' => $ventana,
                    'destinatarios' => ['control_pedidos.tienda.liberar', 'control_pedidos.cedis.liberar'],
                    'ejecutada_at' => now(),
                ]);

                if ($pedido) {
                    $this->historialService->ejecutar(
                        $pedido->id,
                        (int) $usuarioId,
                        $pedido->catalogo_estatus_pedido_id,
                        $pedido->catalogo_estatus_pedido_id,
                        'Venció espera de pago; se solicitó liberación física.',
                        AccionesHistorialPedidoBma::VENCIMIENTO_ESPERA_PAGO
                    );

                    $this->notificarService->ejecutar(
                        $pedido,
                        'pedido_espera_pago_vencida',
                        'Venció la espera de pago. Liberación solicitada.',
                        ['control_pedidos.tienda.liberar', 'control_pedidos.cedis.liberar'],
                        null,
                        true,
                        ['url' => '/control-pedidos/tienda?tab=liberacion']
                    );
                }

                $procesadas++;
                $alertas++;
            });
        }

        // Aviso cercano al vencimiento (idempotente por ventana).
        $horas = $this->config->anticipacionAvisoHoras();
        $limiteCercano = $ahora->copy()->addHours($horas);
        $tareasCercanas = PedidoBmaTareaPreparacion::query()
            ->with(['pedido'])
            ->whereNotNull('espera_pago_at')
            ->whereNotNull('fecha_limite')
            ->where('fecha_limite', '>', $ahora)
            ->where('fecha_limite', '<=', $limiteCercano)
            ->whereIn('estado', [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
            ])
            ->get();

        foreach ($tareasCercanas as $tarea) {
            $ventana = 'aviso_'.$tarea->fecha_limite->timezone($zona)->format('Y-m-d-H');
            $clave = "tarea:{$tarea->id}:aviso_vencimiento:{$ventana}";
            if (PedidoBmaAlertaPreparacion::query()->where('clave_unica', $clave)->exists()) {
                continue;
            }

            $pedido = $tarea->pedido;
            if (! $pedido) {
                continue;
            }

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_espera_pago_aviso',
                'La espera de pago está por vencer.',
                [],
                null,
                true,
                ['url' => '/control-pedidos']
            );

            PedidoBmaAlertaPreparacion::query()->create([
                'clave_unica' => $clave,
                'pedido_bma_id' => $pedido->id,
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'tipo' => 'aviso_vencimiento',
                'ventana' => $ventana,
                'destinatarios' => ['vendedor'],
                'ejecutada_at' => now(),
            ]);
            $alertas++;
        }

        return ['procesadas' => $procesadas, 'alertas' => $alertas];
    }
}
