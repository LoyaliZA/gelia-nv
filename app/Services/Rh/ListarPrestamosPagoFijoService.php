<?php

namespace App\Services\Rh;

use App\Models\RhPrestamoPagoFijo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListarPrestamosPagoFijoService
{
    public function ejecutar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = RhPrestamoPagoFijo::query()
            ->with(['colaborador.departamento', 'colaborador.area', 'registradoPor'])
            ->withCount('deducciones')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->aplicarFiltros($query, $filtros);

        return $query->paginate($porPagina)->withQueryString();
    }

    public function metricas(array $filtros = []): array
    {
        $base = RhPrestamoPagoFijo::query();
        $this->aplicarFiltros($base, $filtros);

        $activos = (clone $base)->where('estado', RhPrestamoPagoFijo::ESTADO_ACTIVO)->count();
        $pausados = (clone $base)->where('estado', RhPrestamoPagoFijo::ESTADO_PAUSADO)->count();
        $liquidados = (clone $base)->where('estado', RhPrestamoPagoFijo::ESTADO_LIQUIDADO)->count();
        $montoCuotaActivo = (clone $base)
            ->where('estado', RhPrestamoPagoFijo::ESTADO_ACTIVO)
            ->sum('monto_cuota');

        return [
            'activos' => $activos,
            'pausados' => $pausados,
            'liquidados' => $liquidados,
            'monto_cuota_activo' => round((float) $montoCuotaActivo, 2),
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['busqueda'])) {
            $busqueda = trim($filtros['busqueda']);
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                    ->orWhere('uuid', 'like', "%{$busqueda}%")
                    ->orWhere('concepto', 'like', "%{$busqueda}%")
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

        if (!empty($filtros['departamento_id'])) {
            $query->whereHas('colaborador', fn ($q) => $q->where('departamento_id', $filtros['departamento_id']));
        }

        if (!empty($filtros['area_id'])) {
            $query->whereHas('colaborador', fn ($q) => $q->where('area_id', $filtros['area_id']));
        }

        if (!empty($filtros['modalidad']) && $filtros['modalidad'] !== 'TODAS') {
            $query->where('modalidad', strtolower($filtros['modalidad']));
        }

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'TODAS') {
            $query->where('estado', strtolower($filtros['estado']));
        }
    }
}
