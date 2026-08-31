<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Support\Reportes\ExhibicionVouchersValidadosMapper;
use App\Support\Reportes\FechasPagoReporte;
use Illuminate\Support\Collection;

class ListarReporteVouchersValidadosService
{
    private const GRUPOS_POR_PAGINA = 7;

    public function __construct(
        private AplicarFiltrosReporteVouchersValidadosQuery $filtros,
        private ExhibicionVouchersValidadosMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{grupos: list<array>, paginacion: array, agrupar_por: string}
     */
    public function ejecutar(User $usuario, array $params): array
    {
        $agrupar = $this->normalizarAgrupacion($params['agrupar_por'] ?? 'movimiento');
        $page = max(1, (int) ($params['page'] ?? 1));

        $items = $this->filtros->itemsVisibles($usuario, $params);
        $duplicados = $this->filtros->posiblesDuplicados($usuario, $params);

        if (! empty($params['posible_duplicado'])) {
            $items = array_values(array_filter(
                $items,
                fn (PedidoBmaCierrePagoItem $i) => isset($duplicados[$i->id])
            ));
        }

        $agrupados = $this->agrupar($items, $agrupar);
        $claves = $agrupados->keys()->values();
        $totalGrupos = $claves->count();
        $offset = ($page - 1) * self::GRUPOS_POR_PAGINA;
        $clavesPagina = $claves->slice($offset, self::GRUPOS_POR_PAGINA);

        $grupos = [];
        foreach ($clavesPagina as $clave) {
            /** @var Collection<int, PedidoBmaCierrePagoItem> $grupoItems */
            $grupoItems = $agrupados[$clave];
            $grupos[] = $this->serializarGrupo($clave, $grupoItems, $agrupar, $usuario, $duplicados);
        }

        return [
            'grupos' => $grupos,
            'agrupar_por' => $agrupar,
            'paginacion' => [
                'current_page' => $page,
                'per_page' => self::GRUPOS_POR_PAGINA,
                'total' => $totalGrupos,
                'last_page' => max(1, (int) ceil($totalGrupos / self::GRUPOS_POR_PAGINA)),
            ],
        ];
    }

    /**
     * Todos los grupos sin paginación (exportación PDF).
     *
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function todosLosGrupos(User $usuario, array $params): array
    {
        $agrupar = $this->normalizarAgrupacion($params['agrupar_por'] ?? 'movimiento');
        $items = $this->filtros->itemsVisibles($usuario, $params);
        $duplicados = $this->filtros->posiblesDuplicados($usuario, $params);

        if (! empty($params['posible_duplicado'])) {
            $items = array_values(array_filter(
                $items,
                fn (PedidoBmaCierrePagoItem $i) => isset($duplicados[$i->id])
            ));
        }

        $agrupados = $this->agrupar($items, $agrupar);
        $grupos = [];
        foreach ($agrupados as $clave => $grupoItems) {
            $grupos[] = $this->serializarGrupo($clave, $grupoItems, $agrupar, $usuario, $duplicados);
        }

        return $grupos;
    }

    private function normalizarAgrupacion(string $agrupar): string
    {
        return match ($agrupar) {
            'banco' => 'banco',
            'forma_pago' => 'forma_pago',
            'dia', 'movimiento' => 'movimiento',
            default => 'movimiento',
        };
    }

    /**
     * @param  list<PedidoBmaCierrePagoItem>  $items
     * @return Collection<string, Collection<int, PedidoBmaCierrePagoItem>>
     */
    private function agrupar(array $items, string $agrupar): Collection
    {
        $coleccion = collect($items);

        return match ($agrupar) {
            'banco' => $coleccion->groupBy(fn (PedidoBmaCierrePagoItem $i) => (string) ($i->catalogo_banco_id ?? '__sin_banco__')),
            'forma_pago' => $coleccion->groupBy(fn (PedidoBmaCierrePagoItem $i) => (string) ($i->forma_pago_snapshot ?? '__sin_forma__')),
            default => $coleccion->groupBy(fn (PedidoBmaCierrePagoItem $i) => $i->fecha_pago_snapshot
                ? $i->fecha_pago_snapshot->toDateString()
                : FechasPagoReporte::CLAVE_SIN_FECHA),
        };
    }

    /**
     * @param  Collection<int, PedidoBmaCierrePagoItem>  $grupoItems
     * @param  array<int, true>  $duplicados
     * @return array<string, mixed>
     */
    private function serializarGrupo(
        string $clave,
        Collection $grupoItems,
        string $agrupar,
        User $usuario,
        array $duplicados,
    ): array {
        $totalValidadoCentavos = 0;
        $posterior = 0;
        $observaciones = 0;
        $dupEnGrupo = 0;
        $pedidos = [];
        $bancos = [];
        $formas = [];

        foreach ($grupoItems as $item) {
            if (\App\Support\Reportes\ClasificacionIngresoBancario::cuentaIngresoBancario($item)) {
                $totalValidadoCentavos += (int) round((float) $item->monto_snapshot * 100);
            }
            $cierre = $item->cierre;
            if ($cierre) {
                $pedidos[$cierre->pedido_bma_id] = true;
            }
            if ($item->banco_snapshot) {
                $bancos[$item->banco_snapshot] = true;
            }
            if ($item->forma_pago_snapshot) {
                $formas[$item->forma_pago_snapshot] = PedidoBmaPago::labelForma($item->forma_pago_snapshot);
            }
            if (FechasPagoReporte::reportadoPosteriormente($item->fecha_pago_snapshot, $item->capturado_at_snapshot)) {
                $posterior++;
            }
            if ($item->estado_revision_snapshot === PedidoBmaPago::REVISION_CON_OBSERVACIONES
                || ! empty($item->motivo_rechazo_snapshot)) {
                $observaciones++;
            }
            if (isset($duplicados[$item->id])) {
                $dupEnGrupo++;
            }
        }

        $label = match ($agrupar) {
            'banco' => $clave === '__sin_banco__'
                ? 'Sin banco'
                : ($grupoItems->first()?->banco_snapshot ?? 'Sin banco'),
            'forma_pago' => $clave === '__sin_forma__'
                ? 'Sin forma de pago'
                : (PedidoBmaPago::labelForma($clave) ?? $clave),
            default => FechasPagoReporte::etiquetaGrupoPedido($clave),
        };

        $periodo = null;
        if ($agrupar === 'banco' || $agrupar === 'forma_pago') {
            $fechas = $grupoItems->map(fn ($i) => $i->fecha_pago_snapshot?->toDateString())->filter()->sort()->values();
            if ($fechas->isNotEmpty()) {
                $periodo = [
                    'desde' => $fechas->first(),
                    'hasta' => $fechas->last(),
                ];
            }
        }

        $filas = $grupoItems->map(function (PedidoBmaCierrePagoItem $item) use ($usuario, $duplicados) {
            $cierre = $item->cierre;
            if (! $cierre) {
                return null;
            }

            return $this->mapper->fila($item, $cierre, $usuario, $duplicados);
        })->filter()->values()->all();

        return [
            'clave' => $clave,
            'label' => $label,
            'agrupar_por' => $agrupar,
            'resumen' => [
                'total_validado' => number_format($totalValidadoCentavos / 100, 2, '.', ''),
                'vouchers' => $grupoItems->count(),
                'pedidos' => count($pedidos),
                'bancos_involucrados' => count($bancos),
                'formas_pago' => array_values($formas),
                'reportados_posteriormente' => $posterior,
                'observaciones' => $observaciones,
                'posibles_duplicados' => $dupEnGrupo,
                'periodo' => $periodo,
            ],
            'exhibiciones' => $filas,
        ];
    }
}
