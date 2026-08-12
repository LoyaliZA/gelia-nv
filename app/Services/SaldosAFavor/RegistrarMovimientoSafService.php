<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use Illuminate\Support\Facades\DB;

class RegistrarMovimientoSafService
{
    public function handle(
        SafCredito $credito,
        string $tipo,
        float $monto,
        float $saldoAnterior,
        float $saldoPosterior,
        ?int $usuarioId = null,
        array $extra = [],
    ): SafMovimiento {
        return SafMovimiento::create([
            'saf_credito_id' => $credito->id,
            'cliente_id' => $credito->cliente_id,
            'tipo' => $tipo,
            'monto' => round($monto, 2),
            'saldo_anterior' => round($saldoAnterior, 2),
            'saldo_posterior' => round($saldoPosterior, 2),
            'pedido_bma_id' => $extra['pedido_bma_id'] ?? null,
            'saf_comprobante_caja_id' => $extra['saf_comprobante_caja_id'] ?? null,
            'saf_pedido_aplicacion_id' => $extra['saf_pedido_aplicacion_id'] ?? null,
            'referencia_externa' => $extra['referencia_externa'] ?? null,
            'usuario_id' => $usuarioId,
            'saf_motivo_id' => $extra['saf_motivo_id'] ?? null,
            'observaciones' => $extra['observaciones'] ?? null,
        ]);
    }
}
