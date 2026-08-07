<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\User;
use App\Notifications\SaldoFavorReservaLiberadaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class LiberarReservaSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    public function handle(
        int $creditoId,
        float $monto,
        ?int $usuarioId = null,
        array $extra = [],
    ): SafCredito {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw new InvalidArgumentException('El monto a liberar debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($creditoId, $monto, $usuarioId, $extra) {
            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();
            if ($monto - (float) $credito->monto_reservado > 0.001) {
                throw new InvalidArgumentException("No hay reserva suficiente en {$credito->folio}.");
            }

            $antes = (float) $credito->monto_disponible;
            $credito->monto_reservado = round((float) $credito->monto_reservado - $monto, 2);
            $credito->sincronizarDisponible();
            $credito->save();

            $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_LIBERACION,
                $monto,
                $antes,
                (float) $credito->monto_disponible,
                $usuarioId,
                $extra
            );

            $fresh = $credito->fresh();
            $revisores = User::permission('saldos_favor.revisar')->get();
            if ($revisores->isNotEmpty()) {
                Notification::send($revisores, new SaldoFavorReservaLiberadaNotification($fresh, $monto));
            }

            return $fresh;
        });
    }
}
