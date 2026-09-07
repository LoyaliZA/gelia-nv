<?php

namespace App\Services\PuntoVenta\Reportes\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Support\PuntoVenta\Reportes\MetricaResguardoPdvIds;
use App\Support\PuntoVenta\Reportes\PercentilesPdv;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CalcularMetricasReporteResguardoPdvService
{
    public function __construct(
        private readonly NormalizarFiltrosReporteResguardoPdvService $normalizarFiltros,
        private readonly AplicarFiltrosReporteResguardoPdvQuery $filtrosQuery,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   corte_reporte_at: string,
     *   rango: array{desde: string, hasta: string},
     *   filtros: array<string, mixed>,
     *   metricas: array<string, array<string, mixed>>,
     *   por_sucursal: array<string, array<string, array<string, mixed>>>
     * }
     */
    public function ejecutar(User $user, array $filtros = []): array
    {
        $normalizados = $this->normalizarFiltros->normalizar($filtros);
        $this->filtrosQuery->asegurarAcceso($user);

        $metricas = $this->calcularMetricas($user, $normalizados);
        $porSucursal = $this->calcularDesglosePorSucursal($user, $normalizados);

        return [
            'corte_reporte_at' => $normalizados['corte_reporte_at']->toIso8601String(),
            'rango' => [
                'desde' => $normalizados['desde']->toIso8601String(),
                'hasta' => $normalizados['hasta']->toIso8601String(),
            ],
            'filtros' => $this->normalizarFiltros->paraPayload($normalizados),
            'metricas' => $metricas,
            'por_sucursal' => $porSucursal,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function calcularMetricas(User $user, array $filtros): array
    {
        $queryBase = $this->queryResguardosFiltrado($user, $filtros);

        $clasificaciones = $this->filtrosQuery->contarClasificaciones($queryBase, $filtros['corte_reporte_at']);

        return [
            MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR => $this->metricaEntera(
                MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR,
                $this->contarEstadoAlCorte($queryBase, ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)
            ),
            MetricaResguardoPdvIds::R_02_EN_CUSTODIA => $this->metricaEntera(
                MetricaResguardoPdvIds::R_02_EN_CUSTODIA,
                $this->contarEstadoAlCorte($queryBase, ResguardoPdv::ESTADO_EN_CUSTODIA)
            ),
            MetricaResguardoPdvIds::R_03_INCIDENCIAS_ABIERTAS => $this->metricaEntera(
                MetricaResguardoPdvIds::R_03_INCIDENCIAS_ABIERTAS,
                $this->contarIncidenciasAbiertasAlCorte($queryBase, $filtros['corte_reporte_at'])
            ),
            MetricaResguardoPdvIds::R_04_REZAGADOS => $this->metricaEntera(
                MetricaResguardoPdvIds::R_04_REZAGADOS,
                $clasificaciones['rezagado']
            ),
            MetricaResguardoPdvIds::R_05_PROXIMOS_VENCER => $this->metricaEntera(
                MetricaResguardoPdvIds::R_05_PROXIMOS_VENCER,
                $clasificaciones['proximo_a_vencer']
            ),
            MetricaResguardoPdvIds::R_06_VENCIDOS => $this->metricaEntera(
                MetricaResguardoPdvIds::R_06_VENCIDOS,
                $clasificaciones['vencido']
            ),
            MetricaResguardoPdvIds::R_07_TIEMPO_RECEPCION => $this->metricaDuracion(
                MetricaResguardoPdvIds::R_07_TIEMPO_RECEPCION,
                $this->duracionesRecepcion($queryBase, $filtros)
            ),
            MetricaResguardoPdvIds::R_08_TIEMPO_CUSTODIA => $this->metricaDuracion(
                MetricaResguardoPdvIds::R_08_TIEMPO_CUSTODIA,
                $this->duracionesCustodia($queryBase, $filtros)
            ),
            MetricaResguardoPdvIds::R_09_TASA_ENTREGA => $this->metricaTasaEntrega($queryBase, $filtros),
            MetricaResguardoPdvIds::R_10_TASA_INCIDENCIA => $this->metricaTasaIncidencia($user, $filtros),
            MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO => $this->metricaEntera(
                MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO,
                $this->contarEventosRecepcion($user, $filtros)
            ),
            MetricaResguardoPdvIds::R_12_DEVOLUCIONES_RANGO => $this->metricaEntera(
                MetricaResguardoPdvIds::R_12_DEVOLUCIONES_RANGO,
                $this->contarEventosDevolucion($user, $filtros)
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function calcularDesglosePorSucursal(User $user, array $filtros): array
    {
        if (! empty($filtros['sucursal_id'])) {
            return [];
        }

        $desglose = [];
        foreach ($this->filtrosQuery->idsSucursalesDesglose($user, $filtros) as $sucursalId) {
            $filtrosSucursal = array_merge($filtros, ['sucursal_id' => $sucursalId]);
            $queryBase = $this->queryResguardosFiltrado($user, $filtrosSucursal);

            $desglose[(string) $sucursalId] = [
                MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR => $this->metricaEntera(
                    MetricaResguardoPdvIds::R_01_PENDIENTES_RECIBIR,
                    $this->contarEstadoAlCorte($queryBase, ResguardoPdv::ESTADO_PENDIENTE_RECEPCION)
                ),
                MetricaResguardoPdvIds::R_02_EN_CUSTODIA => $this->metricaEntera(
                    MetricaResguardoPdvIds::R_02_EN_CUSTODIA,
                    $this->contarEstadoAlCorte($queryBase, ResguardoPdv::ESTADO_EN_CUSTODIA)
                ),
                MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO => $this->metricaEntera(
                    MetricaResguardoPdvIds::R_11_RECEPCIONES_RANGO,
                    $this->contarEventosRecepcion($user, $filtrosSucursal)
                ),
                MetricaResguardoPdvIds::R_12_DEVOLUCIONES_RANGO => $this->metricaEntera(
                    MetricaResguardoPdvIds::R_12_DEVOLUCIONES_RANGO,
                    $this->contarEventosDevolucion($user, $filtrosSucursal)
                ),
            ];
        }

        return $desglose;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryResguardosFiltrado(User $user, array $filtros): Builder
    {
        $query = $this->filtrosQuery->queryResguardos($user);

        return $this->filtrosQuery->aplicarFiltrosResguardo($query, $user, $filtros);
    }

    private function contarEstadoAlCorte(Builder $queryBase, string $estado): int
    {
        return (clone $queryBase)
            ->where('estado', $estado)
            ->count();
    }

    private function contarIncidenciasAbiertasAlCorte(Builder $queryBase, Carbon $corte): int
    {
        return (clone $queryBase)
            ->whereHas('incidencias', function (Builder $incidencias) use ($corte) {
                $incidencias
                    ->where('estado', ResguardoPdvIncidencia::ESTADO_ABIERTA)
                    ->where('reportado_at', '<=', $corte)
                    ->where(function (Builder $q) use ($corte) {
                        $q->whereNull('autorizado_at')
                            ->orWhere('autorizado_at', '>', $corte);
                    });
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{duraciones: list<int>, en_curso: int}
     */
    private function duracionesRecepcion(Builder $queryBase, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $duraciones = [];
        $enCurso = 0;

        (clone $queryBase)
            ->whereNotNull('salida_cedis_at')
            ->whereNotNull('recepcion_fisica_at')
            ->where('recepcion_fisica_at', '>=', $desde)
            ->where('recepcion_fisica_at', '<', $hasta)
            ->select(['id', 'salida_cedis_at', 'recepcion_fisica_at'])
            ->orderBy('id')
            ->chunkById(200, function ($resguardos) use (&$duraciones) {
                foreach ($resguardos as $resguardo) {
                    $segundos = (int) $resguardo->salida_cedis_at->diffInSeconds($resguardo->recepcion_fisica_at, false);
                    if ($segundos >= 0) {
                        $duraciones[] = $segundos;
                    }
                }
            });

        return ['duraciones' => $duraciones, 'en_curso' => $enCurso];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{duraciones: list<int>, en_curso: int}
     */
    private function duracionesCustodia(Builder $queryBase, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $corte = $filtros['corte_reporte_at'];
        $duraciones = [];
        $enCurso = 0;

        (clone $queryBase)
            ->whereNotNull('recepcion_fisica_at')
            ->where(function (Builder $q) use ($desde, $hasta, $corte) {
                $q->where(function (Builder $cerrados) use ($desde, $hasta) {
                    $cerrados
                        ->whereNotNull('entrega_completada_at')
                        ->where('entrega_completada_at', '>=', $desde)
                        ->where('entrega_completada_at', '<', $hasta);
                })->orWhere(function (Builder $devueltos) use ($desde, $hasta) {
                    $devueltos
                        ->whereNotNull('devolucion_confirmada_at')
                        ->where('devolucion_confirmada_at', '>=', $desde)
                        ->where('devolucion_confirmada_at', '<', $hasta);
                })->orWhere(function (Builder $abiertos) use ($corte, $desde, $hasta) {
                    $abiertos
                        ->whereNull('entrega_completada_at')
                        ->whereNull('devolucion_confirmada_at')
                        ->where('recepcion_fisica_at', '<', $hasta)
                        ->where('recepcion_fisica_at', '>=', $desde->copy()->subYears(2))
                        ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                        ->where('recepcion_fisica_at', '<=', $corte);
                });
            })
            ->select([
                'id',
                'estado',
                'recepcion_fisica_at',
                'entrega_completada_at',
                'devolucion_confirmada_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($resguardos) use (&$duraciones, &$enCurso) {
                foreach ($resguardos as $resguardo) {
                    $fin = $resguardo->entrega_completada_at ?? $resguardo->devolucion_confirmada_at;

                    if ($fin === null) {
                        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA) {
                            continue;
                        }

                        $enCurso++;

                        continue;
                    }

                    $segundos = $resguardo->recepcion_fisica_at->diffInSeconds($fin, false);
                    if ($segundos >= 0) {
                        $duraciones[] = $segundos;
                    }
                }
            });

        return ['duraciones' => $duraciones, 'en_curso' => $enCurso];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaTasaEntrega(Builder $queryBase, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $entregados = (clone $queryBase)
            ->where('estado', ResguardoPdv::ESTADO_ENTREGADO)
            ->whereNotNull('entrega_completada_at')
            ->where('entrega_completada_at', '>=', $desde)
            ->where('entrega_completada_at', '<', $hasta)
            ->count();

        $devueltos = (clone $queryBase)
            ->where('estado', ResguardoPdv::ESTADO_DEVUELTO)
            ->whereNotNull('devolucion_confirmada_at')
            ->where('devolucion_confirmada_at', '>=', $desde)
            ->where('devolucion_confirmada_at', '<', $hasta)
            ->count();

        $denominador = $entregados + $devueltos;

        return [
            'id' => MetricaResguardoPdvIds::R_09_TASA_ENTREGA,
            'unidad' => 'porcentaje',
            'entregados' => $entregados,
            'devueltos' => $devueltos,
            'valor' => $denominador > 0 ? round(($entregados / $denominador) * 100, 2) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaTasaIncidencia(User $user, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $queryEventos = $this->filtrosQuery->queryEventos($user);
        $this->filtrosQuery->aplicarFiltrosEvento($queryEventos, $filtros);

        $activos = (clone $queryEventos)
            ->where('ocurrido_at', '>=', $desde)
            ->where('ocurrido_at', '<', $hasta)
            ->distinct('resguardo_id')
            ->count('resguardo_id');

        $resguardoIds = $this->queryResguardosFiltrado($user, $filtros)->select('id');

        $conIncidenciaQuery = ResguardoPdvIncidencia::query()
            ->whereIn('resguardo_id', $resguardoIds)
            ->where('reportado_at', '>=', $desde)
            ->where('reportado_at', '<', $hasta);

        if (! empty($filtros['tipo_incidencia'])) {
            $conIncidenciaQuery->where('tipo', (string) $filtros['tipo_incidencia']);
        }

        $conIncidencia = (clone $conIncidenciaQuery)->distinct('resguardo_id')->count('resguardo_id');

        return [
            'id' => MetricaResguardoPdvIds::R_10_TASA_INCIDENCIA,
            'unidad' => 'porcentaje',
            'con_incidencia' => $conIncidencia,
            'activos' => $activos,
            'valor' => $activos > 0 ? round(($conIncidencia / $activos) * 100, 2) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarEventosRecepcion(User $user, array $filtros): int
    {
        $query = $this->filtrosQuery->queryEventos($user);
        $this->filtrosQuery->aplicarFiltrosEvento($query, $filtros);

        return (clone $query)
            ->whereIn('tipo_evento', [
                ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA,
                ResguardoPdvEvento::TIPO_RECEPCION_PARCIAL,
            ])
            ->where('ocurrido_at', '>=', $filtros['desde'])
            ->where('ocurrido_at', '<', $filtros['hasta'])
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarEventosDevolucion(User $user, array $filtros): int
    {
        $query = $this->filtrosQuery->queryEventos($user);
        $this->filtrosQuery->aplicarFiltrosEvento($query, $filtros);

        return (clone $query)
            ->where('tipo_evento', ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA)
            ->where('ocurrido_at', '>=', $filtros['desde'])
            ->where('ocurrido_at', '<', $filtros['hasta'])
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function metricaEntera(string $id, int $valor): array
    {
        return [
            'id' => $id,
            'unidad' => 'entero',
            'valor' => $valor,
        ];
    }

    /**
     * @param  array{duraciones: list<int>, en_curso: int}  $datos
     * @return array<string, mixed>
     */
    private function metricaDuracion(string $id, array $datos): array
    {
        $duraciones = $datos['duraciones'];
        $conteo = count($duraciones);
        $resultado = [
            'id' => $id,
            'unidad' => 'segundos',
            'conteo' => $conteo,
            'en_curso' => $datos['en_curso'],
        ];

        if ($conteo > 0) {
            $resultado['promedio_segundos'] = (int) round(array_sum($duraciones) / $conteo);
        }

        $percentiles = PercentilesPdv::calcular($duraciones);
        if ($percentiles !== null) {
            $resultado['percentiles'] = $percentiles;
        }

        return $resultado;
    }
}
