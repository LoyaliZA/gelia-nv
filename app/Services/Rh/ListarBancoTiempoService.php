<?php

namespace App\Services\Rh;

use App\Models\RhBancoTiempo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListarBancoTiempoService
{
    public function ejecutar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = RhBancoTiempo::query()
            ->with(['colaborador.departamento', 'colaborador.area', 'registradoPor'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->aplicarFiltros($query, $filtros);

        return $query->paginate($porPagina)->withQueryString();
    }

    public function metricas(array $filtros = []): array
    {
        $base = RhBancoTiempo::query();
        $this->aplicarFiltros($base, $filtros);

        $activas   = (clone $base)->where('estado', RhBancoTiempo::ESTADO_ACTIVA)->count();
        $saldadas  = (clone $base)->where('estado', RhBancoTiempo::ESTADO_SALDADA)->count();
        $horasPendientes = (clone $base)
            ->where('estado', RhBancoTiempo::ESTADO_ACTIVA)
            ->sum('horas_pendientes');

        return [
            'activas'          => $activas,
            'saldadas'         => $saldadas,
            'horas_pendientes' => round((float) $horasPendientes, 2),
            'total'            => $activas + $saldadas,
        ];
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['busqueda'])) {
            $busqueda = trim($filtros['busqueda']);
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                    ->orWhere('origen_deuda', 'like', "%{$busqueda}%")
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

        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['fecha_inicio'])) {
            $query->where('fecha_acuerdo', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $query->where('fecha_acuerdo', '<=', $filtros['fecha_fin']);
        }
    }
}
