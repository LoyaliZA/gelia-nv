<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativaTarea;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SolicitarCancelacionOperativaService
{
    /** Estados con mercancía potencialmente separada. */
    private const ESTADOS_SEPARADA = [
        PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
        PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
        PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO,
        PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
        PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
        PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
    ];

    public function __construct(
        private CancelacionOperativaConfig $config,
        private TransicionEstadoTareaPreparacionService $transicionService,
        private FinalizarCancelacionOperativaService $finalizarService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  array{motivo: string, comentario?: string|null}  $datos
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBmaCancelacionOperativa
    {
        if (! $usuario->can('control_pedidos.cancelacion_operativa.solicitar')
            && ! $usuario->can('control_pedidos.cancelar')) {
            throw new \RuntimeException('No tiene permiso para solicitar cancelación operativa.');
        }

        $motivo = (string) ($datos['motivo'] ?? '');
        if (! isset(CancelarPedidoBmaService::MOTIVOS[$motivo])) {
            throw new \InvalidArgumentException('Seleccione un motivo de cancelación válido.');
        }

        $comentario = trim((string) ($datos['comentario'] ?? ''));
        if ($motivo === 'otro' && $comentario === '') {
            throw new \InvalidArgumentException('El motivo «Otro» requiere comentario.');
        }

        if (! $pedido->puedeCancelarDirecto()) {
            throw new \RuntimeException(
                'Este pedido ya pasó un hito irreversible (guía/envío). La cancelación requiere autorización.'
            );
        }

        return DB::transaction(function () use ($pedido, $usuario, $motivo, $comentario) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            $existente = PedidoBmaCancelacionOperativa::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereIn('estado', PedidoBmaCancelacionOperativa::ESTADOS_ACTIVOS)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente->load(['tareas.tarea.almacen']);
            }

            if ($pedido->cancelado_at) {
                throw ValidationException::withMessages([
                    'estado' => 'El pedido ya está cancelado.',
                ]);
            }

            $tareas = PedidoBmaTareaPreparacion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->whereNotIn('estado', [PedidoBmaTareaPreparacion::ESTADO_CANCELADA])
                ->lockForUpdate()
                ->get();

            $cancelacion = PedidoBmaCancelacionOperativa::query()->create([
                'pedido_bma_id' => $pedido->id,
                'estado' => PedidoBmaCancelacionOperativa::ESTADO_SOLICITADA,
                'motivo' => $motivo,
                'comentario' => $comentario !== '' ? $comentario : null,
                'solicitada_por_id' => $usuario->id,
                'solicitada_at' => now(),
                'folio_anterior' => $pedido->folio_remision ?: $pedido->folio,
                'version' => 1,
            ]);

            $pedido->update(['esperando_pago_at' => null]);

            $hayPendienteFisica = false;

            foreach ($tareas as $tarea) {
                if (! in_array($tarea->estado, self::ESTADOS_SEPARADA, true)) {
                    PedidoBmaCancelacionOperativaTarea::query()->create([
                        'pedido_bma_cancelacion_operativa_id' => $cancelacion->id,
                        'pedido_bma_tarea_preparacion_id' => $tarea->id,
                        'estado_liberacion' => PedidoBmaCancelacionOperativaTarea::LIBERACION_NO_SEPARADA,
                        'estado_previo_liberacion' => $tarea->estado,
                        'liberada_at' => now(),
                        'liberada_por_id' => $usuario->id,
                    ]);
                    continue;
                }

                $estadoPrevio = $tarea->estado;
                $cantidad = (int) $tarea->productos()->sum('cantidad_encontrada')
                    ?: (int) $tarea->productos()->sum('cantidad_solicitada');

                PedidoBmaCancelacionOperativaTarea::query()->create([
                    'pedido_bma_cancelacion_operativa_id' => $cancelacion->id,
                    'pedido_bma_tarea_preparacion_id' => $tarea->id,
                    'estado_liberacion' => PedidoBmaCancelacionOperativaTarea::LIBERACION_PENDIENTE,
                    'estado_previo_liberacion' => $estadoPrevio,
                    'cantidad_a_liberar' => $cantidad > 0 ? $cantidad : null,
                ]);

                if ($tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA) {
                    $this->transicionService->ejecutar(
                        $tarea,
                        PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
                        $usuario->id,
                        'solicitar_liberacion_cancelacion',
                        'Liberar mercancía por cancelación operativa.',
                        ['cancelacion_operativa_id' => $cancelacion->id],
                        null,
                        $usuario,
                        true
                    );
                }

                $hayPendienteFisica = true;
            }

            $cancelacion->update([
                'estado' => $hayPendienteFisica
                    ? PedidoBmaCancelacionOperativa::ESTADO_LIBERACION_PENDIENTE
                    : PedidoBmaCancelacionOperativa::ESTADO_LIBERADA,
                'liberacion_solicitada_por_id' => $usuario->id,
                'liberacion_solicitada_at' => now(),
                'version' => $cancelacion->version + 1,
            ]);

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                sprintf(
                    'Cancelación operativa solicitada. Motivo: %s. %s',
                    CancelarPedidoBmaService::MOTIVOS[$motivo],
                    $hayPendienteFisica
                        ? 'Pendiente de liberación física.'
                        : 'Sin mercancía separada; se intenta finalizar.'
                ),
                AccionesHistorialPedidoBma::CANCELACION_OPERATIVA_SOLICITADA
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_cancelacion_operativa',
                'Se solicitó cancelación. Confirme liberación de mercancía si aplica.',
                ['control_pedidos.tienda.liberar', 'control_pedidos.cedis.liberar'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos/tienda?tab=liberacion']
            );

            $cancelacion = $cancelacion->fresh(['tareas.tarea.almacen']);

            if (! $hayPendienteFisica) {
                return $this->finalizarService->intentar($cancelacion, $usuario);
            }

            return $cancelacion;
        });
    }
}
