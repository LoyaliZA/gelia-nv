<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaCancelacionOperativa;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ResolverFinancieroCancelacionService
{
    public function __construct(
        private FinalizarCancelacionOperativaService $finalizarService,
    ) {}

    /**
     * @param  array{resolucion_financiera: string, version?: int|null}  $datos
     */
    public function ejecutar(
        PedidoBmaCancelacionOperativa $cancelacion,
        User $usuario,
        array $datos,
    ): PedidoBmaCancelacionOperativa {
        if (! $usuario->can('control_pedidos.cancelacion_operativa.resolver_financiera')) {
            throw new \RuntimeException('No tiene permiso de resolución financiera.');
        }

        $resolucion = (string) ($datos['resolucion_financiera'] ?? '');
        $permitidas = [
            CancelarPedidoBmaService::RESOLUCION_NINGUNA,
            CancelarPedidoBmaService::RESOLUCION_PENDIENTE,
            'reembolso_manual',
            'saldo_a_favor',
            'sin_movimiento',
        ];

        if (! in_array($resolucion, $permitidas, true)
            || $resolucion === CancelarPedidoBmaService::RESOLUCION_PENDIENTE) {
            throw ValidationException::withMessages([
                'resolucion_financiera' => 'Seleccione una resolución financiera válida (no pendiente).',
            ]);
        }

        if (! $cancelacion->requiere_resolucion_financiera
            && $cancelacion->estado !== PedidoBmaCancelacionOperativa::ESTADO_LIBERADA) {
            throw ValidationException::withMessages([
                'estado' => 'Esta cancelación no requiere resolución financiera en este momento.',
            ]);
        }

        $cancelacion->update([
            'resolucion_financiera' => $resolucion,
            'requiere_resolucion_financiera' => false,
            'version' => $cancelacion->version + 1,
        ]);

        return $this->finalizarService->intentar($cancelacion->fresh(), $usuario);
    }
}
