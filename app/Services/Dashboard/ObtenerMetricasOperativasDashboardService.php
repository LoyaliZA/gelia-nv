<?php

namespace App\Services\Dashboard;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Services\Solicitudes\ListarSolicitudesService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ObtenerMetricasOperativasDashboardService
{
    public function __construct(
        private ListarSolicitudesService $solicitudes,
    ) {}

    /**
     * @param  array<string, mixed>  $periodo
     * @return array{cotizado: array<string, mixed>, actividad: array<string, mixed>}
     */
    public function ejecutar(User $usuario, array $periodo): array
    {
        $inicio = $periodo['inicio'];
        $fin = $periodo['fin'];
        $prevInicio = $periodo['comparacion_inicio'];
        $prevFin = $periodo['comparacion_fin'];
        $tramos = $this->tramos($periodo);

        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idCancelada = CatalogoEstadoSolicitud::idDe('Cancelada');
        $idsCierre = array_values(array_filter([(int) $idRespondida, (int) $idCancelada]));

        $montosActuales = $this->sumarPorDia($usuario, $inicio, $fin, 'created_at', true);
        $montosAnteriores = $this->sumarPorDia($usuario, $prevInicio, $prevFin, 'created_at', true);
        $creadasActuales = $this->contarPorDia($usuario, $inicio, $fin, 'created_at');
        $creadasAnteriores = $this->contarPorDia($usuario, $prevInicio, $prevFin, 'created_at');
        $cerradasActuales = $idsCierre === []
            ? collect()
            : $this->contarPorDia($usuario, $inicio, $fin, 'updated_at', $idsCierre);
        $cerradasAnteriores = $idsCierre === []
            ? collect()
            : $this->contarPorDia($usuario, $prevInicio, $prevFin, 'updated_at', $idsCierre);

        $serie = [];
        $total = 0.0;
        $totalAnterior = 0.0;
        $creadas = 0;
        $cerradas = 0;

        foreach ($tramos as $indice => $tramo) {
            $monto = $this->sumarTramo($montosActuales, $tramo['inicio'], $tramo['fin']);
            $creadasTramo = $this->contarTramo($creadasActuales, $tramo['inicio'], $tramo['fin']);
            $cerradasTramo = $this->contarTramo($cerradasActuales, $tramo['inicio'], $tramo['fin']);
            $diasTramo = $this->pasoDias($tramo);
            $prevTramoInicio = $prevInicio->copy()->addDays($indice * $diasTramo)->startOfDay();
            $prevTramoFin = $prevTramoInicio->copy()->addDays($diasTramo - 1)->endOfDay();
            if ($prevTramoFin->gt($prevFin)) {
                $prevTramoFin = $prevFin->copy();
            }
            $montoAnterior = $this->sumarTramo($montosAnteriores, $prevTramoInicio, $prevTramoFin);
            $creadasAnterior = $this->contarTramo($creadasAnteriores, $prevTramoInicio, $prevTramoFin);
            $cerradasAnterior = $this->contarTramo($cerradasAnteriores, $prevTramoInicio, $prevTramoFin);

            $total += $monto;
            $totalAnterior += $montoAnterior;
            $creadas += $creadasTramo;
            $cerradas += $cerradasTramo;

            $serie[] = [
                'clave' => $tramo['inicio']->toDateString(),
                'etiqueta' => $tramo['etiqueta'],
                'monto' => round($monto, 2),
                'monto_anterior' => round($montoAnterior, 2),
                'creadas' => $creadasTramo,
                'cerradas' => $cerradasTramo,
                'creadas_anterior' => $creadasAnterior,
                'cerradas_anterior' => $cerradasAnterior,
            ];
        }

        return [
            'cotizado' => [
                'total' => round($total, 2),
                'total_anterior' => round($totalAnterior, 2),
                'delta_pct' => $this->deltaPct($total, $totalAnterior),
                'serie' => $serie,
                'desglose' => $this->desglose($usuario, $inicio, $fin),
                'periodo' => $periodo['etiqueta'],
                'periodo_anterior' => $periodo['etiqueta_anterior'],
            ],
            'actividad' => [
                'creadas' => $creadas,
                'cerradas' => $cerradas,
                'serie' => $serie,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $periodo
     * @return list<array{inicio: Carbon, fin: Carbon, etiqueta: string}>
     */
    public function tramos(array $periodo): array
    {
        $inicio = $periodo['inicio']->copy()->startOfDay();
        $fin = $periodo['fin']->copy()->startOfDay();
        $tramos = [];

        if ($periodo['grano'] === ResolverPeriodoDashboard::GRANOS_SEMANA) {
            $cursor = $inicio->copy();
            while ($cursor->lte($fin)) {
                $hasta = $cursor->copy()->addDays(6);
                if ($hasta->gt($fin)) {
                    $hasta = $fin->copy();
                }
                $tramos[] = [
                    'inicio' => $cursor->copy(),
                    'fin' => $hasta->copy(),
                    'etiqueta' => $this->etiquetaTramo($cursor, $hasta),
                ];
                $cursor = $hasta->copy()->addDay();
            }

            return $tramos;
        }

        $cursor = $inicio->copy();
        while ($cursor->lte($fin)) {
            $tramos[] = [
                'inicio' => $cursor->copy(),
                'fin' => $cursor->copy(),
                'etiqueta' => $cursor->copy()->locale('es')->translatedFormat($inicio->isSameMonth($fin) ? 'j' : 'j M'),
            ];
            $cursor->addDay();
        }

        return $tramos;
    }

    /**
     * @return Collection<string, float>
     */
    private function sumarPorDia(User $usuario, Carbon $inicio, Carbon $fin, string $columna, bool $soloConMonto = false): Collection
    {
        $query = $this->baseComercial($usuario)
            ->whereBetween($columna, [$inicio, $fin]);
        if ($soloConMonto) {
            $query->whereNotNull('monto_cotizado');
        }

        return $query
            ->selectRaw('DATE('.$columna.') as dia, SUM(monto_cotizado) as total')
            ->groupByRaw('DATE('.$columna.')')
            ->pluck('total', 'dia')
            ->map(fn ($valor) => (float) $valor);
    }

    /**
     * @param  list<int>|null  $estados
     * @return Collection<string, int>
     */
    private function contarPorDia(User $usuario, Carbon $inicio, Carbon $fin, string $columna, ?array $estados = null): Collection
    {
        $query = $this->baseComercial($usuario)
            ->whereBetween($columna, [$inicio, $fin]);
        if ($estados !== null) {
            $query->whereIn('catalogo_estado_solicitud_id', $estados);
        }

        return $query
            ->selectRaw('DATE('.$columna.') as dia, COUNT(*) as total')
            ->groupByRaw('DATE('.$columna.')')
            ->pluck('total', 'dia')
            ->map(fn ($valor) => (int) $valor);
    }

    private function baseComercial(User $usuario): Builder
    {
        $query = SolicitudTag::query()->whereHas('proceso', function (Builder $proceso) {
            $proceso->where('categoria_flujo', '!=', CatalogoProceso::CATEGORIA_OPERATIVO);
        });
        $this->solicitudes->aislarConsulta($query, $usuario);

        return $query;
    }

    private function sumarTramo(Collection $porDia, Carbon $inicio, Carbon $fin): float
    {
        $total = 0.0;
        $cursor = $inicio->copy()->startOfDay();
        $limite = $fin->copy()->startOfDay();
        while ($cursor->lte($limite)) {
            $clave = $cursor->toDateString();
            $total += (float) ($porDia[$clave] ?? 0);
            $cursor->addDay();
        }

        return $total;
    }

    private function contarTramo(Collection $porDia, Carbon $inicio, Carbon $fin): int
    {
        return (int) round($this->sumarTramo($porDia, $inicio, $fin));
    }

    /**
     * @param  array{inicio: Carbon, fin: Carbon}  $tramo
     */
    private function pasoDias(array $tramo): int
    {
        return (int) $tramo['inicio']->diffInDays($tramo['fin']) + 1;
    }

    private function deltaPct(float $actual, float $anterior): ?float
    {
        if (abs($anterior) < 0.0001) {
            return abs($actual) < 0.0001 ? 0.0 : null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    /**
     * @return array{dimension: string, items: list<array<string, mixed>>}
     */
    private function desglose(User $usuario, Carbon $inicio, Carbon $fin): array
    {
        $filas = $this->baseComercial($usuario)
            ->whereBetween('created_at', [$inicio, $fin])
            ->whereNotNull('monto_cotizado')
            ->select('vendedor_id', 'catalogo_proceso_id')
            ->selectRaw('SUM(monto_cotizado) as monto')
            ->selectRaw('COUNT(*) as solicitudes')
            ->groupBy('vendedor_id', 'catalogo_proceso_id')
            ->get();

        $vendedoresDistintos = $filas->pluck('vendedor_id')->filter()->unique()->count();
        $usarVendedor = $vendedoresDistintos > 1;

        $agrupado = $filas->groupBy(fn ($fila) => $usarVendedor
            ? (string) ($fila->vendedor_id ?? '0')
            : (string) ($fila->catalogo_proceso_id ?? '0'));

        $ids = $agrupado->keys()->map(fn ($id) => (int) $id)->filter()->values();
        $nombres = $usarVendedor
            ? User::query()->whereIn('id', $ids)->pluck('name', 'id')
            : CatalogoProceso::query()->whereIn('id', $ids)->pluck('nombre', 'id');

        $items = $agrupado
            ->map(function (Collection $grupo, $clave) use ($nombres, $usarVendedor) {
                $id = (int) $clave;

                return [
                    'id' => $id,
                    'nombre' => $nombres[$id] ?? ($usarVendedor ? 'Sin responsable' : 'Sin tipo de solicitud'),
                    'monto' => round((float) $grupo->sum('monto'), 2),
                    'solicitudes' => (int) $grupo->sum('solicitudes'),
                ];
            })
            ->sortByDesc('monto')
            ->values();

        $limite = 6;
        if ($items->count() > $limite) {
            $visibles = $items->take($limite)->values();
            $resto = $items->slice($limite);
            $visibles->push([
                'id' => 0,
                'nombre' => 'Otros',
                'monto' => round((float) $resto->sum('monto'), 2),
                'solicitudes' => (int) $resto->sum('solicitudes'),
            ]);
            $items = $visibles;
        }

        return [
            'dimension' => $usarVendedor ? 'vendedor' : 'proceso',
            'items' => $items->all(),
        ];
    }

    private function etiquetaTramo(Carbon $inicio, Carbon $fin): string
    {
        $inicio->locale('es');
        $fin->locale('es');
        if ($inicio->isSameDay($fin)) {
            return $inicio->translatedFormat('j M');
        }

        return $inicio->translatedFormat('j').'–'.$fin->translatedFormat('j M');
    }
}
