<?php

namespace App\Services\PuntoVenta\Reportes\TurnosOperacion;

use App\Models\PuntoVenta\OperacionPdvEvento;
use App\Models\PuntoVenta\SucursalDiaOperacionPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\PuntoVenta\TurnoPdvEvento;
use App\Models\PuntoVenta\TurnoPdvProrroga;
use App\Models\User;
use App\Services\PuntoVenta\Operacion\HorarioCierreOperacionPdvConfig;
use App\Services\PuntoVenta\Operacion\OperacionPdvConfig;
use App\Support\PuntoVenta\Operacion\TipoIntervaloOperativoPdv;
use App\Support\PuntoVenta\Reportes\MetricaTurnoOperacionPdvIds;
use App\Support\PuntoVenta\Reportes\PercentilesPdv;
use App\Support\PuntoVenta\Turnos\MotivosCierreAtencionTurnoPdv;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CalcularMetricasReporteTurnoOperacionPdvService
{
    public function __construct(
        private readonly NormalizarFiltrosReporteTurnoOperacionPdvService $normalizarFiltros,
        private readonly AplicarFiltrosReporteTurnoOperacionPdvQuery $filtrosQuery,
        private readonly OperacionPdvConfig $operacion,
        private readonly HorarioCierreOperacionPdvConfig $horarioCierre,
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
        $normalizados = $this->filtrosQuery->resolverAlcance($user, $normalizados);

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
        $esperaCola = $this->duracionesEsperaCola($user, $filtros);
        $duracionAtencion = $this->duracionesAtencion($user, $filtros);
        $operacion = $this->metricasOperacion($user, $filtros);

        return array_merge([
            MetricaTurnoOperacionPdvIds::T_01_ALTAS => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::T_01_ALTAS,
                $this->contarAltas($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::T_02_ASIGNACIONES => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::T_02_ASIGNACIONES,
                $this->contarEventosTurno($user, $filtros, TurnoPdvEvento::TIPO_ASIGNADO)
            ),
            MetricaTurnoOperacionPdvIds::T_03_TURNOS_CERRADOS => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::T_03_TURNOS_CERRADOS,
                $this->contarTurnosCerrados($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::T_04_BAJAS_COLA => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::T_04_BAJAS_COLA,
                $this->contarBajasCola($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::T_05_ESPERA_COLA => $this->metricaDuracion(
                MetricaTurnoOperacionPdvIds::T_05_ESPERA_COLA,
                $esperaCola
            ),
            MetricaTurnoOperacionPdvIds::T_06_ESPERA_INICIAL => $this->metricaDuracion(
                MetricaTurnoOperacionPdvIds::T_06_ESPERA_INICIAL,
                $this->duracionesEsperaInicial($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::T_07_DURACION_ATENCION => $this->metricaDuracion(
                MetricaTurnoOperacionPdvIds::T_07_DURACION_ATENCION,
                $duracionAtencion
            ),
            MetricaTurnoOperacionPdvIds::T_08_CICLO_TOTAL => $this->metricaDuracion(
                MetricaTurnoOperacionPdvIds::T_08_CICLO_TOTAL,
                $this->duracionesCicloTotal($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO => $this->metricaTasaAbandono($user, $filtros),
            MetricaTurnoOperacionPdvIds::T_10_TASA_NO_SE_PRESENTO => $this->metricaTasaNoSePresento($user, $filtros),
            MetricaTurnoOperacionPdvIds::T_11_TASA_REATENCION => $this->metricaTasaReatencion($user, $filtros),
            MetricaTurnoOperacionPdvIds::T_12_TRANSFERENCIAS => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::T_12_TRANSFERENCIAS,
                $this->contarEventosTurno($user, $filtros, TurnoPdvEvento::TIPO_TRANSFERIDO)
            ),
            MetricaTurnoOperacionPdvIds::T_13_PRORROGAS => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::T_13_PRORROGAS,
                $this->contarProrrogas($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::T_14_DISTRIBUCION_PRIORIDAD => $this->metricaDistribucionPrioridad($user, $filtros),
            MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA => $this->metricaPercentilesDerivada(
                MetricaTurnoOperacionPdvIds::T_15_PERCENTILES_ESPERA_COLA,
                $esperaCola
            ),
            MetricaTurnoOperacionPdvIds::T_16_PERCENTILES_DURACION_ATENCION => $this->metricaPercentilesDerivada(
                MetricaTurnoOperacionPdvIds::T_16_PERCENTILES_DURACION_ATENCION,
                $duracionAtencion
            ),
        ], $operacion);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function calcularDesglosePorSucursal(User $user, array $filtros): array
    {
        if (! empty($filtros['sucursal_id'])) {
            return [];
        }

        $desglose = [];
        foreach ($this->filtrosQuery->idsSucursalesDesglose($user, $filtros) as $sucursalId) {
            $filtrosSucursal = array_merge($filtros, ['sucursal_id' => $sucursalId]);

            $desglose[(string) $sucursalId] = [
                MetricaTurnoOperacionPdvIds::T_01_ALTAS => $this->metricaEntera(
                    MetricaTurnoOperacionPdvIds::T_01_ALTAS,
                    $this->contarAltas($user, $filtrosSucursal)
                ),
                MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO => $this->metricaTasaAbandono($user, $filtrosSucursal),
                MetricaTurnoOperacionPdvIds::O_06_OCUPACION => $this->metricaOcupacion($user, $filtrosSucursal),
                MetricaTurnoOperacionPdvIds::O_09_CIERRES_HORARIO => $this->metricaEntera(
                    MetricaTurnoOperacionPdvIds::O_09_CIERRES_HORARIO,
                    $this->contarCierresHorario($user, $filtrosSucursal)
                ),
            ];
        }

        return $desglose;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarAltas(User $user, array $filtros): int
    {
        $conteo = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->whereNotNull('alta_at')
            ->select(['id', 'sucursal_id', 'alta_at'])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use (&$conteo, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if ($this->filtrosQuery->cumpleAtribucion($turno->alta_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        $conteo++;
                    }
                }
            });

        return $conteo;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarTurnosCerrados(User $user, array $filtros): int
    {
        $conteo = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->where('estado', TurnoPdv::ESTADO_CERRADO)
            ->whereNotNull('cerrado_at')
            ->select(['id', 'sucursal_id', 'cerrado_at'])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use (&$conteo, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if ($this->filtrosQuery->cumpleAtribucion($turno->cerrado_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        $conteo++;
                    }
                }
            });

        return $conteo;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarBajasCola(User $user, array $filtros): int
    {
        return $this->contarEventosTurno($user, $filtros, TurnoPdvEvento::TIPO_BAJA_COLA);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarEventosTurno(User $user, array $filtros, string $tipoEvento): int
    {
        $conteo = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryEventosTurno($user, $filtros)
            ->where('tipo_evento', $tipoEvento)
            ->with(['turno:id,sucursal_id'])
            ->select(['id', 'turno_id', 'ocurrido_at'])
            ->orderBy('id')
            ->chunkById(200, function ($eventos) use (&$conteo, $filtros, $desde, $hasta) {
                foreach ($eventos as $evento) {
                    $sucursalId = (int) $evento->turno->sucursal_id;
                    if ($this->filtrosQuery->cumpleAtribucion($evento->ocurrido_at, $sucursalId, $filtros, $desde, $hasta)) {
                        $conteo++;
                    }
                }
            });

        return $conteo;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarProrrogas(User $user, array $filtros): int
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $atenciones = [];

        $this->filtrosQuery->queryEventosTurno($user, $filtros)
            ->where('tipo_evento', TurnoPdvEvento::TIPO_PRORROGA)
            ->whereNotNull('atencion_id')
            ->with(['turno:id,sucursal_id'])
            ->select(['id', 'turno_id', 'atencion_id', 'ocurrido_at'])
            ->orderBy('id')
            ->chunkById(200, function ($eventos) use (&$atenciones, $filtros, $desde, $hasta) {
                foreach ($eventos as $evento) {
                    if ($evento->atencion_id === null) {
                        continue;
                    }

                    $sucursalId = (int) $evento->turno->sucursal_id;
                    if ($this->filtrosQuery->cumpleAtribucion($evento->ocurrido_at, $sucursalId, $filtros, $desde, $hasta)) {
                        $atenciones[(int) $evento->atencion_id] = true;
                    }
                }
            });

        TurnoPdvProrroga::query()
            ->whereHas('atencion.turno', fn (Builder $turno) => $this->filtrosQuery->aplicarFiltrosTurno($turno, $user, $filtros))
            ->whereHas('atencion', fn (Builder $atencion) => $this->filtrosQuery->aplicarFiltrosAtencion($atencion, $user, $filtros))
            ->select(['id', 'alertado_at', 'atencion_id'])
            ->with(['atencion.turno:id,sucursal_id'])
            ->orderBy('id')
            ->chunkById(200, function ($prorrogas) use (&$atenciones, $filtros, $desde, $hasta) {
                foreach ($prorrogas as $prorroga) {
                    $momento = $prorroga->alertado_at;
                    if ($momento === null) {
                        continue;
                    }

                    $sucursalId = (int) $prorroga->atencion->turno->sucursal_id;
                    if ($this->filtrosQuery->cumpleAtribucion($momento, $sucursalId, $filtros, $desde, $hasta)) {
                        $atenciones[(int) $prorroga->atencion_id] = true;
                    }
                }
            });

        return count($atenciones);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{duraciones: list<int>, en_curso: int}
     */
    private function duracionesEsperaCola(User $user, array $filtros): array
    {
        $duraciones = [];
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->whereNotNull('alta_at')
            ->whereNull('baja_at')
            ->whereHas('atenciones')
            ->with(['atenciones' => fn ($q) => $q->orderBy('numero_secuencia')->orderBy('inicio_at')])
            ->select(['id', 'sucursal_id', 'alta_at'])
            ->orderBy('id')
            ->chunkById(100, function ($turnos) use (&$duraciones, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if (! $this->filtrosQuery->cumpleAtribucion($turno->alta_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $primera = $turno->atenciones->first();
                    if ($primera === null || $primera->inicio_at === null) {
                        continue;
                    }

                    $segundos = (int) $turno->alta_at->diffInSeconds($primera->inicio_at, false);
                    if ($segundos >= 0) {
                        $duraciones[] = $segundos;
                    }
                }
            });

        return ['duraciones' => $duraciones, 'en_curso' => 0];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{duraciones: list<int>, en_curso: int}
     */
    private function duracionesEsperaInicial(User $user, array $filtros): array
    {
        $duraciones = [];
        $enCurso = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $corte = $filtros['corte_reporte_at'];

        $this->filtrosQuery->queryAtenciones($user, $filtros)
            ->whereNotNull('inicio_at')
            ->with(['turno:id,sucursal_id'])
            ->select(['id', 'turno_id', 'inicio_at', 'atencion_inicio_at', 'fin_at'])
            ->orderBy('id')
            ->chunkById(200, function ($atenciones) use (&$duraciones, &$enCurso, $filtros, $desde, $hasta, $corte) {
                foreach ($atenciones as $atencion) {
                    $sucursalId = (int) $atencion->turno->sucursal_id;
                    if (! $this->filtrosQuery->cumpleAtribucion($atencion->inicio_at, $sucursalId, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $finEspera = $atencion->atencion_inicio_at ?? $atencion->fin_at;
                    if ($finEspera === null) {
                        $finEspera = $corte;
                        $enCurso++;
                    }

                    $segundos = (int) $atencion->inicio_at->diffInSeconds($finEspera, false);
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
    private function duracionesAtencion(User $user, array $filtros): array
    {
        $duraciones = [];
        $enCurso = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $corte = $filtros['corte_reporte_at'];

        $this->filtrosQuery->queryAtenciones($user, $filtros)
            ->whereNotNull('atencion_inicio_at')
            ->with(['turno:id,sucursal_id'])
            ->select(['id', 'turno_id', 'atencion_inicio_at', 'fin_at', 'motivo_cierre'])
            ->orderBy('id')
            ->chunkById(200, function ($atenciones) use (&$duraciones, &$enCurso, $filtros, $desde, $hasta, $corte) {
                foreach ($atenciones as $atencion) {
                    if (
                        $atencion->motivo_cierre === MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO
                        && $atencion->atencion_inicio_at === null
                    ) {
                        continue;
                    }

                    $sucursalId = (int) $atencion->turno->sucursal_id;

                    if ($atencion->fin_at === null) {
                        if ($atencion->atencion_inicio_at->lte($corte)) {
                            $enCurso++;
                        }

                        continue;
                    }

                    if (! $this->filtrosQuery->cumpleAtribucion($atencion->fin_at, $sucursalId, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $segundos = (int) $atencion->atencion_inicio_at->diffInSeconds($atencion->fin_at, false);
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
    private function duracionesCicloTotal(User $user, array $filtros): array
    {
        $duraciones = [];
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->where('estado', TurnoPdv::ESTADO_CERRADO)
            ->whereNotNull('alta_at')
            ->whereNotNull('cerrado_at')
            ->select(['id', 'sucursal_id', 'alta_at', 'cerrado_at'])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use (&$duraciones, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if (! $this->filtrosQuery->cumpleAtribucion($turno->cerrado_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $segundos = (int) $turno->alta_at->diffInSeconds($turno->cerrado_at, false);
                    if ($segundos >= 0) {
                        $duraciones[] = $segundos;
                    }
                }
            });

        return ['duraciones' => $duraciones, 'en_curso' => 0];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaTasaAbandono(User $user, array $filtros): array
    {
        $denominador = $this->contarAltas($user, $filtros);
        $numerador = $this->contarAbandonos($user, $filtros);

        return [
            'id' => MetricaTurnoOperacionPdvIds::T_09_TASA_ABANDONO,
            'unidad' => 'porcentaje',
            'abandonos' => $numerador,
            'altas' => $denominador,
            'valor' => $denominador > 0 ? round(($numerador / $denominador) * 100, 2) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarAbandonos(User $user, array $filtros): int
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $abandonos = [];

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->whereNotNull('alta_at')
            ->where(function (Builder $q) {
                $q->whereNotNull('baja_at')
                    ->orWhereHas('atenciones', function (Builder $atencion) {
                        $atencion->where('motivo_cierre', MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO);
                    });
            })
            ->select(['id', 'sucursal_id', 'alta_at', 'baja_at'])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use (&$abandonos, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if (! $this->filtrosQuery->cumpleAtribucion($turno->alta_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    if ($turno->baja_at !== null) {
                        $abandonos[$turno->id] = true;

                        continue;
                    }

                    $tieneNoSePresento = TurnoPdvAtencion::query()
                        ->where('turno_id', $turno->id)
                        ->where('motivo_cierre', MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO)
                        ->exists();

                    if ($tieneNoSePresento) {
                        $abandonos[$turno->id] = true;
                    }
                }
            });

        return count($abandonos);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaTasaNoSePresento(User $user, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $asignaciones = 0;
        $noSePresento = 0;

        $this->filtrosQuery->queryAtenciones($user, $filtros)
            ->whereNotNull('inicio_at')
            ->with(['turno:id,sucursal_id'])
            ->select(['id', 'turno_id', 'inicio_at', 'motivo_cierre'])
            ->orderBy('id')
            ->chunkById(200, function ($atenciones) use (&$asignaciones, &$noSePresento, $filtros, $desde, $hasta) {
                foreach ($atenciones as $atencion) {
                    $sucursalId = (int) $atencion->turno->sucursal_id;
                    if (! $this->filtrosQuery->cumpleAtribucion($atencion->inicio_at, $sucursalId, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $asignaciones++;
                    if ($atencion->motivo_cierre === MotivosCierreAtencionTurnoPdv::NO_SE_PRESENTO) {
                        $noSePresento++;
                    }
                }
            });

        return [
            'id' => MetricaTurnoOperacionPdvIds::T_10_TASA_NO_SE_PRESENTO,
            'unidad' => 'porcentaje',
            'no_se_presento' => $noSePresento,
            'asignaciones' => $asignaciones,
            'valor' => $asignaciones > 0 ? round(($noSePresento / $asignaciones) * 100, 2) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaTasaReatencion(User $user, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $cerradas = 0;
        $reatenciones = 0;
        $tiemposHastaReatencion = [];

        $this->filtrosQuery->queryAtenciones($user, $filtros)
            ->whereNotNull('fin_at')
            ->whereIn('motivo_cierre', [MotivosCierreAtencionTurnoPdv::VENTA, MotivosCierreAtencionTurnoPdv::SIN_VENTA])
            ->with(['turno:id,sucursal_id,reatencion_expira_at'])
            ->select(['id', 'turno_id', 'numero_secuencia', 'inicio_at', 'fin_at', 'motivo_cierre'])
            ->orderBy('turno_id')
            ->orderBy('numero_secuencia')
            ->chunk(200, function ($atenciones) use (&$cerradas, &$reatenciones, &$tiemposHastaReatencion, $user, $filtros, $desde, $hasta) {
                $porTurno = $atenciones->groupBy('turno_id');

                foreach ($porTurno as $turnoId => $grupo) {
                    $ordenadas = $grupo->sortBy('numero_secuencia')->values();

                    foreach ($ordenadas as $indice => $atencion) {
                        $sucursalId = (int) $atencion->turno->sucursal_id;
                        if (! $this->filtrosQuery->cumpleAtribucion($atencion->fin_at, $sucursalId, $filtros, $desde, $hasta)) {
                            continue;
                        }

                        $cerradas++;

                        if ($indice === 0) {
                            continue;
                        }

                        $anterior = $ordenadas[$indice - 1];
                        $esReatencion = $this->esReatencionValida($atencion, $anterior, $user, $filtros, $desde, $hasta);

                        if ($esReatencion) {
                            $reatenciones++;
                            $tiemposHastaReatencion[] = (int) $anterior->fin_at->diffInSeconds($atencion->inicio_at, false);
                        }
                    }
                }
            });

        $resultado = [
            'id' => MetricaTurnoOperacionPdvIds::T_11_TASA_REATENCION,
            'unidad' => 'porcentaje',
            'reatenciones' => $reatenciones,
            'cerradas' => $cerradas,
            'valor' => $cerradas > 0 ? round(($reatenciones / $cerradas) * 100, 2) : null,
        ];

        if ($reatenciones > 0) {
            $resultado['tiempo_hasta_reatencion_promedio_segundos'] = (int) round(
                array_sum($tiemposHastaReatencion) / count($tiemposHastaReatencion)
            );
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function esReatencionValida(
        TurnoPdvAtencion $atencion,
        TurnoPdvAtencion $anterior,
        User $user,
        array $filtros,
        Carbon $desde,
        Carbon $hasta,
    ): bool {
        $turno = $atencion->turno;
        $expira = $turno->reatencion_expira_at;

        if ($expira !== null && $atencion->inicio_at->gt($expira)) {
            return false;
        }

        if ($atencion->numero_secuencia > 1) {
            return true;
        }

        return TurnoPdvEvento::query()
            ->where('turno_id', $turno->id)
            ->where('atencion_id', $atencion->id)
            ->where('tipo_evento', TurnoPdvEvento::TIPO_REATENCION)
            ->where('ocurrido_at', '>=', $desde)
            ->where('ocurrido_at', '<', $hasta)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaDistribucionPrioridad(User $user, array $filtros): array
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $conteos = [
            'normal' => 0,
            'adulto_mayor' => 0,
            'discapacidad' => 0,
            'diamante' => 0,
            'vip' => 0,
        ];
        $total = 0;

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->whereNotNull('alta_at')
            ->select([
                'id',
                'sucursal_id',
                'alta_at',
                'prioridad',
                'prioridad_adulto_mayor',
                'prioridad_discapacidad',
                'prioridad_diamante',
                'prioridad_vip',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use (&$conteos, &$total, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if (! $this->filtrosQuery->cumpleAtribucion($turno->alta_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $total++;
                    if ($turno->prioridad_vip) {
                        $conteos['vip']++;
                    } elseif ($turno->prioridad_diamante) {
                        $conteos['diamante']++;
                    } elseif ($turno->prioridad_adulto_mayor) {
                        $conteos['adulto_mayor']++;
                    } elseif ($turno->prioridad_discapacidad) {
                        $conteos['discapacidad']++;
                    } else {
                        $conteos['normal']++;
                    }
                }
            });

        $distribucion = [];
        foreach ($conteos as $clave => $valor) {
            $distribucion[$clave] = [
                'conteo' => $valor,
                'porcentaje' => $total > 0 ? round(($valor / $total) * 100, 2) : null,
            ];
        }

        return [
            'id' => MetricaTurnoOperacionPdvIds::T_14_DISTRIBUCION_PRIORIDAD,
            'unidad' => 'porcentaje',
            'total' => $total,
            'distribucion' => $distribucion,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function metricasOperacion(User $user, array $filtros): array
    {
        $jornadas = $this->agregarJornadas($user, $filtros);
        $intervalos = $this->agregarIntervalos($user, $filtros, $filtros['corte_reporte_at']);

        return [
            MetricaTurnoOperacionPdvIds::O_01_JORNADAS_ABIERTAS => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::O_01_JORNADAS_ABIERTAS,
                $jornadas['conteo']
            ),
            MetricaTurnoOperacionPdvIds::O_02_DURACION_JORNADA => $this->metricaDuracion(
                MetricaTurnoOperacionPdvIds::O_02_DURACION_JORNADA,
                $jornadas['duraciones']
            ),
            MetricaTurnoOperacionPdvIds::O_03_TIEMPO_PAUSA => $this->metricaDuracionSegundos(
                MetricaTurnoOperacionPdvIds::O_03_TIEMPO_PAUSA,
                $intervalos['en_pausa'],
                $intervalos['en_pausa_en_curso']
            ),
            MetricaTurnoOperacionPdvIds::O_04_TIEMPO_DISPONIBLE => $this->metricaDuracionSegundos(
                MetricaTurnoOperacionPdvIds::O_04_TIEMPO_DISPONIBLE,
                $intervalos['disponible'],
                $intervalos['disponible_en_curso']
            ),
            MetricaTurnoOperacionPdvIds::O_05_TIEMPO_ATENCION_INTERVALOS => $this->metricaDuracionSegundos(
                MetricaTurnoOperacionPdvIds::O_05_TIEMPO_ATENCION_INTERVALOS,
                $intervalos['en_atencion'],
                $intervalos['en_atencion_en_curso']
            ),
            MetricaTurnoOperacionPdvIds::O_06_OCUPACION => $this->metricaOcupacion($user, $filtros),
            MetricaTurnoOperacionPdvIds::O_07_DISPONIBILIDAD => $this->metricaDisponibilidad($user, $filtros),
            MetricaTurnoOperacionPdvIds::O_08_DIAS_SIN_ALTAS => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::O_08_DIAS_SIN_ALTAS,
                $this->contarDiasSinAltas($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::O_09_CIERRES_HORARIO => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::O_09_CIERRES_HORARIO,
                $this->contarCierresHorario($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::O_10_AMPLIACIONES_HORARIO => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::O_10_AMPLIACIONES_HORARIO,
                $this->contarAmpliaciones($user, $filtros)
            ),
            MetricaTurnoOperacionPdvIds::O_11_ALTAS_DESPUES_CIERRE => $this->metricaEntera(
                MetricaTurnoOperacionPdvIds::O_11_ALTAS_DESPUES_CIERRE,
                $this->contarAltasDespuesCierre($user, $filtros)
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{conteo: int, duraciones: array{duraciones: list<int>, en_curso: int}}
     */
    private function agregarJornadas(User $user, array $filtros): array
    {
        $duraciones = [];
        $enCurso = 0;
        $conteo = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];
        $corte = $filtros['corte_reporte_at'];

        $this->filtrosQuery->queryJornadas($user, $filtros)
            ->whereNotNull('apertura_at')
            ->select(['id', 'sucursal_id', 'user_id', 'apertura_at', 'cierre_at'])
            ->orderBy('id')
            ->chunkById(200, function ($jornadas) use (&$duraciones, &$enCurso, &$conteo, $filtros, $desde, $hasta, $corte) {
                foreach ($jornadas as $jornada) {
                    if (! $this->filtrosQuery->cumpleAtribucion($jornada->apertura_at, (int) $jornada->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $conteo++;
                    $abierta = $jornada->cierre_at === null;

                    if ($abierta) {
                        $enCurso++;
                        $fin = $corte;
                    } else {
                        $fin = $jornada->cierre_at;
                    }

                    $segundos = (int) $jornada->apertura_at->diffInSeconds($fin, false);
                    if ($segundos > 0 && ! $abierta) {
                        $duraciones[] = $segundos;
                    }
                }
            });

        return [
            'conteo' => $conteo,
            'duraciones' => ['duraciones' => $duraciones, 'en_curso' => $enCurso],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   en_pausa: int,
     *   disponible: int,
     *   en_atencion: int,
     *   en_pausa_en_curso: int,
     *   disponible_en_curso: int,
     *   en_atencion_en_curso: int
     * }
     */
    private function agregarIntervalos(User $user, array $filtros, Carbon $corte): array
    {
        $totales = [
            'en_pausa' => 0,
            'disponible' => 0,
            'en_atencion' => 0,
            'en_pausa_en_curso' => 0,
            'disponible_en_curso' => 0,
            'en_atencion_en_curso' => 0,
        ];

        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryIntervalos($user, $filtros)
            ->select(['id', 'sucursal_id', 'tipo', 'inicio_at', 'fin_at'])
            ->orderBy('id')
            ->chunkById(200, function ($intervalos) use (&$totales, $filtros, $desde, $hasta, $corte) {
                foreach ($intervalos as $intervalo) {
                    if (! $this->filtrosQuery->cumpleAtribucion($intervalo->inicio_at, (int) $intervalo->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    $fin = $intervalo->fin_at ?? $corte;
                    $segundos = max(0, (int) $intervalo->inicio_at->diffInSeconds($fin, false));

                    $clave = match ($intervalo->tipo) {
                        TipoIntervaloOperativoPdv::EnPausa => 'en_pausa',
                        TipoIntervaloOperativoPdv::Disponible => 'disponible',
                        TipoIntervaloOperativoPdv::EnAtencion => 'en_atencion',
                    };

                    $totales[$clave] += $segundos;

                    if ($intervalo->fin_at === null) {
                        $totales[$clave.'_en_curso']++;
                    }
                }
            });

        return $totales;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaOcupacion(User $user, array $filtros): array
    {
        $jornadas = $this->agregarJornadas($user, $filtros);
        $intervalos = $this->agregarIntervalos($user, $filtros, $filtros['corte_reporte_at']);

        $duracionJornada = array_sum($jornadas['duraciones']['duraciones']);
        $tiempoAtencion = $intervalos['en_atencion'];

        return [
            'id' => MetricaTurnoOperacionPdvIds::O_06_OCUPACION,
            'unidad' => 'porcentaje',
            'tiempo_atencion_segundos' => $tiempoAtencion,
            'tiempo_jornada_segundos' => $duracionJornada,
            'valor' => $duracionJornada > 0 ? round(($tiempoAtencion / $duracionJornada) * 100, 2) : null,
            'en_curso' => $jornadas['duraciones']['en_curso'] > 0 || $intervalos['en_atencion_en_curso'] > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function metricaDisponibilidad(User $user, array $filtros): array
    {
        $jornadas = $this->agregarJornadas($user, $filtros);
        $intervalos = $this->agregarIntervalos($user, $filtros, $filtros['corte_reporte_at']);

        $duracionJornada = array_sum($jornadas['duraciones']['duraciones']);
        $tiempoDisponible = $intervalos['disponible'];

        return [
            'id' => MetricaTurnoOperacionPdvIds::O_07_DISPONIBILIDAD,
            'unidad' => 'porcentaje',
            'tiempo_disponible_segundos' => $tiempoDisponible,
            'tiempo_jornada_segundos' => $duracionJornada,
            'valor' => $duracionJornada > 0 ? round(($tiempoDisponible / $duracionJornada) * 100, 2) : null,
            'en_curso' => $jornadas['duraciones']['en_curso'] > 0 || $intervalos['disponible_en_curso'] > 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarDiasSinAltas(User $user, array $filtros): int
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        return $this->filtrosQuery->queryDiasSucursal($user, $filtros)
            ->where('acepta_altas', false)
            ->where('fecha_operativa', '>=', $desde->toDateString())
            ->where('fecha_operativa', '<', $hasta->toDateString())
            ->when(
                ! empty($filtros['fecha_operativa']),
                fn (Builder $q) => $q->whereDate('fecha_operativa', $filtros['fecha_operativa'])
            )
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarCierresHorario(User $user, array $filtros): int
    {
        $conteo = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryEventosOperacion($user, $filtros)
            ->where('tipo_evento', OperacionPdvEvento::TIPO_CIERRE_HORARIO)
            ->select(['id', 'sucursal_id', 'ocurrido_at'])
            ->orderBy('id')
            ->chunkById(200, function ($eventos) use (&$conteo, $filtros, $desde, $hasta) {
                foreach ($eventos as $evento) {
                    if ($this->filtrosQuery->cumpleAtribucion($evento->ocurrido_at, (int) $evento->sucursal_id, $filtros, $desde, $hasta)) {
                        $conteo++;
                    }
                }
            });

        return $conteo;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarAmpliaciones(User $user, array $filtros): int
    {
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        return $this->filtrosQuery->queryDiasSucursal($user, $filtros)
            ->whereNotNull('ampliacion_hasta_at')
            ->where('fecha_operativa', '>=', $desde->toDateString())
            ->where('fecha_operativa', '<', $hasta->toDateString())
            ->when(
                ! empty($filtros['fecha_operativa']),
                fn (Builder $q) => $q->whereDate('fecha_operativa', $filtros['fecha_operativa'])
            )
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function contarAltasDespuesCierre(User $user, array $filtros): int
    {
        $conteo = 0;
        $desde = $filtros['desde'];
        $hasta = $filtros['hasta'];

        $this->filtrosQuery->queryTurnos($user, $filtros)
            ->whereNotNull('alta_at')
            ->select(['id', 'sucursal_id', 'alta_at'])
            ->orderBy('id')
            ->chunkById(200, function ($turnos) use (&$conteo, $filtros, $desde, $hasta) {
                foreach ($turnos as $turno) {
                    if (! $this->filtrosQuery->cumpleAtribucion($turno->alta_at, (int) $turno->sucursal_id, $filtros, $desde, $hasta)) {
                        continue;
                    }

                    if ($this->altaViolatesCierre((int) $turno->sucursal_id, $turno->alta_at)) {
                        $conteo++;
                    }
                }
            });

        return $conteo;
    }

    private function altaViolatesCierre(int $sucursalId, Carbon $altaAt): bool
    {
        $fechaOperativa = $this->operacion->fechaOperativa($sucursalId, $altaAt);

        $dia = SucursalDiaOperacionPdv::query()
            ->where('sucursal_id', $sucursalId)
            ->whereDate('fecha_operativa', $fechaOperativa)
            ->first();

        if ($dia === null || $dia->acepta_altas) {
            return false;
        }

        if ($dia->ampliacion_hasta_at !== null && $altaAt->lte($dia->ampliacion_hasta_at)) {
            return false;
        }

        $horario = $this->horarioCierre->resolverParaSucursal($sucursalId);
        if ($horario === null) {
            return false;
        }

        $zona = $horario['zona_horaria'];
        $local = $altaAt->copy()->timezone($zona);
        [$hora, $minuto] = array_map('intval', explode(':', $horario['hora_cierre']));
        $umbral = Carbon::parse($fechaOperativa, $zona)->setTime($hora, $minuto, 0);

        if ($dia->hora_cierre !== null) {
            [$h, $m] = array_map('intval', explode(':', substr((string) $dia->hora_cierre, 0, 5)));
            $umbral = Carbon::parse($fechaOperativa, $zona)->setTime($h, $m, 0);
        }

        return $local->gte($umbral);
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

        $percentiles = PercentilesPdv::calcular($duraciones, MetricaTurnoOperacionPdvIds::UMBRAL_PERCENTILES);
        if ($percentiles !== null) {
            $resultado['percentiles'] = $percentiles;
        }

        return $resultado;
    }

    /**
     * @param  array{duraciones: list<int>, en_curso: int}  $datos
     * @return array<string, mixed>
     */
    private function metricaPercentilesDerivada(string $id, array $datos): array
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

        $percentiles = PercentilesPdv::calcular($duraciones, MetricaTurnoOperacionPdvIds::UMBRAL_PERCENTILES);
        if ($percentiles !== null) {
            $resultado['percentiles'] = $percentiles;
        }

        return $resultado;
    }

    private function metricaDuracionSegundos(string $id, int $segundos, int $enCurso): array
    {
        return [
            'id' => $id,
            'unidad' => 'segundos',
            'valor' => $segundos,
            'en_curso' => $enCurso > 0,
        ];
    }
}
