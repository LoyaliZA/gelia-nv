<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelarCreditoSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    public function handle(
        int $creditoId,
        ?int $usuarioId = null,
        ?string $observaciones = null,
    ): SafCredito {
        return DB::transaction(function () use ($creditoId, $usuarioId, $observaciones) {
            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();
            if ($credito->estado_financiero === SafCredito::ESTADO_CANCELADO) {
                return $credito;
            }
            if ((float) $credito->monto_reservado > 0.001) {
                throw new InvalidArgumentException('Libere las reservas antes de cancelar el saldo a favor.');
            }

            $antes = (float) $credito->monto_disponible;
            $monto = $antes;
            $credito->monto_disponible = 0;
            $credito->estado_financiero = SafCredito::ESTADO_CANCELADO;
            $credito->estado_revision = SafCredito::REVISION_RECHAZADO;
            $credito->save();

            $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_CANCELACION,
                $monto,
                $antes,
                0,
                $usuarioId,
                ['observaciones' => $observaciones]
            );

            return $credito->fresh();
        });
    }
}
