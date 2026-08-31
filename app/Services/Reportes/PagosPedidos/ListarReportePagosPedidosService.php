<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\User;
use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Support\Collection;

class ListarReportePagosPedidosService
{
    private const DIAS_POR_PAGINA = 7;

    public function __construct(
        private AplicarFiltrosReportePagosPedidosQuery $filtros,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{grupos: list<array>, paginacion: array, filtros: array}
     */
    public function ejecutar(User $usuario, array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));

        $base = PedidoBmaCierrePago::query();
        $this->filtros->aplicar($base, $usuario, $params);

        $fechas = (clone $base)
            ->selectRaw('DATE(pedido_fecha) as dia')
            ->distinct()
            ->orderByDesc('dia')
            ->pluck('dia')
            ->map(fn ($d) => $d === null ? FechasPagoReporte::CLAVE_SIN_FECHA : (string) $d);

        $totalDias = $fechas->count();
        $offset = ($page - 1) * self::DIAS_POR_PAGINA;
        $diasPagina = $fechas->slice($offset, self::DIAS_POR_PAGINA)->values();

        $grupos = [];
        foreach ($diasPagina as $dia) {
            $cierres = (clone $base)
                ->with([
                    'cliente:id,nombre,numero_cliente',
                    'vendedor:id,name',
                    'departamento:id,nombre',
                    'validadoPor:id,name',
                ])
                ->withCount([
                    'items',
                    'items as vouchers_count' => fn ($q) => $q->whereNotNull('ruta_archivo_snapshot')->where('ruta_archivo_snapshot', '!=', ''),
                ])
                ->when(
                    $dia === FechasPagoReporte::CLAVE_SIN_FECHA,
                    fn ($q) => $q->whereNull('pedido_fecha'),
                    fn ($q) => $q->whereDate('pedido_fecha', $dia),
                )
                ->orderByDesc('pedido_fecha')
                ->orderByDesc('validado_at')
                ->get();

            $grupos[] = [
                'fecha' => $dia,
                'fecha_label' => FechasPagoReporte::etiquetaGrupoPedido($dia),
                'resumen' => [
                    'pedidos' => $cierres->count(),
                    'total_remisiones' => $this->sum($cierres, 'total_pedido'),
                    'monto_venta' => $this->sum($cierres, 'monto_venta'),
                    'pagos_validos' => $this->sum($cierres, 'pagos_validos'),
                    'saf_aplicado' => $this->sum($cierres, 'saf_aplicado'),
                    'diferencia' => $this->sum($cierres, 'diferencia'),
                    'excedente' => $this->sum($cierres, 'excedente'),
                    'observaciones' => $cierres->whereIn('estado_cobertura', ['parcial', 'con_excedente', 'sin_pago'])->count(),
                ],
                'pedidos' => $cierres->map(fn (PedidoBmaCierrePago $c) => $this->compacto($c))->values()->all(),
            ];
        }

        return [
            'grupos' => $grupos,
            'paginacion' => [
                'current_page' => $page,
                'per_page' => self::DIAS_POR_PAGINA,
                'total' => $totalDias,
                'last_page' => max(1, (int) ceil($totalDias / self::DIAS_POR_PAGINA)),
            ],
            'filtros' => $params,
        ];
    }

    /** @return Collection<int, PedidoBmaCierrePago> */
    public function cierresFiltrados(User $usuario, array $params): Collection
    {
        $query = PedidoBmaCierrePago::query()
            ->with(['cliente', 'vendedor', 'departamento', 'almacen', 'validadoPor', 'pedido.remision', 'items.capturadoPor', 'items.revisadoPor']);
        $this->filtros->aplicar($query, $usuario, $params);

        $orden = ($params['orden'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy('pedido_fecha', $orden)
            ->orderBy('validado_at', $orden)
            ->get();
    }

    private function compacto(PedidoBmaCierrePago $c): array
    {
        return [
            'cierre_id' => $c->id,
            'version' => $c->version,
            'estado_cierre' => $c->estado,
            'folio' => $c->folio_snapshot,
            'folio_remision' => $c->folio_remision_snapshot,
            'cliente' => $c->cliente ? [
                'nombre' => $c->cliente->nombre,
                'numero_cliente' => $c->cliente->numero_cliente,
            ] : null,
            'vendedor' => $c->vendedor?->name,
            'departamento' => $c->departamento?->nombre,
            'monto_venta' => $c->monto_venta,
            'total_pedido' => $c->total_pedido,
            'total_a_cobrar' => $c->total_a_cobrar,
            'pagos_validos' => $c->pagos_validos,
            'saf_aplicado' => $c->saf_aplicado,
            'diferencia' => $c->diferencia,
            'excedente' => $c->excedente,
            'estado_cobertura' => $c->estado_cobertura,
            'num_exhibiciones' => $c->items_count ?? 0,
            'pedido_fecha' => $c->pedido_fecha?->toDateString(),
            'pedido_fecha_label' => \App\Support\Reportes\FechasPagoReporte::formatear($c->pedido_fecha),
            'validado_at' => $c->validado_at?->toIso8601String(),
            'validado_por' => $c->validadoPor?->name,
            'tiene_remision' => ! empty($c->metadata_snapshot['remision_documento_id']),
            'tiene_vouchers' => ($c->vouchers_count ?? 0) > 0,
            'vouchers_count' => (int) ($c->vouchers_count ?? 0),
        ];
    }

    /** @param  Collection<int, PedidoBmaCierrePago>  $cierres  */
    private function sum(Collection $cierres, string $campo): string
    {
        $total = $cierres->sum(fn ($c) => (float) $c->{$campo});

        return number_format($total, 2, '.', '');
    }
}
