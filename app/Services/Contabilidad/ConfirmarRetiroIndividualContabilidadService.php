<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoEstatusPago;
use App\Models\Contabilidad\LotePago;
use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PlataformaPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConfirmarRetiroIndividualContabilidadService
{
    public function __construct(
        private CalcularMontoEsperadoBancoService $montoEsperadoService,
    ) {}

    public function ejecutar(Pedido $pedido, float $montoRealBanco, string $fechaDeposito): Pedido
    {
        return DB::transaction(function () use ($pedido, $montoRealBanco, $fechaDeposito) {
            $pedido->loadMissing(['tipoTransaccion', 'plataformaPago']);

            if ($pedido->estaTransferido()) {
                throw new \RuntimeException('El pedido ya fue transferido.');
            }

            $codigoTipo = $pedido->tipoTransaccion?->codigo ?? 'venta';
            $montoEsperado = $this->montoEsperadoService->ejecutar(
                $codigoTipo,
                (float) $pedido->venta_total,
                (float) $pedido->comision_plataforma
            );

            $diferencia = $montoEsperado - $montoRealBanco;
            $comisionTransferencia = $diferencia > 0 ? round($diferencia, 2) : 0.0;
            $nuevaUtilidad = round((float) $pedido->utilidad_total - $comisionTransferencia, 2);

            $lote = LotePago::query()->create([
                'plataforma_pago_id' => $pedido->plataforma_pago_id,
                'fecha_corte_esperada' => Carbon::now()->toDateString(),
                'fecha_deposito_real' => $fechaDeposito,
                'monto_ventas_total' => $pedido->venta_total,
                'comisiones_plataforma_total' => $pedido->comision_plataforma,
                'monto_esperado_banco' => $montoEsperado,
                'monto_real_banco' => $montoRealBanco,
                'estatus' => LotePago::ESTATUS_COMPLETADO,
            ]);

            $pedido->update([
                'estatus_pago_id' => CatalogoEstatusPago::TRANSFERIDO,
                'lote_pago_id' => $lote->id,
                'fecha_retiro' => $fechaDeposito,
                'comision_transferencia' => $comisionTransferencia,
                'utilidad_total' => $nuevaUtilidad,
            ]);

            PlataformaPago::query()
                ->whereKey($pedido->plataforma_pago_id)
                ->update(['ultimo_corte' => $fechaDeposito]);

            return $pedido->fresh(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas']);
        });
    }
}
