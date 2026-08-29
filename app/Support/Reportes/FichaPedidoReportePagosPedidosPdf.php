<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos;
use Illuminate\Support\Carbon;

/** Ficha visual de un pedido en el PDF. */
final class FichaPedidoReportePagosPedidosPdf
{
    private const EPS = 0.005;

    /** @var array<string, string> */
    private const ESTADOS_CIERRE = [
        PedidoBmaCierrePago::ESTADO_VIGENTE => 'Vigente',
        PedidoBmaCierrePago::ESTADO_REVOCADO => 'Revocado',
    ];

    /** @var array<string, string> */
    private const ESTADOS_COBERTURA = [
        'cubierto' => 'Cubierto',
        'parcial' => 'Parcial',
        'con_excedente' => 'Excedente',
        'sin_pago' => 'Sin pago',
    ];

    /**
     * @return array{
     *     campos: list<array{label: string, valor: string}>,
     *     estado_cierre: string,
     *     version: int,
     *     estado_cobertura: string,
     *     composicion: list<array{label: string, valor: string, destacado: bool}>,
     *     cobertura: list<array{label: string, valor: string, destacado: bool, tono: ?string}>
     * }
     */
    public static function presentar(PedidoBmaCierrePago $cierre, array $params = []): array
    {
        $fin = [
            'monto_venta' => (float) $cierre->monto_venta,
            'monto_envio' => (float) $cierre->monto_envio,
            'monto_seguro' => (float) $cierre->monto_seguro,
            'total_pedido' => (float) $cierre->total_pedido,
            'saf_aplicado' => (float) $cierre->saf_aplicado,
            'total_a_cobrar' => (float) $cierre->total_a_cobrar,
            'pagos_validos' => (float) $cierre->pagos_validos,
            'diferencia' => (float) $cierre->diferencia,
            'excedente' => (float) $cierre->excedente,
            'tolerancia_aplicada' => (float) $cierre->tolerancia_aplicada,
            'estado_cobertura' => $cierre->estado_cobertura,
        ];

        $vendedor = $cierre->vendedor?->name ?? '—';
        $departamento = $cierre->departamento?->nombre;
        $vendedorDepto = $departamento ? "{$vendedor} · {$departamento}" : $vendedor;

        $estadoCierre = self::ESTADOS_CIERRE[$cierre->estado] ?? ucfirst((string) $cierre->estado);
        $resultado = self::lineaResultadoCobertura($fin);
        $alcancePedido = AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($params)
            ? 'Pedido completo'
            : null;

        return [
            'campos' => [
                ['label' => 'Folio GELIA', 'valor' => $cierre->folio_snapshot ?: '—'],
                ['label' => 'Folio de remisión', 'valor' => $cierre->folio_remision_snapshot ?: '—'],
                ['label' => 'Cliente', 'valor' => $cierre->cliente?->nombre ?? '—'],
                ['label' => 'Número de cliente', 'valor' => $cierre->cliente?->numero_cliente ?? '—'],
                ['label' => 'Vendedora / departamento', 'valor' => $vendedorDepto],
                ['label' => 'Fecha de validación', 'valor' => self::fmtFechaHora($cierre->validado_at)],
                ['label' => 'Validó', 'valor' => $cierre->validadoPor?->name ?? '—'],
                ['label' => 'Estado del cierre y versión', 'valor' => "{$estadoCierre} · v{$cierre->version}"],
            ],
            'estado_cierre' => $estadoCierre,
            'version' => (int) $cierre->version,
            'estado_cobertura' => self::ESTADOS_COBERTURA[$cierre->estado_cobertura ?? ''] ?? ($cierre->estado_cobertura ?? '—'),
            'composicion' => [
                ['label' => 'Mercancía', 'valor' => self::fmtMxn($fin['monto_venta']), 'destacado' => false],
                ['label' => 'Envío', 'valor' => self::fmtMxn($fin['monto_envio']), 'destacado' => false],
                ['label' => 'Seguro', 'valor' => self::fmtMxn($fin['monto_seguro']), 'destacado' => false],
                ['label' => 'Total del pedido', 'valor' => self::fmtMxn($fin['total_pedido']), 'destacado' => true],
            ],
            'cobertura' => [
                ['label' => 'SAF', 'valor' => '−'.self::fmtMxn($fin['saf_aplicado']), 'destacado' => false, 'tono' => null, 'alcance' => $alcancePedido],
                ['label' => 'Total a cobrar', 'valor' => self::fmtMxn($fin['total_a_cobrar']), 'destacado' => true, 'tono' => null, 'alcance' => $alcancePedido],
                ['label' => 'Pagos válidos', 'valor' => self::fmtMxn($fin['pagos_validos']), 'destacado' => false, 'tono' => null, 'alcance' => $alcancePedido],
                [
                    'label' => 'Pendiente o excedente',
                    'valor' => $resultado['valor'],
                    'destacado' => true,
                    'tono' => $resultado['tono'],
                    'alcance' => $alcancePedido,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, float|string|null>  $fin
     * @return array{valor: string, tono: ?string}
     */
    private static function lineaResultadoCobertura(array $fin): array
    {
        $diferencia = (float) ($fin['diferencia'] ?? 0);
        $excedente = (float) ($fin['excedente'] ?? 0);
        $tolerancia = (float) ($fin['tolerancia_aplicada'] ?? 0);
        $estado = $fin['estado_cobertura'] ?? null;
        $pendiente = max(0.0, $diferencia);

        if ($pendiente <= self::EPS && $excedente <= self::EPS) {
            return ['valor' => self::fmtMxn(0).' — Cubierto', 'tono' => 'exito'];
        }
        if ($excedente > self::EPS && ($excedente > $tolerancia + self::EPS || $estado === 'con_excedente')) {
            return ['valor' => self::fmtMxn($excedente), 'tono' => 'advertencia'];
        }
        if ($pendiente > self::EPS && ($pendiente > $tolerancia + self::EPS || in_array($estado, ['parcial', 'sin_pago'], true))) {
            return [
                'valor' => self::fmtMxn($pendiente),
                'tono' => $estado === 'sin_pago' ? 'critico' : 'advertencia',
            ];
        }

        $residual = $pendiente > self::EPS ? $pendiente : $excedente;

        return ['valor' => self::fmtMxn($residual).' (tolerancia)', 'tono' => 'info'];
    }

    private static function fmtMxn(float $monto): string
    {
        return '$'.number_format($monto, 2, '.', ',');
    }

    private static function fmtFechaHora(?Carbon $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        return $fecha->copy()->locale('es')->isoFormat('D MMM YYYY, HH:mm');
    }
}
