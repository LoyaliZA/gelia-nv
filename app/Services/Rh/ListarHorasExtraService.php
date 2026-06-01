<?php

namespace App\Services\Rh;

use App\Models\RhHorasExtra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListarHorasExtraService
{
    public function ejecutar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = RhHorasExtra::query()
            ->with(['colaborador.departamento', 'colaborador.area', 'supervisor'])
            ->orderByDesc('fecha_turno')
            ->orderByDesc('id');

        $this->aplicarFiltros($query, $filtros);

        return $query->paginate($porPagina)->withQueryString();
    }

    public function metricas(array $filtros = []): array
    {
        $base = RhHorasExtra::query();
        $this->aplicarFiltros($base, $filtros);

        $hoy = now()->toDateString();

        $registrosHoy = (clone $base)->whereDate('fecha_turno', $hoy)->count();
        $pendientes = (clone $base)->where('estado_pago', 'pendiente')->count();
        $horasAPagar = (clone $base)->sum('horas_extra_a_pagar');
        $montoTotal = (clone $base)->sum('total_economico');
        $montoPendiente = (clone $base)->where('estado_pago', 'pendiente')->sum('total_economico');

        return [
            'registros_hoy' => $registrosHoy,
            'pendientes_pago' => $pendientes,
            'horas_extra_a_pagar' => (int) $horasAPagar,
            'monto_total' => round((float) $montoTotal, 2),
            'monto_pendiente' => round((float) $montoPendiente, 2),
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['busqueda'])) {
            $busqueda = trim($filtros['busqueda']);
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                    ->orWhere('uuid', 'like', "%{$busqueda}%")
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

        if (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha_turno', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha_turno', '<=', $filtros['fecha_fin']);
        }

        if (!empty($filtros['solo_hoy'])) {
            $query->whereDate('fecha_turno', now()->toDateString());
        }

        if (!empty($filtros['estado_pago']) && $filtros['estado_pago'] !== 'TODAS') {
            $query->where('estado_pago', strtolower($filtros['estado_pago']));
        }
    }
}
