<?php

namespace App\Services\Rh;

use App\Models\RhDeduccion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListarDeduccionesService
{
    public function ejecutar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = RhDeduccion::query()
            ->with(['colaborador.departamento', 'colaborador.area', 'reglaIncidencia', 'registradoPor'])
            ->orderByDesc('fecha_ocurrencia')
            ->orderByDesc('id');

        $this->aplicarFiltros($query, $filtros);

        return $query->paginate($porPagina)->withQueryString();
    }

    public function metricas(array $filtros = []): array
    {
        $base = RhDeduccion::query();
        $this->aplicarFiltros($base, $filtros);

        $hoy = now()->toDateString();

        return [
            'registros_hoy' => (clone $base)->whereDate('fecha_ocurrencia', $hoy)->count(),
            'pendientes_deduccion' => (clone $base)->whereIn('estado_deduccion', [
                RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                RhDeduccion::ESTADO_PENDIENTE_COMISION,
            ])->count(),
            'total_deduccion_periodo' => round((float) (clone $base)->sum('total_deduccion'), 2),
            'monto_pendiente' => round((float) (clone $base)->whereIn('estado_deduccion', [
                RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                RhDeduccion::ESTADO_PENDIENTE_COMISION,
            ])->sum('total_deduccion'), 2),
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['busqueda'])) {
            $busqueda = trim($filtros['busqueda']);
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                    ->orWhere('uuid', 'like', "%{$busqueda}%")
                    ->orWhere('regla_nombre_snapshot', 'like', "%{$busqueda}%")
                    ->orWhere('producto_sku_snapshot', 'like', "%{$busqueda}%")
                    ->orWhereHas('colaborador', function ($c) use ($busqueda) {
                        $c->where('nombre', 'like', "%{$busqueda}%")
                            ->orWhere('apellido_paterno', 'like', "%{$busqueda}%")
                            ->orWhere('apellido_materno', 'like', "%{$busqueda}%")
                            ->orWhere('folio', 'like', "%{$busqueda}%");
                    });
            });
        }

        if (!empty($filtros['rh_colaborador_id'])) {
            $query->where('rh_colaborador_id', $filtros['rh_colaborador_id']);
        }

        if (!empty($filtros['rh_colaborador_ids']) && is_array($filtros['rh_colaborador_ids'])) {
            $query->whereIn('rh_colaborador_id', $filtros['rh_colaborador_ids']);
        }

        if (!empty($filtros['catalogo_regla_incidencia_id'])) {
            $query->where('catalogo_regla_incidencia_id', $filtros['catalogo_regla_incidencia_id']);
        }

        if (!empty($filtros['departamento_id'])) {
            $query->whereHas('colaborador', fn ($q) => $q->where('departamento_id', $filtros['departamento_id']));
        }

        if (!empty($filtros['area_id'])) {
            $query->whereHas('colaborador', fn ($q) => $q->where('area_id', $filtros['area_id']));
        }

        if (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha_ocurrencia', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha_ocurrencia', '<=', $filtros['fecha_fin']);
        }

        if (!empty($filtros['solo_hoy'])) {
            $query->whereDate('fecha_ocurrencia', now()->toDateString());
        }

        if (!empty($filtros['estado_deduccion']) && $filtros['estado_deduccion'] !== 'TODAS') {
            $estado = strtolower($filtros['estado_deduccion']);
            if ($estado === 'pendiente') {
                $query->whereIn('estado_deduccion', [
                    RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                    RhDeduccion::ESTADO_PENDIENTE_COMISION,
                ]);
            } elseif ($estado === 'programado') {
                $query->where('estado_deduccion', RhDeduccion::ESTADO_PENDIENTE_NOMINA);
            } else {
                $query->where('estado_deduccion', $estado);
            }
        }

        if (!empty($filtros['origen_deduccion']) && $filtros['origen_deduccion'] !== 'TODAS') {
            $query->where('origen_deduccion', strtolower($filtros['origen_deduccion']));
        }

        if (!empty($filtros['rama'])) {
            $rama = strtolower($filtros['rama']);
            if ($rama === 'incidencias') {
                $query->whereNotNull('catalogo_regla_incidencia_id')
                    ->whereNull('rh_prestamo_pago_fijo_id');
            } elseif ($rama === 'pagos_pendientes') {
                $query->where(function ($q) {
                    $q->whereNotNull('rh_prestamo_pago_fijo_id')
                        ->orWhereIn('estado_deduccion', [
                            RhDeduccion::ESTADO_PENDIENTE_NOMINA,
                            RhDeduccion::ESTADO_PENDIENTE_COMISION,
                        ]);
                });
            }
        }
    }
}
