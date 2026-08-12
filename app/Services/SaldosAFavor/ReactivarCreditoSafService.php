<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReactivarCreditoSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    public function handle(
        int $creditoId,
        ?int $usuarioId = null,
        ?string $observaciones = null,
        ?int $nuevaVigenciaDias = null,
    ): SafCredito {
        return DB::transaction(function () use ($creditoId, $usuarioId, $observaciones, $nuevaVigenciaDias) {
            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();

            if ($credito->estado_financiero === SafCredito::ESTADO_CANCELADO) {
                throw new InvalidArgumentException('No se puede reactivar un saldo a favor cancelado.');
            }
            if ($credito->estado_financiero !== SafCredito::ESTADO_VENCIDO) {
                throw new InvalidArgumentException('Solo se reactivan saldos a favor en estado vencido.');
            }

            $credito->sincronizarDisponible();
            $remanente = (float) $credito->monto_disponible;
            if ($remanente <= 0.001) {
                throw new InvalidArgumentException('El saldo a favor vencido no tiene remanente para reactivar.');
            }

            $antes = $remanente;
            $dias = $nuevaVigenciaDias ?? SafCredito::VIGENCIA_DIAS;
            $credito->fecha_vencimiento = now()->addDays($dias)->toDateString();
            $credito->recalcularEstadoFinanciero();
            // Forzar salida de vencido tras recalcular
            if ($credito->estado_financiero === SafCredito::ESTADO_VENCIDO) {
                $credito->estado_financiero = (float) $credito->monto_aplicado > 0
                    ? SafCredito::ESTADO_PARCIAL
                    : SafCredito::ESTADO_DISPONIBLE;
            }
            $credito->save();

            $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_REACTIVACION,
                $remanente,
                $antes,
                (float) $credito->monto_disponible,
                $usuarioId,
                ['observaciones' => $observaciones ?? 'Reactivación de saldo a favor vencido']
            );

            return $credito->fresh();
        });
    }
}
