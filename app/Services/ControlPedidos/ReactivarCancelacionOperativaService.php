<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativaTarea;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReactivarCancelacionOperativaService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  array{motivo: string, folio_nuevo?: string|null, version?: int|null}  $datos
     */
    public function ejecutar(
        PedidoBmaCancelacionOperativa $cancelacion,
        User $usuario,
        array $datos,
    ): PedidoBmaCancelacionOperativa {
        if (! $usuario->can('control_pedidos.cancelacion_operativa.reactivar')
            && ! $usuario->can('control_pedidos.cancelar')) {
            throw new \RuntimeException('No tiene permiso para reactivar el pedido.');
        }

        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indique el motivo de reactivación.',
            ]);
        }

        return DB::transaction(function () use ($cancelacion, $usuario, $datos, $motivo) {
            $cancelacion = PedidoBmaCancelacionOperativa::query()
                ->lockForUpdate()
                ->findOrFail($cancelacion->id);

            if (isset($datos['version']) && (int) $cancelacion->version !== (int) $datos['version']) {
                throw new ConflictHttpException(
                    'La cancelación cambió (posible liberación concurrente). Actualice e intente de nuevo.'
                );
            }

            if (! $cancelacion->estaActiva()) {
                throw ValidationException::withMessages([
                    'estado' => 'Esta cancelación ya no está activa.',
                ]);
            }

            $cancelacion->load(['tareas.tarea', 'pedido']);

            $yaLiberada = $cancelacion->tareas->contains(
                fn (PedidoBmaCancelacionOperativaTarea $t) => $t->estado_liberacion
                    === PedidoBmaCancelacionOperativaTarea::LIBERACION_LIBERADA
            );

            if ($yaLiberada) {
                throw ValidationException::withMessages([
                    'liberacion' => 'No se puede reactivar: ya hay mercancía liberada. Debe iniciar una consulta nueva.',
                ]);
            }

            // Verificar tareas físicas no liberadas en paralelo.
            foreach ($cancelacion->tareas as $fila) {
                $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->find($fila->pedido_bma_tarea_preparacion_id);
                if ($tarea && $tarea->estado === PedidoBmaTareaPreparacion::ESTADO_LIBERADA) {
                    throw new ConflictHttpException(
                        'Una tarea ya fue liberada. No se puede reactivar.'
                    );
                }
            }

            $folioNuevo = trim((string) ($datos['folio_nuevo'] ?? ''));

            foreach ($cancelacion->tareas as $fila) {
                if ($fila->estado_liberacion === PedidoBmaCancelacionOperativaTarea::LIBERACION_NO_SEPARADA) {
                    continue;
                }

                $tarea = PedidoBmaTareaPreparacion::query()->find($fila->pedido_bma_tarea_preparacion_id);
                if (! $tarea) {
                    continue;
                }

                $destino = $fila->estado_previo_liberacion
                    ?: PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA;

                // Solo restaurar desde LIBERACION_SOLICITADA.
                if ($tarea->estado === PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA) {
                    $destinoSeguro = in_array($destino, [
                        PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                        PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                    ], true) ? $destino : PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA;

                    // Máquina solo permite LIBERACION_SOLICITADA → RESPONDIDA.
                    $this->transicionService->ejecutar(
                        $tarea,
                        PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                        $usuario->id,
                        'reactivar_cancelacion',
                        'Cancelación revertida; ya no liberar. Estado previo: '.$destinoSeguro,
                        ['cancelacion_operativa_id' => $cancelacion->id],
                        null,
                        $usuario,
                        true
                    );
                }
            }

            $pedido = $cancelacion->pedido;
            $cancelacion->update([
                'estado' => PedidoBmaCancelacionOperativa::ESTADO_REVERTIDA,
                'revertida_por_id' => $usuario->id,
                'revertida_at' => now(),
                'motivo_reactivacion' => $motivo,
                'folio_nuevo' => $folioNuevo !== '' ? $folioNuevo : null,
                'version' => $cancelacion->version + 1,
            ]);

            if ($folioNuevo !== '') {
                $pedido->update(['folio_remision' => $folioNuevo]);
            }

            $detalle = sprintf(
                'Pedido reactivado. Motivo: %s. Folio anterior: %s.%s',
                $motivo,
                $cancelacion->folio_anterior ?? '—',
                $folioNuevo !== '' ? ' Folio nuevo: '.$folioNuevo : ''
            );

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedido->catalogo_estatus_pedido_id,
                $detalle,
                AccionesHistorialPedidoBma::REACTIVACION_CANCELACION
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(),
                'pedido_cancelacion_reactivada',
                'El pedido fue reactivado. Ya no deben liberar la mercancía.',
                ['control_pedidos.tienda.liberar', 'control_pedidos.cedis.liberar'],
                $usuario->id,
                true,
                ['url' => '/control-pedidos/tienda']
            );

            return $cancelacion->fresh(['tareas.tarea', 'pedido']);
        });
    }
}
