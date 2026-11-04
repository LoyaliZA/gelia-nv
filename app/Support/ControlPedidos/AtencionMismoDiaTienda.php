<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use RuntimeException;

/** Estado derivado del reporte original y la validación, sin recalcular el corte por la hora actual. */
final class AtencionMismoDiaTienda
{
    public static function datos(PedidoBma $pedido): ?array
    {
        $json = $pedido->solicitud_preparacion_json ?? [];
        if (($json['atencion_tienda'] ?? null) !== 'prevista' || ($json['cobertura_tienda']['tipo'] ?? null) !== 'transferencia') return null;
        $reporte = $json['tienda_md_reporte'] ?? [];
        $oportuno = ($reporte['estado'] ?? null) === 'oportuno' && ($reporte['oportuno'] ?? false) === true;
        $confirmada = $oportuno && $pedido->pago_validado_at !== null && $pedido->tieneRemision() && filled($pedido->numero_remision);
        return [
            'ficha' => 'T-02.1', 'cobertura' => 'transferencia', 'intencion' => 'prevista',
            'confirmada' => $confirmada, 'reporte' => $reporte['estado'] ?? null,
            'primer_pago_at' => $reporte['primer_pago_at'] ?? null,
            'motivo' => $confirmada
                ? 'Reporte oportuno, pago validado y remisión registrada. Falta completar el traslado y la entrega.'
                : (($reporte['estado'] ?? null) === 'tardio'
                    ? 'El reporte llegó fuera del corte. Falta acordar cómo continuar; no hay reprogramación automática.'
                    : ($oportuno ? 'Reporte oportuno. Complete la validación del pago y la remisión.' : 'Mismo día previsto. Falta reportar el pago.')), 
        ];
    }

    public static function assertSalida(PedidoBma $pedido): void
    {
        $estado = self::datos($pedido);
        if ($estado !== null && ! $estado['confirmada']) throw new RuntimeException($estado['motivo']);
    }
}
