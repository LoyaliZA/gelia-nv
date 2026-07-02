<?php

namespace App\Services\Cobranza;

use App\Models\Cliente;
use App\Models\CobranzaAlerta;
use App\Models\CobranzaBitacora;
use App\Models\CobranzaFactura;
use Carbon\Carbon;

class RecalcularCreditoClienteService
{
    /**
     * Recalcula vencimiento, alertas de mora y límite de crédito según los parámetros actuales del cliente.
     *
     * @return array{recalculado: bool, motivo?: string, fecha_vencimiento?: string, dias_atraso?: int}
     */
    public function ejecutar(Cliente $cliente, ?int $usuarioId = null, string $origen = 'manual', bool $registrarBitacora = true): array
    {
        $cliente->refresh();

        $facturaActiva = CobranzaFactura::query()
            ->where('cliente_id', $cliente->id)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->where('folio', 'like', 'COB-%')
            ->orderByDesc('monto')
            ->first();

        if (!$facturaActiva) {
            $facturaActiva = CobranzaFactura::query()
                ->where('cliente_id', $cliente->id)
                ->where('pagada', false)
                ->where('monto', '>', 0)
                ->orderByDesc('monto')
                ->first();
        }

        if (!$facturaActiva) {
            return ['recalculado' => false, 'motivo' => 'sin_factura_activa'];
        }

        if (!$cliente->fecha_inicio_credito) {
            return ['recalculado' => false, 'motivo' => 'sin_fecha_inicio'];
        }

        $esLegacy = str_starts_with($facturaActiva->folio, 'COB-');

        if (!$esLegacy) {
            $this->sincronizarAlertasLimite($cliente, $facturaActiva);

            return [
                'recalculado' => true,
                'fecha_vencimiento' => Carbon::parse($facturaActiva->fecha_vencimiento)->toDateString(),
                'dias_atraso' => max(0, (int) Carbon::parse($facturaActiva->fecha_vencimiento)->startOfDay()->diffInDays(now()->startOfDay(), false)),
            ];
        }

        $diasCredito = ($cliente->dias_credito > 0) ? (int) $cliente->dias_credito : 30;
        $fechaInicio = Carbon::parse($cliente->fecha_inicio_credito)->startOfDay();
        $vencimientoCalculado = $fechaInicio->copy()->addDays($diasCredito)->startOfDay();
        $vencimientoAnterior = $facturaActiva->fecha_vencimiento
            ? Carbon::parse($facturaActiva->fecha_vencimiento)->toDateString()
            : null;

        $facturaActiva->update([
            'fecha_emision' => $fechaInicio->toDateString(),
            'fecha_vencimiento' => $vencimientoCalculado->toDateString(),
        ]);

        $hoy = now()->startOfDay();
        $diasAtraso = (int) $vencimientoCalculado->diffInDays($hoy, false);

        $this->sincronizarAlertasVencimiento($cliente, $facturaActiva, $diasAtraso);
        $this->sincronizarAlertasLimite($cliente, $facturaActiva);

        if ($registrarBitacora) {
            CobranzaBitacora::create([
                'cliente_id' => $cliente->id,
                'usuario_id' => $usuarioId ?? auth()->id() ?? 1,
                'tipo_evento' => 'ajuste_manual',
                'monto_anterior' => (float) $facturaActiva->monto,
                'monto_nuevo' => (float) $facturaActiva->monto,
                'descripcion' => sprintf(
                    'Recálculo de crédito (%s). Vencimiento: %s → %s. Plazo: %d días.',
                    $origen,
                    $vencimientoAnterior ?? 'N/A',
                    $vencimientoCalculado->toDateString(),
                    $diasCredito
                ),
                'es_alerta' => false,
            ]);
        }

        return [
            'recalculado' => true,
            'fecha_vencimiento' => $vencimientoCalculado->toDateString(),
            'dias_atraso' => max(0, $diasAtraso),
        ];
    }

    /**
     * @return array{procesados: int, recalculados: int, omitidos: int}
     */
    public function ejecutarMasivo(?int $usuarioId = null): array
    {
        $clienteIds = CobranzaFactura::query()
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->distinct()
            ->pluck('cliente_id');

        $procesados = 0;
        $recalculados = 0;
        $omitidos = 0;

        foreach (Cliente::whereIn('id', $clienteIds)->cursor() as $cliente) {
            $procesados++;
            $resultado = $this->ejecutar($cliente, $usuarioId, 'masivo');
            if ($resultado['recalculado']) {
                $recalculados++;
            } else {
                $omitidos++;
            }
        }

        return compact('procesados', 'recalculados', 'omitidos');
    }

    private function sincronizarAlertasVencimiento(Cliente $cliente, CobranzaFactura $factura, int $diasAtraso): void
    {
        if ($diasAtraso >= 1) {
            $alertaPendiente = CobranzaAlerta::query()
                ->where('factura_id', $factura->id)
                ->where('tipo', 'vencimiento')
                ->where('estado', 'pendiente')
                ->first();

            if ($alertaPendiente) {
                $alertaPendiente->update([
                    'dias_atraso' => $diasAtraso,
                    'fecha_alerta' => now()->toDateString(),
                ]);
            } else {
                CobranzaAlerta::create([
                    'cliente_id' => $cliente->id,
                    'factura_id' => $factura->id,
                    'tipo' => 'vencimiento',
                    'dias_atraso' => $diasAtraso,
                    'fecha_alerta' => now()->toDateString(),
                    'estado' => 'pendiente',
                ]);
            }

            return;
        }

        CobranzaAlerta::query()
            ->where('factura_id', $factura->id)
            ->where('tipo', 'vencimiento')
            ->where('estado', 'pendiente')
            ->delete();
    }

    private function sincronizarAlertasLimite(Cliente $cliente, CobranzaFactura $factura): void
    {
        $limiteFinal = (float) $cliente->monto_credito_autorizado;
        $consolidado = (float) CobranzaFactura::query()
            ->where('cliente_id', $cliente->id)
            ->where('pagada', false)
            ->where('monto', '>', 0)
            ->sum('monto');

        if ($limiteFinal <= 0) {
            CobranzaAlerta::query()
                ->where('cliente_id', $cliente->id)
                ->where('tipo', 'limite_superado')
                ->where('estado', '!=', 'resuelta')
                ->update(['estado' => 'resuelta']);

            return;
        }

        if ($consolidado > $limiteFinal) {
            $existe = CobranzaAlerta::query()
                ->where('cliente_id', $cliente->id)
                ->where('tipo', 'limite_superado')
                ->where('estado', '!=', 'resuelta')
                ->exists();

            if (!$existe) {
                CobranzaAlerta::create([
                    'cliente_id' => $cliente->id,
                    'factura_id' => $factura->id,
                    'tipo' => 'limite_superado',
                    'dias_atraso' => null,
                    'fecha_alerta' => now()->toDateString(),
                    'estado' => 'pendiente',
                ]);
            }

            return;
        }

        CobranzaAlerta::query()
            ->where('cliente_id', $cliente->id)
            ->where('tipo', 'limite_superado')
            ->where('estado', '!=', 'resuelta')
            ->update(['estado' => 'resuelta']);
    }
}
