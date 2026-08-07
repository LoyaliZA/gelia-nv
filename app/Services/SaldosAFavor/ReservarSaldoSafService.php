<?php

namespace App\Services\SaldosAFavor;

use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReservarSaldoSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    /**
     * Reserva montos sobre saldos específicos o por FIFO de vencimiento.
     *
     * @param  list<array{saf_credito_id:int, monto:float|int|string}>|null  $seleccion
     * @return list<array{credito:SafCredito, monto:float}>
     */
    public function handle(
        int $clienteId,
        float $montoTotal,
        ?int $usuarioId = null,
        ?array $seleccion = null,
        array $extra = [],
    ): array {
        $montoTotal = round($montoTotal, 2);
        if ($montoTotal <= 0) {
            throw new InvalidArgumentException('El monto a reservar debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($clienteId, $montoTotal, $usuarioId, $seleccion, $extra) {
            $plan = $seleccion
                ? $this->planDesdeSeleccion($clienteId, $seleccion)
                : $this->planFifo($clienteId, $montoTotal);

            $suma = round(array_sum(array_column($plan, 'monto')), 2);
            if ($suma + 0.001 < $montoTotal && $seleccion === null) {
                throw new InvalidArgumentException('Saldo disponible insuficiente para reservar el monto solicitado.');
            }
            if ($seleccion !== null && abs($suma - $montoTotal) > 0.01) {
                // Si hay selección explícita, el total es la suma de la selección.
            }

            $resultado = [];
            foreach ($plan as $item) {
                /** @var SafCredito $credito */
                $credito = SafCredito::whereKey($item['credito_id'])->lockForUpdate()->firstOrFail();
                $this->assertUsable($credito, $clienteId);

                $monto = round((float) $item['monto'], 2);
                if ($monto <= 0) {
                    continue;
                }
                if ($monto - (float) $credito->monto_disponible > 0.001) {
                    throw new InvalidArgumentException("El saldo a favor {$credito->folio} no tiene disponible suficiente.");
                }

                $antes = (float) $credito->monto_disponible;
                $credito->monto_reservado = round((float) $credito->monto_reservado + $monto, 2);
                $credito->sincronizarDisponible();
                $credito->save();

                $this->movimientos->handle(
                    $credito,
                    SafMovimiento::TIPO_RESERVA,
                    $monto,
                    $antes,
                    (float) $credito->monto_disponible,
                    $usuarioId,
                    $extra
                );

                $resultado[] = ['credito' => $credito->fresh(), 'monto' => $monto];
            }

            return $resultado;
        });
    }

    /**
     * @param  list<array{saf_credito_id:int, monto:float|int|string}>  $seleccion
     * @return list<array{credito_id:int, monto:float}>
     */
    private function planDesdeSeleccion(int $clienteId, array $seleccion): array
    {
        $plan = [];
        foreach ($seleccion as $row) {
            $id = (int) ($row['saf_credito_id'] ?? 0);
            $monto = round((float) ($row['monto'] ?? 0), 2);
            if ($id <= 0 || $monto <= 0) {
                continue;
            }
            $plan[] = ['credito_id' => $id, 'monto' => $monto];
        }
        if ($plan === []) {
            throw new InvalidArgumentException('Debe indicar al menos un saldo a favor con monto a reservar.');
        }

        return $plan;
    }

    /**
     * @return list<array{credito_id:int, monto:float}>
     */
    private function planFifo(int $clienteId, float $montoTotal): array
    {
        $creditos = $this->creditosDisponibles($clienteId);
        $restante = $montoTotal;
        $plan = [];

        foreach ($creditos as $credito) {
            if ($restante <= 0) {
                break;
            }
            $tomar = min((float) $credito->monto_disponible, $restante);
            if ($tomar <= 0) {
                continue;
            }
            $plan[] = ['credito_id' => $credito->id, 'monto' => round($tomar, 2)];
            $restante = round($restante - $tomar, 2);
        }

        if ($restante > 0.001) {
            throw new InvalidArgumentException('Saldo disponible insuficiente para reservar el monto solicitado.');
        }

        return $plan;
    }

    private function creditosDisponibles(int $clienteId): Collection
    {
        return SafCredito::query()
            ->where('cliente_id', $clienteId)
            ->whereIn('estado_financiero', [SafCredito::ESTADO_DISPONIBLE, SafCredito::ESTADO_PARCIAL])
            ->where('monto_disponible', '>', 0)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function assertUsable(SafCredito $credito, int $clienteId): void
    {
        if ((int) $credito->cliente_id !== $clienteId) {
            throw new InvalidArgumentException('El saldo a favor no pertenece al cliente.');
        }
        if (! $credito->puedeUsarse()) {
            throw new InvalidArgumentException("El saldo a favor {$credito->folio} no está disponible para uso.");
        }
        if ($credito->fecha_vencimiento->lt(now()->startOfDay())) {
            throw new InvalidArgumentException("El saldo a favor {$credito->folio} está vencido.");
        }
    }
}
