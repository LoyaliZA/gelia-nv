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
        $zona = app(\App\Services\ControlPedidos\PreparacionTiendaConfig::class)->zonaHoraria();
        $fechaObjetivo = $json['tienda_md_solicitud']['fecha'] ?? (filled($reporte['primer_pago_at'] ?? null) ? \Carbon\Carbon::parse($reporte['primer_pago_at'])->timezone($zona)->toDateString() : null);
        $diaVigente = $fechaObjetivo !== null && $fechaObjetivo === now($zona)->toDateString();
        $origenCedis = ($json['area_revision'] ?? null) === 'control_pedidos';
        $local = now($zona);
        $continuacion = (! $oportuno && filled($reporte['primer_pago_at'] ?? null)) || ($fechaObjetivo !== null && ! $diaVigente)
            || (blank($reporte['primer_pago_at'] ?? null) && ($local->isWeekend() || $local->gt($local->copy()->setTime(13, 0))));
        $motivo = 'Mismo día previsto. Falta reportar el pago.';
        if (! $origenCedis) $motivo = 'Las piezas ya disponibles en PDV requieren acordar la modalidad; no se clasifican automáticamente como TIENDA MD.';
        elseif ($continuacion) $motivo = 'TIENDA MD pendiente de acuerdo de continuación. No se reprograma automáticamente.';
        elseif ($confirmada) $motivo = 'Reporte oportuno, pago validado y remisión registrada. Falta completar el traslado y la entrega.';
        elseif ($oportuno) $motivo = 'Reporte oportuno. Complete la validación del pago y la remisión.';
        return [
            'ficha' => 'T-02.1', 'cobertura' => 'transferencia', 'intencion' => 'prevista',
            'confirmada' => $confirmada, 'reporte' => $reporte['estado'] ?? null,
            'primer_pago_at' => $reporte['primer_pago_at'] ?? null,
            'fecha_objetivo' => $fechaObjetivo, 'dia_vigente' => $diaVigente,
            'origen_cedis' => $origenCedis, 'pendiente_acuerdo' => $continuacion,
            'puede_preparar_md' => $confirmada && $diaVigente && $origenCedis,
            'motivo' => $motivo,
        ];
    }

    public static function assertSalida(PedidoBma $pedido): void
    {
        $estado = self::datos($pedido);
        if ($estado !== null && ! $estado['puede_preparar_md']) throw new RuntimeException($estado['motivo']);
    }
}
