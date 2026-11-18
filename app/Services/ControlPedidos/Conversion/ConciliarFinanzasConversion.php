<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Support\ControlPedidos\Conversion\FinanzasConversionModalidad;

/**
 * Overlay municipal: el pendiente es solo el cargo nuevo. No reescribe la fórmula canónica.
 */
class ConciliarFinanzasConversion
{
    /**
     * @param  array<string, mixed>  $captura
     */
    public function evaluar(PedidoBma $pedido, array $captura = []): FinanzasConversionModalidad
    {
        $mercancia = number_format((float) ($pedido->total_mercancia ?? 0), 2, '.', '');
        $pagadoMonto = $this->pagado($pedido);
        $pagado = number_format($pagadoMonto, 2, '.', '');
        $actual = $pedido->costo_envio;
        $costoEnvioActual = $actual === null || $actual === ''
            ? null
            : number_format((float) $actual, 2, '.', '');

        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $persistidas = is_array($json['finanzas_municipales'] ?? null) ? $json['finanzas_municipales'] : [];
        $transporte = $this->conocimiento($captura, $persistidas);

        $nuevoCapturado = $captura['costo_envio'] ?? null;
        if ($nuevoCapturado === null && $transporte === 'conocido') {
            $nuevoCapturado = $pedido->costo_envio;
        }

        $diferido = $transporte === 'diferido';
        $conocido = $transporte === 'conocido' && is_numeric($nuevoCapturado) && round((float) $nuevoCapturado, 2) > 0;

        $costoEnvioNuevo = null;
        $transporteDesconocido = false;
        if ($diferido) {
            $costoEnvioNuevo = null;
            $transporteDesconocido = true;
        } elseif ($conocido) {
            $costoEnvioNuevo = number_format(round((float) $nuevoCapturado, 2), 2, '.', '');
        }

        $mercanciaNeta = max(0, round((float) $mercancia - (float) ($pedido->saldo_a_favor ?? 0), 2));
        $reconocido = max(0, round($pagadoMonto - $mercanciaNeta, 2));
        $nuevo = $costoEnvioNuevo === null ? 0.0 : (float) $costoEnvioNuevo;
        $pendiente = $transporteDesconocido ? 0.0 : max(0, round($nuevo - $reconocido, 2));

        return new FinanzasConversionModalidad(
            $mercancia,
            $pagado,
            $costoEnvioActual,
            $costoEnvioNuevo,
            number_format($pendiente, 2, '.', ''),
            $transporteDesconocido,
            $conocido || $transporteDesconocido,
        );
    }

    /**
     * Captura de vista previa gana; GET tras confirmar lee finanzas_municipales.
     *
     * @param  array<string, mixed>  $captura
     * @param  array<string, mixed>  $persistidas
     */
    private function conocimiento(array $captura, array $persistidas): ?string
    {
        foreach ([$captura['costo_transporte'] ?? null, $persistidas['costo_transporte'] ?? null] as $valor) {
            if ($valor === 'diferido' || $valor === 'conocido') {
                return $valor;
            }
        }

        return null;
    }

    private function pagado(PedidoBma $pedido): float
    {
        if (! $pedido->exists) {
            return 0.0;
        }

        return round((float) PedidoBmaPago::query()
            ->where('pedido_bma_id', $pedido->id)
            ->activosParaCobertura()
            ->sum('monto'), 2);
    }
}
