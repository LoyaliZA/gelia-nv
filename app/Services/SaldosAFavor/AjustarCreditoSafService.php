<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AjustarCreditoSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    public function handle(
        int $creditoId,
        float $montoDelta,
        ?int $usuarioId = null,
        ?int $motivoId = null,
        ?string $observaciones = null,
    ): SafCredito {
        $montoDelta = round($montoDelta, 2);
        if ($montoDelta == 0.0) {
            throw new InvalidArgumentException('El ajuste debe ser distinto de cero.');
        }
        if (! $motivoId) {
            throw new InvalidArgumentException('El ajuste requiere un motivo del catálogo.');
        }
        if (blank($observaciones)) {
            throw new InvalidArgumentException('El ajuste requiere observaciones.');
        }

        return DB::transaction(function () use ($creditoId, $montoDelta, $usuarioId, $motivoId, $observaciones) {
            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();
            if ($credito->estado_financiero === SafCredito::ESTADO_CANCELADO) {
                throw new InvalidArgumentException('No se puede ajustar un saldo a favor cancelado.');
            }

            $antes = (float) $credito->monto_disponible;
            $nuevoOriginal = round((float) $credito->monto_original + $montoDelta, 2);
            if ($nuevoOriginal < 0) {
                throw new InvalidArgumentException('El ajuste dejaría el monto original negativo.');
            }

            $credito->monto_original = $nuevoOriginal;
            $credito->sincronizarDisponible();
            if ((float) $credito->monto_disponible < -0.001) {
                throw new InvalidArgumentException('El ajuste dejaría el disponible negativo.');
            }
            $credito->estado_revision = SafCredito::REVISION_AJUSTADO;
            $credito->save();

            $this->movimientos->handle(
                $credito,
                $montoDelta > 0 ? SafMovimiento::TIPO_AJUSTE_POS : SafMovimiento::TIPO_AJUSTE_NEG,
                abs($montoDelta),
                $antes,
                (float) $credito->monto_disponible,
                $usuarioId,
                [
                    'saf_motivo_id' => $motivoId,
                    'observaciones' => $observaciones,
                ]
            );

            return $credito->fresh();
        });
    }
}
