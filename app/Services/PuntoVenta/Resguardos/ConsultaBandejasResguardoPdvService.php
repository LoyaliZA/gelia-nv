<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Contracts\PuntoVenta\ResuelvePlazosCustodiaResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\AntiguedadOperativaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\BandejaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ConsultaBandejasResguardoPdvService
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly ResuelvePlazosCustodiaResguardoPdv $plazos,
        private readonly CalcularAntiguedadOperativaResguardoPdvService $antiguedad,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     bandeja: string,
     *     resguardos: LengthAwarePaginator,
     *     metricas: array<string, int>,
     *     filtros: array<string, mixed>
     * }
     */
    public function payload(User $user, array $filtros = []): array
    {
        $bandeja = $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);
        $filtrosNormalizados = $this->normalizarFiltros($filtros, $bandeja);

        return [
            'bandeja' => $bandeja,
            'resguardos' => $this->listar($user, $filtrosNormalizados),
            'metricas' => $this->metricas($user, $filtrosNormalizados),
            'filtros' => $filtrosNormalizados,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(User $user, array $filtros = []): LengthAwarePaginator
    {
        $bandeja = $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);
        $filtrosNormalizados = $this->normalizarFiltros($filtros, $bandeja);
        $query = $this->queryBandeja($user, $bandeja);
        $this->aplicarFiltrosComunes($query, $user, $filtrosNormalizados, $bandeja);
        $this->aplicarOrdenBandeja($query, $bandeja);

        $perPage = (int) ($filtrosNormalizados['per_page'] ?? self::PER_PAGE);

        return $query
            ->paginate(max(1, min($perPage, 50)))
            ->withQueryString()
            ->through(fn (ResguardoPdv $resguardo) => $this->serializarResguardo($resguardo));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, int>
     */
    public function metricas(User $user, array $filtros = []): array
    {
        $filtrosBase = $this->normalizarFiltros($filtros, null);
        unset($filtrosBase['bandeja'], $filtrosBase['estado'], $filtrosBase['antiguedad'], $filtrosBase['page']);

        $metricas = [];
        foreach (BandejaResguardoPdv::valores() as $bandeja) {
            $query = $this->queryBandeja($user, $bandeja);
            $this->aplicarFiltrosComunes($query, $user, $filtrosBase, $bandeja, aplicarAntiguedad: false, excluirVencidos: false);
            $metricas[$bandeja] = $query->count();
        }

        return array_merge($metricas, $this->metricasAntiguedad($user, $filtrosBase));
    }

    public function antiguedadConfigurada(): bool
    {
        $global = $this->plazos->obtenerGlobal();

        return $global !== null && $global['activo'];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function contarParaExportacion(User $user, array $filtros = []): int
    {
        $bandeja = $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);
        $filtrosNormalizados = $this->normalizarFiltros($filtros, $bandeja);
        unset($filtrosNormalizados['page'], $filtrosNormalizados['per_page']);

        $query = $this->queryBandeja($user, $bandeja);
        $this->aplicarFiltrosComunes($query, $user, $filtrosNormalizados, $bandeja);

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function filasParaExportacion(User $user, array $filtros = []): array
    {
        $bandeja = $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);
        $filtrosNormalizados = $this->normalizarFiltros($filtros, $bandeja);
        unset($filtrosNormalizados['page'], $filtrosNormalizados['per_page']);

        $query = $this->queryBandeja($user, $bandeja);
        $this->aplicarFiltrosComunes($query, $user, $filtrosNormalizados, $bandeja);
        $this->aplicarOrdenBandeja($query, $bandeja);

        $filas = [];

        (clone $query)
            ->chunkById(200, function (Collection $resguardos) use (&$filas, $bandeja) {
                foreach ($resguardos as $resguardo) {
                    $filas[] = $this->serializarResguardoParaExportacion($resguardo, $bandeja);
                }
            });

        return $filas;
    }

    private function queryBandeja(User $user, string $bandeja): Builder
    {
        $query = $this->queryAutorizada($user);

        match ($bandeja) {
            BandejaResguardoPdv::POR_RECIBIR => $query->where('estado', ResguardoPdv::ESTADO_PENDIENTE_RECEPCION),
            BandejaResguardoPdv::EN_CUSTODIA => $query->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA),
            BandejaResguardoPdv::INCIDENCIAS => $query->whereHas(
                'incidencias',
                fn (Builder $incidencias) => $incidencias->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)
            ),
            default => null,
        };

        return $query;
    }

    private function queryAutorizada(User $user): Builder
    {
        $this->alcance->asegurarConsultaPiso($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER);

        $query = ResguardoPdv::query()
            ->with([
                'sucursal:id,nombre',
                'cliente:id,numero_cliente,nombre',
                'pedido:id,folio,folio_remision,cliente_id',
            ])
            ->withCount([
                'incidencias as incidencias_abiertas_count' => fn (Builder $q) => $q
                    ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA),
            ]);

        return $this->alcance->aplicarConsultaPiso(
            $query,
            $user,
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosComunes(
        Builder $query,
        User $user,
        array $filtros,
        string $bandeja,
        bool $aplicarAntiguedad = true,
        bool $excluirVencidos = true,
    ): void {
        $this->validarSucursalFiltro($user, $filtros['sucursal_id'] ?? null);

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if ($this->antiguedadConfigurada()) {
            if ($excluirVencidos
                && $bandeja === BandejaResguardoPdv::EN_CUSTODIA
                && ! $this->alcance->tienePermisoPdv($user, PuntoVentaModulo::PERMISO_RESGUARDOS_VER_VENCIDOS)) {
                $this->restringirIdsPorEvaluacion($query, function (ResguardoPdv $resguardo) {
                    return ! $this->antiguedad->debeExcluirDeVistaPrincipal($resguardo);
                });
            }

            if ($aplicarAntiguedad && ! empty($filtros['antiguedad'])) {
                $antiguedad = (string) $filtros['antiguedad'];
                $this->restringirIdsPorEvaluacion($query, function (ResguardoPdv $resguardo) use ($antiguedad) {
                    return $this->antiguedad->coincideConFiltro($resguardo, $antiguedad);
                });
            }
        }

        if (! empty($filtros['q'])) {
            $this->aplicarBusqueda($query, (string) $filtros['q']);
        }
    }

    /**
     * @param  array<string, mixed>  $filtrosBase
     * @return array<string, int>
     */
    private function metricasAntiguedad(User $user, array $filtrosBase): array
    {
        if (! $this->antiguedadConfigurada()) {
            return [];
        }

        $metricas = [
            AntiguedadOperativaResguardoPdv::REZAGADO => 0,
            AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER => 0,
            AntiguedadOperativaResguardoPdv::VENCIDO => 0,
        ];

        $queryRezagado = $this->queryBandeja($user, BandejaResguardoPdv::POR_RECIBIR);
        $this->aplicarFiltrosComunes($queryRezagado, $user, $filtrosBase, BandejaResguardoPdv::POR_RECIBIR, aplicarAntiguedad: false, excluirVencidos: false);
        $metricas[AntiguedadOperativaResguardoPdv::REZAGADO] = $this->contarPorClasificacion(
            $queryRezagado,
            AntiguedadOperativaResguardoPdv::REZAGADO
        );

        $queryCustodia = $this->queryBandeja($user, BandejaResguardoPdv::EN_CUSTODIA);
        $this->aplicarFiltrosComunes($queryCustodia, $user, $filtrosBase, BandejaResguardoPdv::EN_CUSTODIA, aplicarAntiguedad: false, excluirVencidos: false);
        $metricas[AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER] = $this->contarPorClasificacion(
            $queryCustodia,
            AntiguedadOperativaResguardoPdv::PROXIMO_A_VENCER
        );
        $metricas[AntiguedadOperativaResguardoPdv::VENCIDO] = $this->contarPorClasificacion(
            $queryCustodia,
            AntiguedadOperativaResguardoPdv::VENCIDO
        );

        return $metricas;
    }

    private function contarPorClasificacion(Builder $query, string $clasificacion): int
    {
        $total = 0;

        (clone $query)
            ->select(['id', 'sucursal_id', 'estado', 'salida_cedis_at', 'recepcion_fisica_at', 'entrega_completada_at', 'devolucion_confirmada_at', 'vencido_repuesto_at'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $resguardos) use ($clasificacion, &$total) {
                foreach ($resguardos as $resguardo) {
                    if ($this->antiguedad->coincideConFiltro($resguardo, $clasificacion)) {
                        $total++;
                    }
                }
            });

        return $total;
    }

    /**
     * @param  callable(ResguardoPdv): bool  $acepta
     */
    private function restringirIdsPorEvaluacion(Builder $query, callable $acepta): void
    {
        $ids = (clone $query)->pluck('id');
        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $permitidos = ResguardoPdv::query()
            ->whereIn('id', $ids)
            ->get([
                'id',
                'sucursal_id',
                'estado',
                'salida_cedis_at',
                'recepcion_fisica_at',
                'entrega_completada_at',
                'devolucion_confirmada_at',
                'vencido_repuesto_at',
            ])
            ->filter($acepta)
            ->pluck('id');

        if ($permitidos->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('id', $permitidos->all());
    }

    private function validarSucursalFiltro(User $user, mixed $sucursalId): void
    {
        if ($sucursalId === null || $sucursalId === '') {
            return;
        }

        if (! is_numeric($sucursalId)) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }

        $activa = $this->alcance->sucursalActivaId($user);
        if ($activa === null || (int) $sucursalId !== $activa) {
            throw new AuthorizationException('Sucursal no autorizada.');
        }
    }

    private function aplicarBusqueda(Builder $query, string $termino): void
    {
        $termino = trim($termino);
        if ($termino === '') {
            return;
        }

        $like = '%'.$termino.'%';

        $query->where(function (Builder $q) use ($like, $termino) {
            $q->where('snapshot_folio', 'like', $like)
                ->orWhere('snapshot_cliente_nombre', 'like', $like)
                ->orWhereHas('bultos', function (Builder $bultos) use ($termino) {
                    $bultos->where('codigo_etiqueta', $termino)
                        ->orWhere('folio', 'like', '%'.$termino.'%');
                })
                ->orWhereHas('pedido', function (Builder $pedido) use ($like, $termino) {
                    $pedido->where('folio', 'like', $like)
                        ->orWhere('folio_remision', 'like', $like);

                    if (is_numeric($termino)) {
                        $pedido->orWhere('id', (int) $termino);
                    }
                })
                ->orWhereHas('cliente', function (Builder $cliente) use ($like, $termino) {
                    $cliente->where('nombre', 'like', $like);

                    if (is_numeric($termino)) {
                        $cliente->orWhere('numero_cliente', 'like', $like);
                    }
                });
        });
    }

    private function aplicarOrdenBandeja(Builder $query, string $bandeja): void
    {
        match ($bandeja) {
            BandejaResguardoPdv::POR_RECIBIR => $query
                ->orderBy('salida_cedis_at')
                ->orderBy('id'),
            BandejaResguardoPdv::EN_CUSTODIA => $query
                ->orderBy('recepcion_fisica_at')
                ->orderBy('id'),
            BandejaResguardoPdv::INCIDENCIAS => $query
                ->withMax(
                    ['incidencias as ultima_incidencia_abierta_at' => fn (Builder $q) => $q
                        ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)],
                    'reportado_at'
                )
                ->orderByDesc('ultima_incidencia_abierta_at')
                ->orderBy('id'),
            default => $query->orderByDesc('updated_at')->orderBy('id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardo(ResguardoPdv $resguardo): array
    {
        $evaluacion = $this->antiguedadConfigurada()
            ? $this->antiguedad->evaluar($resguardo)
            : [
                'clasificaciones' => $this->antiguedad->clasificacionesVacias(),
                'fecha_limite_custodia' => null,
                'fecha_limite_rezago' => null,
                'plazos_snapshot' => null,
            ];

        $clasificacionesEtiquetas = [];
        foreach ($evaluacion['clasificaciones'] as $clave => $activa) {
            if ($activa) {
                $clasificacionesEtiquetas[] = EtiquetasResguardoPdv::antiguedades()[$clave] ?? $clave;
            }
        }

        return [
            'id' => $resguardo->id,
            'estado' => $resguardo->estado,
            'pedido_bma_id' => $resguardo->pedido_bma_id,
            'snapshot_folio' => $resguardo->snapshot_folio,
            'snapshot_cliente_nombre' => $resguardo->snapshot_cliente_nombre,
            'cantidad_bultos_esperada' => $resguardo->cantidad_bultos_esperada,
            'salida_cedis_at' => $resguardo->salida_cedis_at?->toIso8601String(),
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at?->toIso8601String(),
            'entrega_bloqueada' => $resguardo->entrega_bloqueada,
            'incidencias_abiertas_count' => (int) ($resguardo->incidencias_abiertas_count ?? 0),
            'clasificaciones' => $evaluacion['clasificaciones'],
            'clasificaciones_etiquetas' => $clasificacionesEtiquetas,
            'fecha_limite_custodia' => $evaluacion['fecha_limite_custodia'],
            'fecha_limite_rezago' => $evaluacion['fecha_limite_rezago'],
            'sucursal' => $resguardo->sucursal ? [
                'id' => $resguardo->sucursal->id,
                'nombre' => $resguardo->sucursal->nombre,
            ] : null,
            'cliente' => $resguardo->cliente ? [
                'id' => $resguardo->cliente->id,
                'numero_cliente' => $resguardo->cliente->numero_cliente,
                'nombre' => $resguardo->cliente->nombre,
            ] : null,
            'pedido' => $resguardo->pedido ? [
                'id' => $resguardo->pedido->id,
                'folio' => $resguardo->pedido->folio,
                'folio_remision' => $resguardo->pedido->folio_remision,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltros(array $filtros, ?string $bandejaForzada): array
    {
        $bandeja = $bandejaForzada ?? $this->normalizarBandeja($filtros['bandeja'] ?? BandejaResguardoPdv::POR_RECIBIR);

        return [
            'bandeja' => $bandeja,
            'q' => isset($filtros['q']) ? trim((string) $filtros['q']) : null,
            'estado' => $filtros['estado'] ?? null,
            'antiguedad' => $filtros['antiguedad'] ?? null,
            'sucursal_id' => isset($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null,
            'page' => isset($filtros['page']) ? (int) $filtros['page'] : null,
            'per_page' => isset($filtros['per_page']) ? (int) $filtros['per_page'] : self::PER_PAGE,
        ];
    }

    private function normalizarBandeja(string $bandeja): string
    {
        return in_array($bandeja, BandejaResguardoPdv::valores(), true)
            ? $bandeja
            : BandejaResguardoPdv::POR_RECIBIR;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarResguardoParaExportacion(ResguardoPdv $resguardo, string $bandeja): array
    {
        $serializado = $this->serializarResguardo($resguardo);

        return [
            'id' => $serializado['id'],
            'bandeja' => EtiquetasResguardoPdv::bandejas()[$bandeja] ?? $bandeja,
            'folio' => $serializado['snapshot_folio'],
            'estado' => EtiquetasResguardoPdv::etiquetaEstado((string) $serializado['estado']),
            'sucursal' => $serializado['sucursal']['nombre'] ?? '',
            'numero_cliente' => $serializado['cliente']['numero_cliente'] ?? '',
            'cliente' => $serializado['snapshot_cliente_nombre'] ?? ($serializado['cliente']['nombre'] ?? ''),
            'pedido_id' => $serializado['pedido']['id'] ?? $serializado['pedido_bma_id'] ?? '',
            'pedido_folio' => $serializado['pedido']['folio'] ?? '',
            'pedido_remision' => $serializado['pedido']['folio_remision'] ?? '',
            'bultos_esperados' => $serializado['cantidad_bultos_esperada'],
            'incidencias_abiertas' => $serializado['incidencias_abiertas_count'],
            'salida_cedis' => $this->formatearFechaExportacion($serializado['salida_cedis_at'] ?? null),
            'recepcion_fisica' => $this->formatearFechaExportacion($serializado['recepcion_fisica_at'] ?? null),
            'clasificaciones' => implode('; ', $serializado['clasificaciones_etiquetas'] ?? []),
            'fecha_limite_custodia' => $this->formatearFechaExportacion($serializado['fecha_limite_custodia'] ?? null),
            'fecha_limite_rezago' => $this->formatearFechaExportacion($serializado['fecha_limite_rezago'] ?? null),
            'entrega_bloqueada' => ! empty($serializado['entrega_bloqueada']) ? 'Sí' : 'No',
        ];
    }

    private function formatearFechaExportacion(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($valor)->timezone(config('app.timezone'))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $valor;
        }
    }
}
