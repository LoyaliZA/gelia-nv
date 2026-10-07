<?php

namespace App\Services\Escalonamiento;

use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\Escalonamiento\EscalonamientoAplicacionDevolucion;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoMovimiento;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\Escalonamiento\EscalonamientoResumenCliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ListarComparacionClientesEscalonamiento
{
    private const ALIAS_RESUMEN = 'erc';

    public function __construct(
        private EvaluarListaClienteEscalonamiento $evaluarLista,
        private ParticipacionClienteEscalonamiento $participacion,
        private EvaluarInactividadClienteEscalonamiento $inactividad,
        private ResolverResultadoClienteEscalonamiento $resolverResultado,
        private ListaPublicoGeneralEscalonamiento $listaPg,
    ) {}

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     paginacion: array{current_page: int, last_page: int, per_page: int, total: int, from: ?int, to: ?int}
     * }
     */
    public function listar(EscalonamientoPeriodo $periodo, Request $request): array
    {
        $perPage = min(100, max(10, (int) $request->get('per_page', 25)));
        $orden = (string) $request->get('orden', 'numero');
        $direccion = strtolower((string) $request->get('direccion', 'asc')) === 'desc' ? 'desc' : 'asc';
        $busqueda = trim((string) $request->get('q', ''));
        $rapido = (string) $request->get('filtro_rapido', '');
        $ocultarInactivos = $request->boolean('ocultar_inactivos');
        $resultadosFiltro = $this->parseResultadosFiltro($request);

        $nombresLista = CatalogoListaDescuento::query()->pluck('nombre', 'id');

        $ventasSub = $this->subconsultaVentas($periodo);
        $devolucionesSub = $this->subconsultaDevoluciones($periodo);

        $query = Cliente::query()
            ->select('clientes.*')
            ->leftJoin('escalonamiento_resumenes_cliente as '.self::ALIAS_RESUMEN, function (JoinClause $join) use ($periodo) {
                $join->on('clientes.id', '=', self::ALIAS_RESUMEN.'.cliente_id')
                    ->where(self::ALIAS_RESUMEN.'.escalonamiento_periodo_id', '=', $periodo->id);
            })
            ->leftJoinSub($ventasSub, 'tv', 'tv.cliente_id', '=', 'clientes.id')
            ->leftJoinSub($devolucionesSub, 'td', 'td.cliente_id', '=', 'clientes.id');

        if ($ocultarInactivos) {
            $query->where('clientes.es_inactivo', false);
        }

        if ($busqueda !== '') {
            $query->where(function (Builder $q) use ($busqueda) {
                $q->where('clientes.numero_cliente', 'like', '%'.$busqueda.'%')
                    ->orWhere('clientes.nombre', 'like', '%'.$busqueda.'%');
            });
        }

        $listaVigenteId = (int) $request->get('lista_vigente_id', 0);
        if ($listaVigenteId > 0) {
            $query->where(self::ALIAS_RESUMEN.'.lista_vigente_id', $listaVigenteId);
        }

        $listaCalculadaId = (int) $request->get('lista_calculada_id', 0);
        if ($listaCalculadaId > 0) {
            $query->where(self::ALIAS_RESUMEN.'.clasificacion_mes_id', $listaCalculadaId);
        }

        $this->aplicarFiltroMontos($query, $request);

        $this->aplicarFiltroRapido($query, $periodo, $rapido);

        if ($resultadosFiltro !== []) {
            $ids = $this->clienteIdsPorResultado($periodo, $request, $resultadosFiltro, $ocultarInactivos, $busqueda, $rapido);
            if ($ids === []) {
                return $this->paginacionVacia($perPage);
            }
            $query->whereIn('clientes.id', $ids);
        }

        $this->aplicarOrden($query, $orden, $direccion);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();
        $coleccion = $paginator->getCollection();
        $clienteIds = $coleccion->pluck('id')->filter()->values();

        $resumenes = EscalonamientoResumenCliente::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('cliente_id', $clienteIds)
            ->with([
                'clasificacionMes:id,nombre',
                'listaBase:id,nombre',
                'listaVigente:id,nombre',
            ])
            ->get()
            ->keyBy('cliente_id');

        $clientesPorId = Cliente::query()
            ->whereIn('id', $clienteIds)
            ->with('listaDescuento:id,nombre,participa_escalonamiento,activo')
            ->get()
            ->keyBy('id');

        $totales = $this->totalesMovimientoPorCliente($periodo, $clienteIds);
        $devolucionesAplicadas = $this->devolucionesAplicadasPorCliente($periodo, $clienteIds);
        $incidenciasAbiertas = EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'abierta')
            ->whereIn('cliente_id', $clienteIds)
            ->whereNotIn('codigo', ['exclusion_lealtad'])
            ->selectRaw('cliente_id, count(*) as total')
            ->groupBy('cliente_id')
            ->pluck('total', 'cliente_id');

        $filas = $clienteIds->map(function (int $clienteId) use (
            $periodo,
            $nombresLista,
            $resumenes,
            $clientesPorId,
            $totales,
            $devolucionesAplicadas,
            $incidenciasAbiertas,
        ) {
            $cliente = $clientesPorId->get($clienteId);
            if (! $cliente) {
                return null;
            }

            $resumen = $resumenes->get($clienteId);
            $compras = $totales[$clienteId]['compras'] ?? '0.00';
            $devoluciones = $devolucionesAplicadas[$clienteId] ?? '0.00';
            $neto = $resumen ? (string) $resumen->acumulado : bcsub($compras, $devoluciones, 2);

            $proyeccion = $this->evaluarLista->proyeccionLectura($periodo, $cliente, $resumen, $neto);
            $participa = (bool) ($proyeccion['participa'] ?? false);
            $bloqueo = (bool) $cliente->lista_bloqueada;
            $listaSiguienteId = $proyeccion['lista_siguiente_propuesta_id'] ?? null;
            $listaOperativaId = $cliente->lista_actual_id ? (int) $cliente->lista_actual_id : null;
            $listaVigenteIdProy = $proyeccion['lista_vigente_id'] ?? null;

            $tuvoActividad = $this->inactividad->tuvoActividadCompra($periodo, $cliente);
            $inact = $this->inactividad->evaluarContador($periodo, $cliente, $tuvoActividad);
            $proponeInactivo = (bool) ($inact['propone_inactivo'] ?? false);

            if ($participa && ! $bloqueo && $proponeInactivo) {
                try {
                    $listaSiguienteId = $this->listaPg->id();
                } catch (\Illuminate\Validation\ValidationException) {
                    // mantiene proyección previa
                }
            }

            $resultado = $this->resolverResultado->resolver($periodo, [
                'bloqueo' => $bloqueo,
                'participa' => $participa,
                'incidencias_abiertas' => (int) ($incidenciasAbiertas[$clienteId] ?? 0),
                'lista_operativa_id' => $listaOperativaId,
                'lista_vigente_id' => $listaVigenteIdProy ? (int) $listaVigenteIdProy : null,
                'lista_siguiente_propuesta_id' => $listaSiguienteId ? (int) $listaSiguienteId : null,
                'cumple_mantenimiento' => (bool) ($proyeccion['cumple_mantenimiento'] ?? false),
                'propone_inactivo' => $proponeInactivo,
            ]);

            $listaVigenteNombre = $resumen?->listaVigente?->nombre
                ?? ($listaVigenteIdProy ? ($nombresLista[$listaVigenteIdProy] ?? null) : null);
            $listaCalculadaNombre = $resumen?->clasificacionMes?->nombre
                ?? ($proyeccion['clasificacion_mes_id'] ? ($nombresLista[$proyeccion['clasificacion_mes_id']] ?? null) : null);

            return [
                'cliente_id' => $clienteId,
                'numero_cliente' => $cliente->numero_cliente,
                'nombre' => $cliente->nombre,
                'lista_operativa' => $cliente->listaDescuento?->nombre,
                'lista_vigente' => $listaVigenteNombre,
                'lista_calculada' => $listaCalculadaNombre,
                'lista_base' => $resumen?->listaBase?->nombre,
                'lista_siguiente' => $listaSiguienteId ? ($nombresLista[$listaSiguienteId] ?? null) : null,
                'ventas' => $compras,
                'devoluciones_aplicadas' => $devoluciones,
                'venta_neta' => $neto,
                'resultado' => $resultado,
                'participa' => $participa,
                'bloqueo' => $bloqueo,
                'es_inactivo' => (bool) $cliente->es_inactivo,
                'meses_sin_compra' => (int) ($cliente->escalonamiento_meses_sin_compra ?? 0),
                'propone_inactivo' => $proponeInactivo,
                'incidencias_abiertas' => (int) ($incidenciasAbiertas[$clienteId] ?? 0),
                'diverge_operativa' => $listaOperativaId && $listaVigenteIdProy
                    && $listaOperativaId !== (int) $listaVigenteIdProy,
            ];
        })->filter()->values();

        return [
            'data' => $filas->all(),
            'paginacion' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    public function exportarCsv(EscalonamientoPeriodo $periodo, Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->merge(['per_page' => 100000]);
        $resultado = $this->listar($periodo, $request);
        $nombre = 'escalonamiento-clientes-'.$periodo->etiquetaMes().'.csv';

        return response()->streamDownload(function () use ($resultado, $periodo) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'numero_cliente', 'nombre', 'lista_operativa', 'ventas', 'devoluciones_aplicadas',
                'venta_neta', 'lista_vigente', 'lista_calculada', 'lista_siguiente', 'resultado', 'periodo',
            ]);
            foreach ($resultado['data'] as $fila) {
                fputcsv($out, [
                    $fila['numero_cliente'],
                    $fila['nombre'],
                    $fila['lista_operativa'],
                    $fila['ventas'],
                    $fila['devoluciones_aplicadas'],
                    $fila['venta_neta'],
                    $fila['lista_vigente'],
                    $fila['lista_calculada'],
                    $fila['lista_siguiente'],
                    $fila['resultado'],
                    $periodo->etiquetaMes(),
                ]);
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    private function parseResultadosFiltro(Request $request): array
    {
        $raw = $request->get('resultado');
        if (is_string($raw) && $raw !== '') {
            $raw = explode(',', $raw);
        }
        if (! is_array($raw)) {
            return [];
        }

        $validos = $this->resolverResultado->codigosValidos();

        return array_values(array_filter(array_map('strval', $raw), fn (string $c) => in_array($c, $validos, true)));
    }

    private function subconsultaVentas(EscalonamientoPeriodo $periodo): Builder
    {
        return EscalonamientoMovimiento::query()
            ->select('cliente_id')
            ->selectRaw('COALESCE(SUM(efecto), 0) as ventas')
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('operacion', ['remision', 'revision'])
            ->groupBy('cliente_id');
    }

    private function subconsultaDevoluciones(EscalonamientoPeriodo $periodo): Builder
    {
        return EscalonamientoAplicacionDevolucion::query()
            ->join('documentos_venta as dve', 'dve.id', '=', 'escalonamiento_aplicaciones_devolucion.documento_devolucion_id')
            ->select('dve.cliente_id')
            ->selectRaw('COALESCE(SUM(escalonamiento_aplicaciones_devolucion.importe), 0) as devoluciones')
            ->where('escalonamiento_aplicaciones_devolucion.escalonamiento_periodo_id', $periodo->id)
            ->where('escalonamiento_aplicaciones_devolucion.estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
            ->groupBy('dve.cliente_id');
    }

    private function aplicarFiltroMontos(Builder $query, Request $request): void
    {
        $ventasMin = $this->montoFiltro($request->get('ventas_min'));
        $ventasMax = $this->montoFiltro($request->get('ventas_max'));
        $devMin = $this->montoFiltro($request->get('devoluciones_min'));
        $devMax = $this->montoFiltro($request->get('devoluciones_max'));

        if ($ventasMin !== null) {
            $query->whereRaw('COALESCE(tv.ventas, 0) >= ?', [$ventasMin]);
        }
        if ($ventasMax !== null) {
            $query->whereRaw('COALESCE(tv.ventas, 0) <= ?', [$ventasMax]);
        }
        if ($devMin !== null) {
            $query->whereRaw('COALESCE(td.devoluciones, 0) >= ?', [$devMin]);
        }
        if ($devMax !== null) {
            $query->whereRaw('COALESCE(td.devoluciones, 0) <= ?', [$devMax]);
        }
    }

    private function montoFiltro(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (! is_numeric($valor)) {
            return null;
        }

        return bcadd((string) $valor, '0', 2);
    }

    private function aplicarFiltroRapido(Builder $query, EscalonamientoPeriodo $periodo, string $rapido): void
    {
        if ($rapido === 'sin_compras') {
            $query->where(function (Builder $q) {
                $q->whereNull(self::ALIAS_RESUMEN.'.id')
                    ->orWhere(self::ALIAS_RESUMEN.'.acumulado', '<=', 0);
            });
        }

        if ($rapido === 'con_pendientes') {
            $query->where(function (Builder $q) use ($periodo) {
                $q->whereIn('clientes.id', $this->clienteIdsConIncidenciasAbiertas($periodo))
                    ->orWhereIn('clientes.id', $this->clienteIdsConDevolucionPendiente($periodo));
            });
        }

        if ($rapido === 'no_participantes') {
            $query->whereHas('listaDescuento', fn (Builder $q) => $q->where('participa_escalonamiento', false));
        }

        if ($rapido === 'con_cambios') {
            $query->where(function (Builder $q) {
                $q->whereColumn(self::ALIAS_RESUMEN.'.clasificacion_mes_id', '!=', self::ALIAS_RESUMEN.'.lista_vigente_id')
                    ->orWhereColumn('clientes.lista_actual_id', '!=', self::ALIAS_RESUMEN.'.lista_vigente_id');
            });
        }
    }

    private function aplicarOrden(Builder $query, string $orden, string $direccion): void
    {
        match ($orden) {
            'ventas' => $query->orderByRaw('COALESCE(tv.ventas, 0) '.$direccion),
            'devoluciones' => $query->orderByRaw('COALESCE(td.devoluciones, 0) '.$direccion),
            'neto' => $query->orderByRaw('COALESCE('.self::ALIAS_RESUMEN.'.acumulado, 0) '.$direccion),
            'nombre' => $query->orderBy('clientes.nombre', $direccion),
            'numero' => $this->ordenPorNumeroCliente($query, $direccion),
            default => $this->ordenPorNumeroCliente($query, 'asc'),
        };
    }

    /**
     * Misma lógica que Cliente::scopeOrdenarPorNumeroCliente, con tabla calificada por los JOIN.
     */
    private function ordenPorNumeroCliente(Builder $query, string $direccion): void
    {
        $dir = strtolower($direccion) === 'desc' ? 'desc' : 'asc';
        $driver = $query->getConnection()->getDriverName();

        $cast = match ($driver) {
            'mysql', 'mariadb' => 'CAST(clientes.numero_cliente AS UNSIGNED)',
            default => 'CAST(clientes.numero_cliente AS INTEGER)',
        };

        $query->orderByRaw("{$cast} {$dir}")
            ->orderBy('clientes.numero_cliente', $dir);
    }

    /**
     * @param  list<string>  $resultadosFiltro
     * @return list<int>
     */
    private function clienteIdsPorResultado(
        EscalonamientoPeriodo $periodo,
        Request $request,
        array $resultadosFiltro,
        bool $ocultarInactivos,
        string $busqueda,
        string $rapido,
    ): array {
        $ventasSub = $this->subconsultaVentas($periodo);
        $devolucionesSub = $this->subconsultaDevoluciones($periodo);

        $base = Cliente::query()
            ->select('clientes.id')
            ->leftJoin('escalonamiento_resumenes_cliente as '.self::ALIAS_RESUMEN, function (JoinClause $join) use ($periodo) {
                $join->on('clientes.id', '=', self::ALIAS_RESUMEN.'.cliente_id')
                    ->where(self::ALIAS_RESUMEN.'.escalonamiento_periodo_id', '=', $periodo->id);
            })
            ->leftJoinSub($ventasSub, 'tv', 'tv.cliente_id', '=', 'clientes.id')
            ->leftJoinSub($devolucionesSub, 'td', 'td.cliente_id', '=', 'clientes.id');

        if ($ocultarInactivos) {
            $base->where('clientes.es_inactivo', false);
        }
        if ($busqueda !== '') {
            $base->where(function (Builder $q) use ($busqueda) {
                $q->where('clientes.numero_cliente', 'like', '%'.$busqueda.'%')
                    ->orWhere('clientes.nombre', 'like', '%'.$busqueda.'%');
            });
        }
        $listaVigenteId = (int) $request->get('lista_vigente_id', 0);
        if ($listaVigenteId > 0) {
            $base->where(self::ALIAS_RESUMEN.'.lista_vigente_id', $listaVigenteId);
        }
        $listaCalculadaId = (int) $request->get('lista_calculada_id', 0);
        if ($listaCalculadaId > 0) {
            $base->where(self::ALIAS_RESUMEN.'.clasificacion_mes_id', $listaCalculadaId);
        }
        $this->aplicarFiltroMontos($base, $request);
        $this->aplicarFiltroRapido($base, $periodo, $rapido);

        $ids = [];
        $base->orderBy('clientes.id')->chunkById(200, function (Collection $clientes) use (
            $periodo,
            $resultadosFiltro,
            &$ids,
        ) {
            $chunkIds = $clientes->pluck('id');
            $resumenes = EscalonamientoResumenCliente::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->whereIn('cliente_id', $chunkIds)
                ->with(['clasificacionMes:id,nombre', 'listaVigente:id,nombre'])
                ->get()
                ->keyBy('cliente_id');

            $clientesFull = Cliente::query()
                ->whereIn('id', $chunkIds)
                ->with('listaDescuento:id,nombre,participa_escalonamiento')
                ->get();

            $totales = $this->totalesMovimientoPorCliente($periodo, $chunkIds);
            $devolucionesAplicadas = $this->devolucionesAplicadasPorCliente($periodo, $chunkIds);
            $incidenciasAbiertas = EscalonamientoIncidencia::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('estado', 'abierta')
                ->whereIn('cliente_id', $chunkIds)
                ->whereNotIn('codigo', ['exclusion_lealtad'])
                ->selectRaw('cliente_id, count(*) as total')
                ->groupBy('cliente_id')
                ->pluck('total', 'cliente_id');

            foreach ($clientesFull as $cliente) {
                $resumen = $resumenes->get($cliente->id);
                $compras = $totales[$cliente->id]['compras'] ?? '0.00';
                $devoluciones = $devolucionesAplicadas[$cliente->id] ?? '0.00';
                $neto = $resumen ? (string) $resumen->acumulado : bcsub($compras, $devoluciones, 2);
                $proyeccion = $this->evaluarLista->proyeccionLectura($periodo, $cliente, $resumen, $neto);
                $participa = (bool) ($proyeccion['participa'] ?? false);
                $bloqueo = (bool) $cliente->lista_bloqueada;
                $listaSiguienteId = $proyeccion['lista_siguiente_propuesta_id'] ?? null;
                $listaOperativaId = $cliente->lista_actual_id ? (int) $cliente->lista_actual_id : null;
                $listaVigenteIdProy = $proyeccion['lista_vigente_id'] ?? null;
                $tuvoActividad = $this->inactividad->tuvoActividadCompra($periodo, $cliente);
                $inact = $this->inactividad->evaluarContador($periodo, $cliente, $tuvoActividad);
                $proponeInactivo = (bool) ($inact['propone_inactivo'] ?? false);

                if ($participa && ! $bloqueo && $proponeInactivo) {
                    try {
                        $listaSiguienteId = $this->listaPg->id();
                    } catch (\Illuminate\Validation\ValidationException) {
                    }
                }

                $codigo = $this->resolverResultado->resolver($periodo, [
                    'bloqueo' => $bloqueo,
                    'participa' => $participa,
                    'incidencias_abiertas' => (int) ($incidenciasAbiertas[$cliente->id] ?? 0),
                    'lista_operativa_id' => $listaOperativaId,
                    'lista_vigente_id' => $listaVigenteIdProy ? (int) $listaVigenteIdProy : null,
                    'lista_siguiente_propuesta_id' => $listaSiguienteId ? (int) $listaSiguienteId : null,
                    'cumple_mantenimiento' => (bool) ($proyeccion['cumple_mantenimiento'] ?? false),
                    'propone_inactivo' => $proponeInactivo,
                ]);

                if (in_array($codigo, $resultadosFiltro, true)) {
                    $ids[] = (int) $cliente->id;
                }
            }
        }, 'clientes.id', 'id');

        return $ids;
    }

    /**
     * @return array{data: list<mixed>, paginacion: array<string, mixed>}
     */
    private function paginacionVacia(int $perPage): array
    {
        return [
            'data' => [],
            'paginacion' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];
    }

    /**
     * @return array<int, array{compras: string, devoluciones: string}>
     */
    private function totalesMovimientoPorCliente(EscalonamientoPeriodo $periodo, Collection $clienteIds): array
    {
        if ($clienteIds->isEmpty()) {
            return [];
        }

        $movimientos = EscalonamientoMovimiento::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->whereIn('cliente_id', $clienteIds)
            ->whereIn('operacion', ['remision', 'revision'])
            ->get(['cliente_id', 'efecto']);

        $out = [];
        foreach ($movimientos as $mov) {
            $id = (int) $mov->cliente_id;
            $out[$id]['compras'] = bcadd($out[$id]['compras'] ?? '0.00', bcadd((string) $mov->efecto, '0', 2), 2);
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function devolucionesAplicadasPorCliente(EscalonamientoPeriodo $periodo, Collection $clienteIds): array
    {
        if ($clienteIds->isEmpty()) {
            return [];
        }

        return EscalonamientoAplicacionDevolucion::query()
            ->where('escalonamiento_aplicaciones_devolucion.escalonamiento_periodo_id', $periodo->id)
            ->where('escalonamiento_aplicaciones_devolucion.estado', EscalonamientoAplicacionDevolucion::ESTADO_ACTIVA)
            ->join('documentos_venta as dve', 'dve.id', '=', 'escalonamiento_aplicaciones_devolucion.documento_devolucion_id')
            ->whereIn('dve.cliente_id', $clienteIds)
            ->selectRaw('dve.cliente_id, COALESCE(SUM(escalonamiento_aplicaciones_devolucion.importe), 0) as total')
            ->groupBy('dve.cliente_id')
            ->pluck('total', 'cliente_id')
            ->map(fn ($v) => bcadd((string) $v, '0', 2))
            ->all();
    }

    /**
     * @return list<int>
     */
    private function clienteIdsConIncidenciasAbiertas(EscalonamientoPeriodo $periodo): array
    {
        return EscalonamientoIncidencia::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('estado', 'abierta')
            ->whereNotIn('codigo', ['exclusion_lealtad'])
            ->distinct()
            ->pluck('cliente_id')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function clienteIdsConDevolucionPendiente(EscalonamientoPeriodo $periodo): array
    {
        return \App\Models\Escalonamiento\DocumentoVenta::query()
            ->where('escalonamiento_periodo_id', $periodo->id)
            ->where('tipo', 'devolucion')
            ->where('estado', 'pendiente_de_vinculacion')
            ->distinct()
            ->pluck('cliente_id')
            ->filter()
            ->values()
            ->all();
    }
}
