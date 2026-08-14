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
     * Reserva montos por FIFO de vencimiento. Si se pasa selección, se ignora
     * y se replanifica con el monto total (fuente de verdad: FIFO).
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

        // Si hay selección explícita, el total es la suma; siempre se aplica FIFO.
        if ($seleccion !== null && $seleccion !== []) {
            $sumaSeleccion = round(array_sum(array_map(
                fn ($r) => (float) ($r['monto'] ?? 0),
                $seleccion
            )), 2);
            if ($sumaSeleccion > 0) {
                $montoTotal = $sumaSeleccion;
            }
        }

        return DB::transaction(function () use ($clienteId, $montoTotal, $usuarioId, $extra) {
            $plan = $this->planFifo($clienteId, $montoTotal);

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
            ->whereIn('estado_financiero', SafCredito::ESTADOS_USABLES)
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
