<?php

namespace App\Services\SaldosAFavor;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafMotivo;
use App\Models\SaldosAFavor\SafPedidoAplicacion;

/**
 * Tras un cambio de total en el pedido: genera saldo a favor si hay excedente,
 * abre incidencia si el total subió sin cobertura, y recorta reservas excesivas.
 */
class ReconciliarTotalPedidoSafService
{
    public function __construct(
        private RegistrarPagoPedidoBmaService $pagos,
        private GenerarCreditoSafService $generar,
        private SincronizarAplicacionesPedidoSafService $sync,
        private RegistrarIncidenciaSafService $incidencias,
    ) {}

    /**
     * @return array{credito_id:?int, incidencia_id:?int, excedente:float, pendiente:float}
     */
    public function handle(
        PedidoBma $pedido,
        float $totalAntes,
        ?int $usuarioId = null,
        string $motivoCodigo = 'sobrante_envio',
        ?string $observaciones = null,
    ): array {
        $pedido->refresh();
        $totalDespues = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);
        $resumen = $this->pagos->resumenPago($pedido);
        $excedente = (float) $resumen['excedente'];
        $pendiente = (float) $resumen['pendiente'];

        $creditoId = null;
        $incidenciaId = null;

        // Recortar reservas si superan el nuevo subtotal cobrable.
        $reservado = (float) SafPedidoAplicacion::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado', SafPedidoAplicacion::ESTADO_RESERVADO)
            ->sum('monto');
        $subtotalSinSaldo = (float) ($pedido->total_a_cobrar ?? 0) + (float) ($pedido->saldo_a_favor ?? 0);
        $maxSaldoUtil = max(0, round($subtotalSinSaldo - (float) $resumen['total_recibido'], 2));
        if ($reservado > $maxSaldoUtil + 0.01 && $pedido->cliente_id) {
            $apps = SafPedidoAplicacion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('estado', SafPedidoAplicacion::ESTADO_RESERVADO)
                ->orderBy('id')
                ->get();
            $seleccion = [];
            $acum = 0.0;
            foreach ($apps as $app) {
                $tomar = min((float) $app->monto, max(0, round($maxSaldoUtil - $acum, 2)));
                if ($tomar > 0.01) {
                    $seleccion[] = ['saf_credito_id' => (int) $app->saf_credito_id, 'monto' => $tomar];
                    $acum = round($acum + $tomar, 2);
                }
                if ($acum >= $maxSaldoUtil) {
                    break;
                }
            }
            $this->sync->reservarParaPedido($pedido->fresh(), $seleccion, $usuarioId);
            $pedido->refresh();
            $resumen = $this->pagos->resumenPago($pedido);
            $excedente = (float) $resumen['excedente'];
            $pendiente = (float) $resumen['pendiente'];
        }

        if ($excedente > 0.01 && $pedido->cliente_id) {
            $motivoId = SafMotivo::where('codigo', $motivoCodigo)->value('id')
                ?? SafMotivo::where('codigo', 'ajuste_admin')->value('id');
            $credito = $this->generar->handle([
                'cliente_id' => (int) $pedido->cliente_id,
                'monto' => $excedente,
                'saf_motivo_id' => $motivoId,
                'detalle_motivo' => $observaciones ?? "Excedente por ajuste de total del pedido {$pedido->folio}",
                'canal_origen' => 'bellaroma',
                'pedido_bma_id' => $pedido->id,
                'documento_origen' => $pedido->folio_remision ?: $pedido->folio,
                'generado_por_id' => $usuarioId,
                'observaciones' => sprintf(
                    'Reconciliación automática. Total antes: %s. Total después: %s.',
                    number_format($totalAntes, 2, '.', ''),
                    number_format($totalDespues, 2, '.', '')
                ),
            ]);
            $creditoId = $credito->id;
        }

        if ($totalDespues > $totalAntes + 0.01 && $pendiente > 0.01) {
            $inc = $this->incidencias->handle(
                'total_aumento',
                sprintf(
                    'El total del pedido %s subió de %s a %s. Pendiente por cubrir: %s. No se modificaron saldos ya aplicados.',
                    $pedido->folio,
                    number_format($totalAntes, 2, '.', ''),
                    number_format($totalDespues, 2, '.', ''),
                    number_format($pendiente, 2, '.', '')
                ),
                $pedido->cliente_id ? (int) $pedido->cliente_id : null,
                $pedido->id,
                null,
                $usuarioId
            );
            $incidenciaId = $inc->id;
        }

        return [
            'credito_id' => $creditoId,
            'incidencia_id' => $incidenciaId,
            'excedente' => $excedente,
            'pendiente' => $pendiente,
        ];
    }
}
