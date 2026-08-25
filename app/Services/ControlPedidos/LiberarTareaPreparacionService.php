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

class LiberarTareaPreparacionService
{
    public function __construct(
        private TransicionEstadoTareaPreparacionService $transicionService,
        private FinalizarCancelacionOperativaService $finalizarService,
        private CancelacionOperativaConfig $config,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  array{
     *   motivo?: string|null,
     *   version?: int|null,
     *   cantidad_liberada?: int|null,
     *   incidencia?: string|null,
     *   evidencia_meta?: array|null,
     *   area?: string|null
     * }  $datos
     */
    public function ejecutar(
        PedidoBmaTareaPreparacion $tarea,
        User $usuario,
        ?string $motivo = null,
        ?int $versionEsperada = null,
        array $datos = [],
    ): PedidoBmaTareaPreparacion {
        $area = strtoupper((string) ($datos['area'] ?? $tarea->area_responsable_codigo ?? 'TIENDA'));
        $permiso = $area === 'CEDIS'
            ? 'control_pedidos.cedis.liberar'
            : 'control_pedidos.tienda.liberar';

        if (! $usuario->can($permiso) && ! $usuario->can('control_pedidos.tienda.liberar')) {
            throw new \RuntimeException('No tiene permiso para liberar mercancía.');
        }

        if ($this->config->evidenciaLiberarObligatoria()
            && empty($datos['evidencia_meta'])
            && empty($datos['incidencia'])) {
            throw ValidationException::withMessages([
                'evidencia' => 'La evidencia de liberación es obligatoria según la configuración.',
            ]);
        }

        return DB::transaction(function () use ($tarea, $usuario, $motivo, $versionEsperada, $datos) {
            $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);

            $filaOp = PedidoBmaCancelacionOperativaTarea::query()
                ->where('pedido_bma_tarea_preparacion_id', $tarea->id)
                ->whereHas('cancelacion', fn ($q) => $q->whereIn(
                    'estado',
                    PedidoBmaCancelacionOperativa::ESTADOS_ACTIVOS
                ))
                ->lockForUpdate()
                ->first();

            if ($filaOp) {
                $cancelacion = PedidoBmaCancelacionOperativa::query()
                    ->lockForUpdate()
                    ->findOrFail($filaOp->pedido_bma_cancelacion_operativa_id);

                if ($cancelacion->estado === PedidoBmaCancelacionOperativa::ESTADO_REVERTIDA) {
                    throw new ConflictHttpException(
                        'El pedido fue reactivado. Ya no debe liberar esta mercancía. Actualice la página.'
                    );
                }

                if ($filaOp->estado_liberacion === PedidoBmaCancelacionOperativaTarea::LIBERACION_LIBERADA) {
                    return $tarea->fresh(['modalidad', 'almacen', 'productos']);
                }
            }

            if (! in_array($tarea->estado, [
                PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA,
                PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
                PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
            ], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo puede liberar tareas respondidas o con liberación solicitada.',
                ]);
            }

            $incidencia = trim((string) ($datos['incidencia'] ?? ''));
            $cantidadLiberada = isset($datos['cantidad_liberada'])
                ? (int) $datos['cantidad_liberada']
                : null;

            $tarea = $this->transicionService->ejecutar(
                $tarea,
                PedidoBmaTareaPreparacion::ESTADO_LIBERADA,
                $usuario->id,
                'liberar',
                $motivo ?: ($incidencia !== '' ? $incidencia : 'Mercancía liberada. Ya devolví estas piezas a disponibilidad.'),
                [
                    'cantidad_liberada' => $cantidadLiberada,
                    'incidencia' => $incidencia !== '' ? $incidencia : null,
                    'evidencia_meta' => $datos['evidencia_meta'] ?? null,
                ],
                $versionEsperada ?? ($datos['version'] ?? null),
                $usuario,
                // Permiso de área ya validado arriba (tienda.liberar | cedis.liberar).
                true
            );

            $pedido = $tarea->pedido()->with(['cliente', 'vendedor', 'estatus'])->first();
            $pedido->update(['es_resguardo' => false, 'esperando_pago_at' => null]);

            if ($filaOp) {
                $estadoLib = $incidencia !== ''
                    ? PedidoBmaCancelacionOperativaTarea::LIBERACION_INCIDENCIA
                    : PedidoBmaCancelacionOperativaTarea::LIBERACION_LIBERADA;

                // Incidencia con faltante documentado aún cuenta como resuelta físicamente
                // cuando confirman devolución; si reportan incidencia sin liberar, dejamos INCIDENCIA
                // y tratamos INCIDENCIA documentada + confirmación como resuelta vía LIBERADA.
                if ($estadoLib === PedidoBmaCancelacionOperativaTarea::LIBERACION_INCIDENCIA) {
                    $estadoLib = PedidoBmaCancelacionOperativaTarea::LIBERACION_LIBERADA;
                }

                $filaOp->update([
                    'estado_liberacion' => $estadoLib,
                    'cantidad_liberada' => $cantidadLiberada,
                    'incidencia' => $incidencia !== '' ? $incidencia : null,
                    'evidencia_meta' => $datos['evidencia_meta'] ?? null,
                    'liberada_por_id' => $usuario->id,
                    'liberada_at' => now(),
                    'version' => $filaOp->version + 1,
                ]);

                $accionHist = $incidencia !== ''
                    ? AccionesHistorialPedidoBma::INCIDENCIA_LIBERACION_CANCELACION
                    : AccionesHistorialPedidoBma::LIBERACION_CANCELACION_TAREA;

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->estatus->id,
                    $pedido->estatus->id,
                    $incidencia !== ''
                        ? 'Liberación con incidencia: '.$incidencia
                        : 'Mercancía liberada por cancelación operativa.',
                    $accionHist
                );

                $cancelacion = PedidoBmaCancelacionOperativa::query()
                    ->with('tareas')
                    ->find($filaOp->pedido_bma_cancelacion_operativa_id);

                if ($cancelacion && $cancelacion->tareas->every(fn ($t) => $t->estaResuelta())) {
                    $cancelacion->update([
                        'estado' => PedidoBmaCancelacionOperativa::ESTADO_LIBERADA,
                        'liberada_por_id' => $usuario->id,
                        'liberada_at' => now(),
                        'version' => $cancelacion->version + 1,
                    ]);
                    $this->finalizarService->intentar($cancelacion->fresh(), $usuario);
                }
            } else {
                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->estatus->id,
                    $pedido->estatus->id,
                    'Mercancía liberada en Tienda.',
                    AccionesHistorialPedidoBma::LIBERACION_PREPARACION_TIENDA
                );
            }

            $this->notificarService->ejecutar(
                $pedido,
                'pedido_preparacion_tienda_liberada',
                'La mercancía resguardada fue liberada.',
                [],
                $usuario->id,
                true
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos']);
        });
    }
}
