<?php

namespace App\Support\Reportes;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use Illuminate\Support\Carbon;

/** Tabla de exhibiciones en el PDF por pedido. */
final class ExhibicionesPedidoReportePagosPedidosPdf
{
    /** @var array<string, string> */
    private const ESTADOS_REVISION = [
        'pendiente' => 'Pendiente',
        'en_revision' => 'En revisión',
        'verificado' => 'Verificado',
        'con_observaciones' => 'Con observaciones',
        'rechazado' => 'Rechazado',
        'confirmado' => 'Verificado',
        'con_diferencia' => 'Con observaciones',
    ];

    /**
     * @param  array<string, mixed>  $params
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     contexto: array{hay_filtro: bool, mostradas: int, totales: int, pagos_coincidentes: string, pagos_pedido_completo: string, nota: ?string},
     *     vacio: bool
     * }
     */
    public static function presentar(PedidoBmaCierrePago $cierre, array $params): array
    {
        $todos = $cierre->items->values()->all();
        $filtrados = AlcanceExhibicionesReportePagosPedidos::filtrar($cierre->items, $params);
        $hayFiltro = AlcanceExhibicionesReportePagosPedidos::tieneFiltrosItem($params);

        $pagosCoincidentes = $hayFiltro
            ? array_sum(array_map(fn (PedidoBmaCierrePagoItem $i) => (float) $i->monto_snapshot, $filtrados))
            : (float) $cierre->pagos_validos;

        $filas = [];
        foreach ($filtrados as $item) {
            $cobertura = self::labelCobertura($item, $todos);

            $filas[] = [
                'numero' => $item->numero_exhibicion,
                'monto' => self::fmtMxn((float) $item->monto_snapshot),
                'forma' => PedidoBmaPago::labelForma($item->forma_pago_snapshot),
                'banco' => $item->banco_snapshot ?: '—',
                'referencia' => $item->referencia_snapshot ?: '—',
                'fecha_reportada' => self::fmtFecha($item->capturado_at_snapshot),
                'fecha_pago' => self::fmtFecha($item->fecha_pago_snapshot),
                'estado' => self::labelEstado($item->estado_revision_snapshot),
                'revisor' => $item->revisadoPor?->name ?? '—',
                'cobertura' => $cobertura['label'],
                'cobertura_tono' => $cobertura['tono'],
            ];
        }

        $contexto = [
            'hay_filtro' => $hayFiltro,
            'mostradas' => count($filas),
            'totales' => count($todos),
            'pagos_coincidentes' => self::fmtMxn($pagosCoincidentes),
            'pagos_pedido_completo' => self::fmtMxn((float) $cierre->pagos_validos),
            'nota' => $hayFiltro
                ? 'La ficha financiera refleja el pedido completo. Esta tabla muestra solo exhibiciones coincidentes con los filtros aplicados.'
                : null,
        ];

        return [
            'filas' => $filas,
            'contexto' => $contexto,
            'vacio' => count($filas) === 0,
        ];
    }

    /**
     * @param  list<PedidoBmaCierrePagoItem>  $todos
     * @return array{label: string, tono: string}
     */
    public static function labelCobertura(PedidoBmaCierrePagoItem $item, array $todos): array
    {
        if ($item->activo_para_cobertura_snapshot) {
            return ['label' => 'Incluido', 'tono' => 'exito'];
        }

        foreach ($todos as $otro) {
            if ($otro->reemplaza_pago_id !== null && (int) $otro->reemplaza_pago_id === (int) $item->pedido_bma_pago_id) {
                return ['label' => 'Sustituido', 'tono' => 'info'];
            }
        }

        return ['label' => 'No contabilizado', 'tono' => 'neutro'];
    }

    private static function labelEstado(?string $estado): string
    {
        if ($estado === null || $estado === '') {
            return '—';
        }

        return self::ESTADOS_REVISION[$estado] ?? $estado;
    }

    private static function fmtMxn(float $monto): string
    {
        return '$'.number_format($monto, 2, '.', ',');
    }

    private static function fmtFecha(?Carbon $fecha): string
    {
        if ($fecha === null) {
            return '—';
        }

        return $fecha->copy()->locale('es')->isoFormat('D MMM YYYY');
    }
}
