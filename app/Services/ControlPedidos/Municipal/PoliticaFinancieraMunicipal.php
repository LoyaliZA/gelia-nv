<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Support\ControlPedidos\ConsultaMunicipal;
use Illuminate\Validation\ValidationException;

/**
 * Autoridad del avance comercial municipal. No escribe ni redefine cubierto.
 */
class PoliticaFinancieraMunicipal
{
    public function esMunicipal(PedidoBma $pedido): bool
    {
        return ConsultaMunicipal::es($pedido);
    }

    public function assertPuedeOperarComercial(PedidoBma $pedido): void
    {
        if (! $this->esMunicipal($pedido)) {
            return;
        }
        if (ConsultaMunicipal::estado($pedido) !== ConsultaMunicipal::CERRADA) {
            throw ValidationException::withMessages([
                'consulta' => 'La consulta municipal no está cerrada. La respuesta del área no autoriza el pago ni la remisión.',
            ]);
        }
        $motivo = ConsultaMunicipal::motivoBloqueo($pedido);
        if ($motivo) {
            throw ValidationException::withMessages(['consulta' => $motivo]);
        }
    }

    /** @return array<string, mixed> */
    public function evaluar(PedidoBma $pedido): array
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $finanzas = is_array($json['finanzas_municipales'] ?? null) ? $json['finanzas_municipales'] : [];
        $conocimiento = ($finanzas['costo_transporte'] ?? null) === 'diferido' ? 'diferido' : 'conocido';
        $leyenda = ($finanzas['leyenda_terminal'] ?? 'PAGADO') === 'POR_COBRAR' ? 'POR_COBRAR' : 'PAGADO';
        $mercancia = round((float) ($pedido->total_mercancia ?? 0), 2);
        $envioConocido = $conocimiento === 'conocido' ? round((float) ($pedido->costo_envio ?? 0), 2) : null;
        $saldo = round((float) ($pedido->saldo_a_favor ?? 0), 2);
        $exigible = round($mercancia + ($envioConocido ?? 0) - $saldo, 2);
        if ($exigible < 0) {
            $exigible = 0;
        }
        $pagado = $this->pagado($pedido);
        $mercanciaNeta = max(0, round($mercancia - $saldo, 2));

        return [
            'consulta_cerrada' => ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA,
            'importe_exigible' => number_format($exigible, 2, '.', ''),
            'conocimiento_costo' => $conocimiento,
            'importe_transporte' => $envioConocido === null ? null : number_format($envioConocido, 2, '.', ''),
            'transporte_desconocido' => $conocimiento === 'diferido',
            'mercancia_cubierta' => $pagado + 0.009 >= $mercanciaNeta,
            'transporte_cubierto' => $conocimiento === 'conocido' && $pagado + 0.009 >= $exigible,
            'leyenda_terminal' => $leyenda,
            'puede_remisionar' => ConsultaMunicipal::estado($pedido) === ConsultaMunicipal::CERRADA && $pagado + 0.009 >= $exigible,
        ];
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
