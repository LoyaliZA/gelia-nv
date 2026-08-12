<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafCredito;
use App\Models\SaldosAFavor\SafMovimiento;
use App\Models\SaldosAFavor\SafPedidoAplicacion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RevertirAplicacionSafService
{
    public function __construct(
        private RegistrarMovimientoSafService $movimientos,
    ) {}

    public function handle(
        ?int $aplicacionId = null,
        ?int $creditoId = null,
        ?float $monto = null,
        ?int $usuarioId = null,
        ?string $observaciones = null,
    ): SafCredito {
        return DB::transaction(function () use ($aplicacionId, $creditoId, $monto, $usuarioId, $observaciones) {
            $app = null;
            if ($aplicacionId) {
                $app = SafPedidoAplicacion::whereKey($aplicacionId)->lockForUpdate()->firstOrFail();
                if ($app->estado !== SafPedidoAplicacion::ESTADO_APLICADO) {
                    throw new InvalidArgumentException('Solo se revierten aplicaciones en estado aplicado.');
                }
                $creditoId = (int) $app->saf_credito_id;
                $monto = (float) $app->monto;
            }

            if (! $creditoId || $monto === null) {
                throw new InvalidArgumentException('Indique la aplicación o saldo a favor + monto a revertir.');
            }

            $monto = round((float) $monto, 2);
            if ($monto <= 0) {
                throw new InvalidArgumentException('El monto a revertir debe ser mayor a cero.');
            }

            $credito = SafCredito::whereKey($creditoId)->lockForUpdate()->firstOrFail();
            if ($credito->estado_financiero === SafCredito::ESTADO_CANCELADO) {
                throw new InvalidArgumentException('No se puede revertir sobre un saldo a favor cancelado.');
            }
            if ($monto - (float) $credito->monto_aplicado > 0.001) {
                throw new InvalidArgumentException("No hay monto aplicado suficiente en {$credito->folio}.");
            }

            $antes = (float) $credito->monto_disponible;
            $credito->monto_aplicado = round((float) $credito->monto_aplicado - $monto, 2);
            $credito->sincronizarDisponible();
            if ($credito->estado_financiero === SafCredito::ESTADO_VENCIDO) {
                // Mantener vencido si ya estaba; remanente queda contable pero no usable.
            }
            $credito->save();

            $extra = [
                'observaciones' => $observaciones ?? 'Reversión de aplicación de saldo a favor',
            ];
            if ($app) {
                $extra['pedido_bma_id'] = $app->pedido_bma_id;
                $extra['saf_pedido_aplicacion_id'] = $app->id;
                $app->estado = SafPedidoAplicacion::ESTADO_LIBERADO;
                $app->liberado_at = now();
                $app->save();

                $pedido = PedidoBma::find($app->pedido_bma_id);
                if ($pedido) {
                    $pedido->saldo_a_favor = (float) SafPedidoAplicacion::query()
                        ->where('pedido_bma_id', $pedido->id)
                        ->whereIn('estado', [
                            SafPedidoAplicacion::ESTADO_RESERVADO,
                            SafPedidoAplicacion::ESTADO_APLICADO,
                        ])
                        ->sum('monto');
                    $pedido->save();
                }
            }

            $this->movimientos->handle(
                $credito,
                SafMovimiento::TIPO_REVERSION,
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
