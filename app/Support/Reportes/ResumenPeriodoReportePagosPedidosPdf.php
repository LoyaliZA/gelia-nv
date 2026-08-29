<?php

namespace App\Support\Reportes;

/**
 * Presentación del resumen del periodo para el PDF.
 */
final class ResumenPeriodoReportePagosPedidosPdf
{
    private const ALCANCE_PEDIDO = 'Pedido completo';

    /**
     * @param  array<string, mixed>  $metricas
     * @return array{indicadores: list<array{label: string, valor: string, alcance: ?string}>, informativos: list<array{label: string, valor: string}>, nota: string}
     */
    public static function presentar(array $metricas): array
    {
        $alcancePagos = ! empty($metricas['pagos_coincidentes_es_pedido_completo'])
            ? self::ALCANCE_PEDIDO
            : 'Exhibiciones filtradas';

        return [
            'indicadores' => [
                [
                    'label' => 'Pedidos incluidos',
                    'valor' => self::fmtNum($metricas['pedidos_validados'] ?? 0),
                    'alcance' => 'Cierres en alcance',
                ],
                [
                    'label' => 'Total del pedido',
                    'valor' => self::fmtMxn($metricas['total_pedido'] ?? 0),
                    'alcance' => self::ALCANCE_PEDIDO,
                ],
                [
                    'label' => 'Pagos coincidentes con los filtros',
                    'valor' => self::fmtMxn($metricas['pagos_coincidentes_filtros'] ?? 0),
                    'alcance' => $alcancePagos,
                ],
                [
                    'label' => 'SAF aplicado',
                    'valor' => self::fmtMxn($metricas['saf_aplicado'] ?? 0),
                    'alcance' => self::ALCANCE_PEDIDO,
                ],
                [
                    'label' => 'Pendiente',
                    'valor' => self::fmtMxn($metricas['pendiente'] ?? 0),
                    'alcance' => self::ALCANCE_PEDIDO,
                ],
                [
                    'label' => 'Excedente',
                    'valor' => self::fmtMxn($metricas['excedente'] ?? 0),
                    'alcance' => self::ALCANCE_PEDIDO,
                ],
            ],
            'informativos' => [
                [
                    'label' => 'Exhibiciones incluidas',
                    'valor' => self::fmtNum($metricas['exhibiciones_incluidas'] ?? 0),
                ],
                [
                    'label' => 'Vouchers',
                    'valor' => self::fmtNum($metricas['comprobantes_archivos'] ?? 0),
                ],
                [
                    'label' => 'Pedidos con observaciones',
                    'valor' => self::fmtNum($metricas['pedidos_observaciones'] ?? 0),
                ],
                [
                    'label' => 'Pedidos históricos o sin evidencia',
                    'valor' => self::fmtNum($metricas['pedidos_historicos_sin_evidencia'] ?? 0),
                ],
            ],
            'nota' => 'Las cifras marcadas como «Pedido completo» corresponden al cierre financiero del pedido incluido en el reporte, '
                .'aunque el pedido haya entrado por un filtro de exhibición.',
        ];
    }

    private static function fmtMxn(mixed $value): string
    {
        return '$'.number_format((float) $value, 2, '.', ',');
    }

    private static function fmtNum(mixed $value): string
    {
        return number_format((int) $value);
    }
}
