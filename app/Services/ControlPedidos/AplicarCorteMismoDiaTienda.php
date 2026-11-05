<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Support\ControlPedidos\CorteMismoDiaTienda;

/** Conserva el primer instante aceptado. Sustituir el soporte no mueve el corte. */
final class AplicarCorteMismoDiaTienda
{
    public function __construct(
        private PreparacionTiendaConfig $config,
        private CorteMismoDiaTienda $corte,
    ) {}

    public function aplicar(PedidoBma $pedido, PedidoBmaPago $pago): void
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $reporte = is_array($json['tienda_md_reporte'] ?? null) ? $json['tienda_md_reporte'] : [];
        if (filled($reporte['primer_pago_at'] ?? null)) {
            return;
        }

        $intencion = ($json['atencion_tienda'] ?? null) === 'prevista'
            && ($json['cobertura_tienda']['tipo'] ?? null) === 'transferencia'
            && $pago->forma_pago === 'transferencia';
        if (! $intencion) {
            return;
        }
        $evaluacion = $this->corte->evaluar($pago->created_at ?? now(), $this->config->zonaHoraria(), true);
        $fechaSolicitud = $json['tienda_md_solicitud']['fecha'] ?? null;
        if ($fechaSolicitud && $fechaSolicitud !== ($pago->created_at ?? now())->copy()->timezone($this->config->zonaHoraria())->toDateString()) {
            $evaluacion['estado'] = CorteMismoDiaTienda::TARDIO;
            $evaluacion['oportuno'] = false;
            $evaluacion['motivo'] = 'El pago se reportó en otra fecha que la solicitud TIENDA MD. Falta acordar la continuación; no se reprograma automáticamente.';
        }
        $json['tienda_md_reporte'] = [
            'estado' => $evaluacion['estado'],
            'oportuno' => $evaluacion['oportuno'],
            'confirmada' => false,
            'motivo' => $evaluacion['motivo'],
            'corte' => $evaluacion['corte'],
            'primer_pago_id' => $pago->id,
            'primer_pago_at' => ($pago->created_at ?? now())->toIso8601String(),
        ];
        $json['tienda_md'] = [
            'ficha' => 'T-02.1',
            'cobertura' => 'transferencia',
            'confirmada' => false,
            'reporte' => $evaluacion['estado'],
        ];

        $pedido->update([
            'solicitud_preparacion_json' => $json,
            'prioridad_md' => $intencion && $evaluacion['oportuno'],
        ]);
    }
}
