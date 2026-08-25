<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;

/**
 * Matriz financiera: sin pagos/SAF → auto; solo SAF → liberar al final;
 * pagos > 0 → bloqueo hasta resolutor. No inventa devoluciones.
 */
class MatrizResolucionFinancieraCancelacionService
{
    public const RESOLUCION_NINGUNA = CancelarPedidoBmaService::RESOLUCION_NINGUNA;

    public const RESOLUCION_PENDIENTE = CancelarPedidoBmaService::RESOLUCION_PENDIENTE;

    /**
     * @return array{puede_auto: bool, requiere_resolutor: bool, resolucion: string, motivo: string}
     */
    public function evaluar(PedidoBma $pedido, ?string $resolucionRegistrada = null): array
    {
        $pedido->loadMissing(['pagosExhibicion', 'safAplicaciones']);

        $totalPagos = (float) $pedido->pagosExhibicion->sum('monto');
        $saf = (float) $pedido->safAplicaciones
            ->where('estado', '!=', 'liberado')
            ->sum('monto');

        if ($totalPagos > 0.01) {
            if ($resolucionRegistrada && $resolucionRegistrada !== ''
                && $resolucionRegistrada !== self::RESOLUCION_PENDIENTE) {
                return [
                    'puede_auto' => true,
                    'requiere_resolutor' => false,
                    'resolucion' => $resolucionRegistrada,
                    'motivo' => 'Resolución financiera registrada por resolutor.',
                ];
            }

            return [
                'puede_auto' => false,
                'requiere_resolutor' => true,
                'resolucion' => self::RESOLUCION_PENDIENTE,
                'motivo' => 'Hay pagos registrados. Requiere resolución financiera (no se inventan devoluciones).',
            ];
        }

        if ($saf > 0.01) {
            return [
                'puede_auto' => true,
                'requiere_resolutor' => false,
                'resolucion' => self::RESOLUCION_NINGUNA,
                'motivo' => 'Sin exhibiciones de pago; se liberará SAF al finalizar.',
            ];
        }

        return [
            'puede_auto' => true,
            'requiere_resolutor' => false,
            'resolucion' => self::RESOLUCION_NINGUNA,
            'motivo' => 'Sin pagos ni SAF pendiente.',
        ];
    }
}
