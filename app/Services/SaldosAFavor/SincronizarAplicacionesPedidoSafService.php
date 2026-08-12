<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SincronizarAplicacionesPedidoSafService
{
    public function __construct(
        private ReservarSaldoSafService $reservar,
        private LiberarReservaSafService $liberar,
        private AplicarReservaSafService $aplicar,
    ) {}

    /**
     * Reemplaza reservas del pedido por la selección dada y actualiza saldo_a_favor derivado.
     *
     * @param  list<array{saf_credito_id:int, monto:float|int|string}>  $seleccion
     */
    public function reservarParaPedido(PedidoBma $pedido, array $seleccion, ?int $usuarioId = null): float
    {
        if (! $pedido->cliente_id) {
            throw new InvalidArgumentException('El pedido debe tener cliente para aplicar saldo a favor.');
        }

        return DB::transaction(function () use ($pedido, $seleccion, $usuarioId) {
            $this->liberarReservasPendientes($pedido, $usuarioId);

            $seleccion = array_values(array_filter(
                $seleccion,
                fn ($r) => (float) ($r['monto'] ?? 0) > 0 && (int) ($r['saf_credito_id'] ?? 0) > 0
            ));

            if ($seleccion === []) {
                $pedido->saldo_a_favor = 0;
                $pedido->save();

                return 0.0;
            }

            $montoTotal = round(array_sum(array_map(fn ($r) => (float) $r['monto'], $seleccion)), 2);
            $reservas = $this->reservar->handle(
                (int) $pedido->cliente_id,
                $montoTotal,
                $usuarioId,
                $seleccion,
                ['pedido_bma_id' => $pedido->id]
            );

            $total = 0.0;
            foreach ($reservas as $item) {
                $app = SafPedidoAplicacion::create([
                    'pedido_bma_id' => $pedido->id,
                    'saf_credito_id' => $item['credito']->id,
                    'monto' => $item['monto'],
                    'estado' => SafPedidoAplicacion::ESTADO_RESERVADO,
                    'reservado_por_id' => $usuarioId,
                    'reservado_at' => now(),
                ]);
                $total = round($total + $item['monto'], 2);
                // Vincular movimiento más reciente de reserva (best-effort via last movement)
                unset($app);
            }

            $pedido->saldo_a_favor = $total;
            $pedido->save();

            return $total;
        });
    }

    public function aplicarReservasPedido(PedidoBma $pedido, ?int $usuarioId = null): float
    {
        return DB::transaction(function () use ($pedido, $usuarioId) {
            $apps = SafPedidoAplicacion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('estado', SafPedidoAplicacion::ESTADO_RESERVADO)
                ->lockForUpdate()
                ->get();

            $total = 0.0;
            foreach ($apps as $app) {
                $this->aplicar->handle(
                    (int) $app->saf_credito_id,
                    (float) $app->monto,
                    $usuarioId,
                    [
                        'pedido_bma_id' => $pedido->id,
                        'saf_pedido_aplicacion_id' => $app->id,
                    ]
                );
                $app->estado = SafPedidoAplicacion::ESTADO_APLICADO;
                $app->aplicado_por_id = $usuarioId;
                $app->aplicado_at = now();
                $app->save();
                $total = round($total + (float) $app->monto, 2);
            }

            $pedido->saldo_a_favor = $total > 0
                ? $total
                : (float) SafPedidoAplicacion::query()
                    ->where('pedido_bma_id', $pedido->id)
                    ->where('estado', SafPedidoAplicacion::ESTADO_APLICADO)
                    ->sum('monto');
            $pedido->save();

            return (float) $pedido->saldo_a_favor;
        });
    }

    public function liberarReservasPendientes(PedidoBma $pedido, ?int $usuarioId = null): void
    {
        DB::transaction(function () use ($pedido, $usuarioId) {
            $apps = SafPedidoAplicacion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('estado', SafPedidoAplicacion::ESTADO_RESERVADO)
                ->lockForUpdate()
                ->get();

            foreach ($apps as $app) {
                $this->liberar->handle(
                    (int) $app->saf_credito_id,
                    (float) $app->monto,
                    $usuarioId,
                    [
                        'pedido_bma_id' => $pedido->id,
                        'saf_pedido_aplicacion_id' => $app->id,
                    ]
                );
                $app->estado = SafPedidoAplicacion::ESTADO_LIBERADO;
                $app->liberado_at = now();
                $app->save();
            }
        });
    }

    public function totalAplicadoOReservado(PedidoBma $pedido): float
    {
        return (float) SafPedidoAplicacion::query()
            ->where('pedido_bma_id', $pedido->id)
            ->whereIn('estado', [
                SafPedidoAplicacion::ESTADO_RESERVADO,
                SafPedidoAplicacion::ESTADO_APLICADO,
            ])
            ->sum('monto');
    }
}
