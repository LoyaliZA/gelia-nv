<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativaTarea;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizarCancelacionOperativaService
{
    public function __construct(
        private MatrizResolucionFinancieraCancelacionService $matriz,
        private CancelarPedidoBmaService $cancelarService,
        private CancelacionOperativaConfig $config,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    public function intentar(
        PedidoBmaCancelacionOperativa $cancelacion,
        User $usuario,
        bool $forzarAdmin = false,
    ): PedidoBmaCancelacionOperativa {
        return DB::transaction(function () use ($cancelacion, $usuario, $forzarAdmin) {
            $cancelacion = PedidoBmaCancelacionOperativa::query()
                ->lockForUpdate()
                ->findOrFail($cancelacion->id);

            if ($cancelacion->estado === PedidoBmaCancelacionOperativa::ESTADO_FINALIZADA) {
                return $cancelacion;
            }

            if ($cancelacion->estado === PedidoBmaCancelacionOperativa::ESTADO_REVERTIDA) {
                throw ValidationException::withMessages([
                    'estado' => 'La cancelación fue revertida; no se puede finalizar.',
                ]);
            }

            $cancelacion->load(['tareas', 'pedido.pagosExhibicion', 'pedido.safAplicaciones', 'pedido.estatus']);

            $pendientes = $cancelacion->tareas->filter(
                fn (PedidoBmaCancelacionOperativaTarea $t) => ! $t->estaResuelta()
            );

            if ($pendientes->isNotEmpty() && ! $forzarAdmin) {
                throw ValidationException::withMessages([
                    'liberacion' => 'Aún hay liberaciones físicas pendientes.',
                ]);
            }

            if ($forzarAdmin && ! $usuario->can('control_pedidos.cancelacion_operativa.concluir_admin')) {
                throw new \RuntimeException('No tiene permiso para concluir administrativamente.');
            }

            $pedido = $cancelacion->pedido;
            $eval = $this->matriz->evaluar($pedido, $cancelacion->resolucion_financiera);

            if (! $eval['puede_auto'] && ! $forzarAdmin) {
                $cancelacion->update([
                    'estado' => PedidoBmaCancelacionOperativa::ESTADO_LIBERADA,
                    'liberada_por_id' => $usuario->id,
                    'liberada_at' => $cancelacion->liberada_at ?? now(),
                    'requiere_resolucion_financiera' => true,
                    'resolucion_financiera' => $eval['resolucion'],
                    'version' => $cancelacion->version + 1,
                ]);

                $this->historialService->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $pedido->catalogo_estatus_pedido_id,
                    $pedido->catalogo_estatus_pedido_id,
                    'Requiere resolución financiera: '.$eval['motivo'],
                    AccionesHistorialPedidoBma::BLOQUEO_FINANCIERO_CANCELACION
                );

                $this->notificarService->ejecutar(
                    $pedido,
                    'pedido_cancelacion_bloqueo_financiero',
                    'Cancelación operativa requiere resolución financiera.',
                    [$this->config->rolResolutorFinanciero()],
                    $usuario->id,
                    true,
                    ['url' => '/control-pedidos']
                );

                return $cancelacion->fresh(['tareas.tarea']);
            }

            if (! $pedido->cancelado_at) {
                $this->cancelarService->ejecutar($pedido, $usuario->id, [
                    'motivo' => $cancelacion->motivo,
                    'comentario' => $cancelacion->comentario,
                    'resolucion_financiera' => $eval['resolucion'],
                ]);
            }

            $cancelacion->update([
                'estado' => PedidoBmaCancelacionOperativa::ESTADO_FINALIZADA,
                'finalizada_por_id' => $usuario->id,
                'finalizada_at' => now(),
                'liberada_por_id' => $cancelacion->liberada_por_id ?? $usuario->id,
                'liberada_at' => $cancelacion->liberada_at ?? now(),
                'requiere_resolucion_financiera' => false,
                'resolucion_financiera' => $eval['resolucion'],
                'version' => $cancelacion->version + 1,
            ]);

            $pedidoFresh = $pedido->fresh();
            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $pedido->catalogo_estatus_pedido_id,
                $pedidoFresh->catalogo_estatus_pedido_id,
                'Cancelación operativa finalizada. Resolución: '.$eval['resolucion'],
                AccionesHistorialPedidoBma::FINALIZACION_CANCELACION_OPERATIVA
            );

            $this->notificarService->ejecutar(
                $pedidoFresh,
                'pedido_cancelacion_operativa_finalizada',
                'Cancelación operativa finalizada.',
                [],
                $usuario->id,
                false,
                ['url' => '/control-pedidos']
            );

            return $cancelacion->fresh(['tareas.tarea', 'pedido.estatus']);
        });
    }
}
