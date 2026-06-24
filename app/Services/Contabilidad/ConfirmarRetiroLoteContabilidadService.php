<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoEstatusPago;
use App\Models\Contabilidad\LotePago;
use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PlataformaPago;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmarRetiroLoteContabilidadService
{
    public function __construct(
        private CalcularMontoEsperadoBancoService $montoEsperadoService,
    ) {}

    /**
     * @param  array<int, array{id: int, monto_real: float}>  $pedidosPayload
     */
    public function ejecutar(int $plataformaPagoId, string $fechaDeposito, array $pedidosPayload): LotePago
    {
        return DB::transaction(function () use ($plataformaPagoId, $fechaDeposito, $pedidosPayload) {
            $ids = collect($pedidosPayload)->pluck('id')->all();
            $montosPorId = collect($pedidosPayload)->keyBy('id');

            /** @var Collection<int, Pedido> $pedidos */
            $pedidos = Pedido::query()
                ->with('tipoTransaccion')
                ->whereIn('id', $ids)
                ->where('plataforma_pago_id', $plataformaPagoId)
                ->where('estatus_pago_id', CatalogoEstatusPago::PENDIENTE)
                ->get()
                ->keyBy('id');

            if ($pedidos->isEmpty()) {
                throw new \RuntimeException('No hay pedidos pendientes válidos para procesar.');
            }

            $ventasTotal = 0.0;
            $comisionesTotal = 0.0;
            $montoEsperadoTotal = 0.0;
            $montoRealTotal = 0.0;

            foreach ($pedidosPayload as $item) {
                $pedido = $pedidos->get($item['id']);
                if (! $pedido) {
                    continue;
                }

                $montoReal = (float) $item['monto_real'];
                $codigoTipo = $pedido->tipoTransaccion?->codigo ?? 'venta';
                $montoEsperado = $this->montoEsperadoService->ejecutar(
                    $codigoTipo,
                    (float) $pedido->venta_total,
                    (float) $pedido->comision_plataforma
                );

                $ventasTotal += (float) $pedido->venta_total;
                $comisionesTotal += (float) $pedido->comision_plataforma;
                $montoEsperadoTotal += $montoEsperado;
                $montoRealTotal += $montoReal;

                $diferencia = $montoEsperado - $montoReal;
                $comisionTransferencia = $diferencia > 0 ? round($diferencia, 2) : 0.0;

                $pedido->update([
                    'estatus_pago_id' => CatalogoEstatusPago::TRANSFERIDO,
                    'fecha_retiro' => $fechaDeposito,
                    'comision_transferencia' => $comisionTransferencia,
                    'utilidad_total' => round((float) $pedido->utilidad_total - $comisionTransferencia, 2),
                ]);
            }

            $lote = LotePago::query()->create([
                'plataforma_pago_id' => $plataformaPagoId,
                'fecha_corte_esperada' => Carbon::now()->toDateString(),
                'fecha_deposito_real' => $fechaDeposito,
                'monto_ventas_total' => round($ventasTotal, 2),
                'comisiones_plataforma_total' => round($comisionesTotal, 2),
                'monto_esperado_banco' => round($montoEsperadoTotal, 2),
                'monto_real_banco' => round($montoRealTotal, 2),
                'estatus' => LotePago::ESTATUS_COMPLETADO,
            ]);

            Pedido::query()
                ->whereIn('id', $pedidos->keys())
                ->update(['lote_pago_id' => $lote->id]);

            PlataformaPago::query()
                ->whereKey($plataformaPagoId)
                ->update(['ultimo_corte' => $fechaDeposito]);

            return $lote;
        });
    }
}
