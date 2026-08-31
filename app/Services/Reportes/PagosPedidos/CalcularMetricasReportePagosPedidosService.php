<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos;
use Illuminate\Database\Eloquent\Builder;

class CalcularMetricasReportePagosPedidosService
{
    public function __construct(
        private AplicarFiltrosReportePagosPedidosQuery $filtros,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, int|string|bool>
     */
    public function ejecutar(User $usuario, array $params): array
    {
        $query = PedidoBmaCierrePago::query();
        $this->filtros->aplicar($query, $usuario, $params);

        $agg = (clone $query)->selectRaw("
            COUNT(*) as pedidos_validados,
            COALESCE(SUM(monto_venta), 0) as monto_venta,
            COALESCE(SUM(monto_envio), 0) as monto_envio,
            COALESCE(SUM(monto_seguro), 0) as monto_seguro,
            COALESCE(SUM(total_pedido), 0) as total_pedido,
            COALESCE(SUM(saf_aplicado), 0) as saf_aplicado,
            COALESCE(SUM(total_a_cobrar), 0) as total_a_cobrar,
            COALESCE(SUM(pagos_validos), 0) as pagos_validos,
            COALESCE(SUM(CASE WHEN diferencia > 0 THEN diferencia ELSE 0 END), 0) as pendiente_agregado,
            COALESCE(SUM(excedente), 0) as excedente_agregado,
            SUM(CASE WHEN estado_cobertura IN ('parcial', 'con_excedente', 'sin_pago') THEN 1 ELSE 0 END) as pedidos_observaciones,
            SUM(CASE WHEN estado_cobertura = 'cubierto' THEN 1 ELSE 0 END) as pedidos_cubiertos,
            SUM(CASE WHEN estado_cobertura = 'cubierto' AND saf_aplicado > 0 THEN 1 ELSE 0 END) as pedidos_cubiertos_con_saf,
            SUM(CASE WHEN estado_cobertura = 'parcial' THEN 1 ELSE 0 END) as pedidos_parciales,
            SUM(CASE WHEN estado_cobertura = 'con_excedente' THEN 1 ELSE 0 END) as pedidos_con_excedente
        ")->first();

        $itemsEnAlcance = $this->queryItemsEnAlcance($query, $params);
        $exhibicionesIncluidas = (clone $itemsEnAlcance)->count();
        $pagosCoincidentes = (float) (clone $itemsEnAlcance)->sum('monto_snapshot');
        $tieneFiltroExhibicion = AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($params);

        if (! $tieneFiltroExhibicion) {
            $pagosCoincidentes = (float) ($agg->pagos_validos ?? 0);
        }

        $comprobantesArchivos = (clone $itemsEnAlcance)
            ->whereNotNull('ruta_archivo_snapshot')
            ->where('ruta_archivo_snapshot', '!=', '')
            ->count();

        $pedidosConComprobante = (clone $query)
            ->whereHas('items', function (Builder $q) use ($params) {
                AlcanceExhibicionesReportePagosPedidos::aplicarEnQuery($q, $params);
                $q->whereNotNull('ruta_archivo_snapshot')->where('ruta_archivo_snapshot', '!=', '');
            })
            ->count();

        $pedidosHistoricosSinEvidencia = (clone $query)
            ->where(function (Builder $q) {
                $q->whereDoesntHave('items', function (Builder $i) {
                    $i->whereNotNull('ruta_archivo_snapshot')->where('ruta_archivo_snapshot', '!=', '');
                })->orWhereHas('items', function (Builder $i) {
                    $i->where('activo_para_cobertura_snapshot', false);
                });
            })
            ->count();

        $pendiente = (float) ($agg->pendiente_agregado ?? 0);
        $excedente = (float) ($agg->excedente_agregado ?? 0);

        if ($pendiente > 0.005) {
            $saldoEtiqueta = 'pendiente';
            $saldoValor = $pendiente;
        } elseif ($excedente > 0.005) {
            $saldoEtiqueta = 'excedente';
            $saldoValor = $excedente;
        } else {
            $saldoEtiqueta = 'cubierto';
            $saldoValor = 0.0;
        }

        return [
            'pedidos_validados' => (int) ($agg->pedidos_validados ?? 0),
            'total_pedido' => number_format((float) ($agg->total_pedido ?? 0), 2, '.', ''),
            'total_remisiones' => number_format((float) ($agg->total_pedido ?? 0), 2, '.', ''),
            'pagos_validos' => number_format((float) ($agg->pagos_validos ?? 0), 2, '.', ''),
            'pagos_coincidentes_filtros' => number_format($pagosCoincidentes, 2, '.', ''),
            'pagos_coincidentes_es_pedido_completo' => ! $tieneFiltroExhibicion,
            'pendiente' => number_format($pendiente, 2, '.', ''),
            'excedente' => number_format($excedente, 2, '.', ''),
            'saldo_etiqueta' => $saldoEtiqueta,
            'saldo_valor' => number_format($saldoValor, 2, '.', ''),
            'monto_venta' => number_format((float) ($agg->monto_venta ?? 0), 2, '.', ''),
            'monto_envio' => number_format((float) ($agg->monto_envio ?? 0), 2, '.', ''),
            'monto_seguro' => number_format((float) ($agg->monto_seguro ?? 0), 2, '.', ''),
            'saf_aplicado' => number_format((float) ($agg->saf_aplicado ?? 0), 2, '.', ''),
            'total_a_cobrar' => number_format((float) ($agg->total_a_cobrar ?? 0), 2, '.', ''),
            'exhibiciones_incluidas' => $exhibicionesIncluidas,
            'comprobantes_archivos' => $comprobantesArchivos,
            'pedidos_con_comprobante' => $pedidosConComprobante,
            /** @deprecated Use comprobantes_archivos */
            'vouchers_revisados' => $comprobantesArchivos,
            'pedidos_observaciones' => (int) ($agg->pedidos_observaciones ?? 0),
            'pedidos_cubiertos' => (int) ($agg->pedidos_cubiertos ?? 0),
            'pedidos_cubiertos_con_saf' => (int) ($agg->pedidos_cubiertos_con_saf ?? 0),
            'pedidos_parciales' => (int) ($agg->pedidos_parciales ?? 0),
            'pedidos_con_excedente' => (int) ($agg->pedidos_con_excedente ?? 0),
            'cantidad_vouchers' => $comprobantesArchivos,
            'pedidos_historicos_sin_evidencia' => $pedidosHistoricosSinEvidencia,
        ];
    }

    /** @param  array<string, mixed>  $params */
    private function queryItemsEnAlcance(Builder $cierreQuery, array $params): Builder
    {
        $items = PedidoBmaCierrePagoItem::query()
            ->whereIn('pedido_bma_cierre_pago_id', (clone $cierreQuery)->select('pedido_bma_cierres_pago.id'));

        if (AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($params)) {
            AlcanceExhibicionesReportePagosPedidos::aplicarEnQuery($items, $params);
        }

        return $items;
    }
}
