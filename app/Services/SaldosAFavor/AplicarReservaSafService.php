<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AplicarReservaSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    /**
     * Convierte reserva en aplicación definitiva.
     */
    public function handle(
        int $creditoId,
        float $monto,
        ?int $usuarioId = null,
        array $extra = [],
    ): SafCredito {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a aplicar debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($creditoId, $monto, $usuarioId, $extra) {
            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();
            if ($monto - (float) $credito->monto_reservado > 0.001) {
                throw new InvalidArgumentException("No hay reserva suficiente en {$credito->folio} para aplicar.");
            }

            $antes = (float) $credito->monto_disponible;
            $credito->monto_reservado = round((float) $credito->monto_reservado - $monto, 2);
            $credito->monto_aplicado = round((float) $credito->monto_aplicado + $monto, 2);
            $credito->sincronizarDisponible();
            $credito->save();

            $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_APLICACION,
                $monto,
                $antes,
                (float) $credito->monto_disponible,
                $usuarioId,
                $extra
            );

            return $credito->fresh();
        });
    }

    /**
     * Aplica directamente sin reserva previa (p.ej. migración histórica).
     */
    public function aplicarDirecto(
        int $creditoId,
        float $monto,
        ?int $usuarioId = null,
        array $extra = [],
    ): SafCredito {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a aplicar debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($creditoId, $monto, $usuarioId, $extra) {
            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();
            if ($monto - (float) $credito->monto_disponible > 0.001) {
                throw new InvalidArgumentException("Disponible insuficiente en {$credito->folio}.");
            }

            $antes = (float) $credito->monto_disponible;
            $credito->monto_aplicado = round((float) $credito->monto_aplicado + $monto, 2);
            $credito->sincronizarDisponible();
            $credito->save();

            $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_APLICACION,
                $monto,
                $antes,
                (float) $credito->monto_disponible,
                $usuarioId,
                $extra
            );

            return $credito->fresh();
        });
    }
}
