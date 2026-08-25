<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\User;

/**
 * Dual-path: históricos / flag off → CancelarPedidoBmaService;
 * con tareas + flag → cancelación operativa (sin liberar SAF al solicitar).
 */
class RouterCancelacionPedidoBmaService
{
    public function __construct(
        private CancelacionOperativaConfig $config,
        private CancelarPedidoBmaService $cancelarInmediato,
        private SolicitarCancelacionOperativaService $solicitarOperativa,
    ) {}

    public function debeUsarOperativa(PedidoBma $pedido, ?User $usuario = null): bool
    {
        if (! $this->config->activo()) {
            return false;
        }

        if ($usuario && ! $this->config->usuarioHabilitado($usuario)) {
            return false;
        }

        return $pedido->tareasPreparacion()
            ->whereNotIn('estado', [
                \App\Models\ControlPedidos\PedidoBmaTareaPreparacion::ESTADO_CANCELADA,
            ])
            ->exists();
    }

    /**
     * @param  array{motivo: string, comentario?: string|null, resolucion_financiera?: string|null}  $datos
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma|PedidoBmaCancelacionOperativa
    {
        if ($this->debeUsarOperativa($pedido, $usuario)) {
            return $this->solicitarOperativa->ejecutar($pedido, $usuario, $datos);
        }

        return $this->cancelarInmediato->ejecutar($pedido, $usuario->id, $datos);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(PedidoBma $pedido, ?User $usuario = null): array
    {
        $base = $this->cancelarInmediato->preview($pedido);
        $operativa = $this->debeUsarOperativa($pedido, $usuario);
        $activa = $pedido->cancelacionOperativaActiva;

        $ubicaciones = [];
        if ($operativa) {
            $pedido->loadMissing(['tareasPreparacion.almacen', 'tareasPreparacion.modalidad']);
            foreach ($pedido->tareasPreparacion as $tarea) {
                if ($tarea->estado === \App\Models\ControlPedidos\PedidoBmaTareaPreparacion::ESTADO_CANCELADA) {
                    continue;
                }
                $ubicaciones[] = [
                    'tarea_id' => $tarea->id,
                    'estado' => $tarea->estado,
                    'almacen' => $tarea->almacen?->nombre,
                    'area' => $tarea->area_responsable_codigo,
                    'modalidad' => $tarea->modalidad?->nombre,
                ];
            }
        }

        return array_merge($base, [
            'flujo' => $operativa ? 'operativo' : 'inmediato',
            'ubicaciones' => $ubicaciones,
            'cancelacion_operativa' => $activa ? [
                'id' => $activa->id,
                'estado' => $activa->estado,
                'puede_reactivar' => $activa->puedeReactivar(),
                'requiere_resolucion_financiera' => (bool) $activa->requiere_resolucion_financiera,
                'motivo' => $activa->motivo,
            ] : null,
            'productos' => $operativa
                ? [
                    'Se solicita cancelación operativa. SAF y pagos NO se liberan hasta la finalización.',
                    'Cada área con mercancía separada deberá confirmar la liberación física.',
                    'Puede reactivar el pedido mientras ninguna tarea haya liberado piezas.',
                    'La cancelación será definitiva después de la liberación.',
                ]
                : $base['productos'],
        ]);
    }
}
