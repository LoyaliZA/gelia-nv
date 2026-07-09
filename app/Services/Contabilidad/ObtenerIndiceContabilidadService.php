<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\CatalogoEstatusPago;
use App\Models\Contabilidad\CatalogoTipoTransaccion;
use App\Models\Contabilidad\ContabilidadConfiguracion;
use App\Models\Contabilidad\Pedido;
use App\Models\Contabilidad\PlataformaPago;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ObtenerIndiceContabilidadService
{
    private const POR_PAGINA = 10;

    /**
     * @return array{
     *     pedidos: LengthAwarePaginator,
     *     metricas: array<string, float|int>,
     *     plataformas: \Illuminate\Support\Collection,
     *     tipos_transaccion: \Illuminate\Support\Collection,
     *     estatus_pago: \Illuminate\Support\Collection,
     *     datos_grafica: array<string, array{utilidad: float, venta: float}>,
     *     filtros: array<string, mixed>,
     *     configuracion: array{mapeo_precios: array{sku: string, precio_base: string, descripcion: string}}
     * }
     */
    public function ejecutar(array $filtros): array
    {
        $mes = (int) ($filtros['mes'] ?? now()->month);
        $anio = (int) ($filtros['anio'] ?? now()->year);
        $busqueda = isset($filtros['q']) ? trim((string) $filtros['q']) : '';
        $plataformaId = isset($filtros['plataforma_id']) && $filtros['plataforma_id'] !== ''
            ? (int) $filtros['plataforma_id']
            : null;
        $estatusId = isset($filtros['estatus_pago_id']) && $filtros['estatus_pago_id'] !== ''
            ? (int) $filtros['estatus_pago_id']
            : null;
        $tipoId = isset($filtros['tipo_transaccion_id']) && $filtros['tipo_transaccion_id'] !== ''
            ? (int) $filtros['tipo_transaccion_id']
            : null;

        $queryBase = Pedido::query()
            ->whereMonth('fecha_salida', $mes)
            ->whereYear('fecha_salida', $anio);

        $metricasQuery = clone $queryBase;

        $pedidosQuery = Pedido::query()
            ->with(['plataformaPago', 'estatusPago', 'tipoTransaccion', 'lineas'])
            ->whereMonth('fecha_salida', $mes)
            ->whereYear('fecha_salida', $anio)
            ->orderByDesc('fecha_salida')
            ->orderByDesc('id');

        if ($busqueda !== '') {
            $pedidosQuery->where(function ($q) use ($busqueda) {
                $q->where('numero_pedido', 'like', "%{$busqueda}%")
                    ->orWhere('cliente_nombre', 'like', "%{$busqueda}%");
            });
        }

        if ($plataformaId) {
            $pedidosQuery->where('plataforma_pago_id', $plataformaId);
        }

        if ($estatusId) {
            $pedidosQuery->where('estatus_pago_id', $estatusId);
        }

        if ($tipoId) {
            $pedidosQuery->where('tipo_transaccion_id', $tipoId);
        }

        $pedidosPeriodo = (clone $queryBase)
            ->orderBy('fecha_salida')
            ->get(['fecha_salida', 'utilidad_total', 'venta_total']);

        $datosGrafica = $pedidosPeriodo
            ->groupBy(fn ($p) => Carbon::parse($p->fecha_salida)->format('Y-m-d'))
            ->map(fn ($grupo) => [
                'utilidad' => round((float) $grupo->sum('utilidad_total'), 2),
                'venta' => round((float) $grupo->sum('venta_total'), 2),
            ])
            ->all();

        return [
            'pedidos' => $pedidosQuery->paginate(self::POR_PAGINA)->withQueryString(),
            'metricas' => [
                'total_pedidos' => (int) $metricasQuery->count(),
                'ventas_total' => round((float) $metricasQuery->sum('venta_total'), 2),
                'utilidad_total' => round((float) $metricasQuery->sum('utilidad_total'), 2),
                'comisiones_total' => round((float) $metricasQuery->sum('comision_plataforma'), 2),
                'pendientes' => (int) (clone $metricasQuery)
                    ->where('estatus_pago_id', CatalogoEstatusPago::PENDIENTE)
                    ->count(),
            ],
            'plataformas' => PlataformaPago::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tasa_comision_pct', 'cuota_fija', 'tasa_iva_pct']),
            'tipos_transaccion' => CatalogoTipoTransaccion::query()
                ->orderBy('id')
                ->get(['id', 'codigo', 'nombre']),
            'estatus_pago' => CatalogoEstatusPago::query()
                ->orderBy('id')
                ->get(['id', 'codigo', 'nombre']),
            'datos_grafica' => $datosGrafica,
            'filtros' => [
                'mes' => $mes,
                'anio' => $anio,
                'q' => $busqueda !== '' ? $busqueda : null,
                'plataforma_id' => $plataformaId,
                'estatus_pago_id' => $estatusId,
                'tipo_transaccion_id' => $tipoId,
            ],
            'configuracion' => [
                'mapeo_precios' => ContabilidadConfiguracion::obtener()->mapeoPreciosEfectivo(),
            ],
        ];
    }
}
