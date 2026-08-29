<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use Illuminate\Support\Collection;

/** Encabezado compacto de cada día en el PDF. */
final class ResumenDiaReportePagosPedidosPdf
{
    private const ESTADOS_INCIDENCIA = ['parcial', 'con_excedente', 'sin_pago'];

    /**
     * @param  Collection<int, PedidoBmaCierrePago>  $cierres
     * @param  array<string, mixed>  $params
     * @return array{meta_linea: string, incidencias: int, pagos_es_pedido_completo: bool}
     */
    public static function presentar(Collection $cierres, array $params): array
    {
        $pedidos = $cierres->count();
        $incidencias = $cierres->whereIn('estado_cobertura', self::ESTADOS_INCIDENCIA)->count();
        $pagosEsPedidoCompleto = ! AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($params);

        $pagos = 0.0;
        $vouchers = 0;

        if ($pagosEsPedidoCompleto) {
            $pagos = (float) $cierres->sum(fn (PedidoBmaCierrePago $c) => (float) $c->pagos_validos);
            foreach ($cierres as $cierre) {
                foreach ($cierre->items as $item) {
                    if (AlcanceExhibicionesReportePagosPedidos::itemTieneVoucher($item)) {
                        $vouchers++;
                    }
                }
            }
        } else {
            foreach ($cierres as $cierre) {
                foreach (AlcanceExhibicionesReportePagosPedidos::filtrar($cierre->items, $params) as $item) {
                    $pagos += (float) $item->monto_snapshot;
                    if (AlcanceExhibicionesReportePagosPedidos::itemTieneVoucher($item)) {
                        $vouchers++;
                    }
                }
            }
        }

        $meta = [
            self::fmtPedidos($pedidos),
            self::fmtPagos($pagos),
            self::fmtVouchers($vouchers),
            self::fmtIncidencias($incidencias),
        ];

        return [
            'meta_linea' => implode(' · ', $meta),
            'incidencias' => $incidencias,
            'pagos_es_pedido_completo' => $pagosEsPedidoCompleto,
        ];
    }

    private static function fmtPedidos(int $n): string
    {
        return $n === 1 ? '1 pedido' : number_format($n).' pedidos';
    }

    private static function fmtPagos(float $monto): string
    {
        return '$'.number_format($monto, 2, '.', ',').' en pagos';
    }

    private static function fmtVouchers(int $n): string
    {
        return $n === 1 ? '1 voucher' : number_format($n).' vouchers';
    }

    private static function fmtIncidencias(int $n): string
    {
        if ($n === 0) {
            return 'Sin incidencias';
        }
        if ($n === 1) {
            return '1 incidencia';
        }

        return number_format($n).' incidencias';
    }
}
