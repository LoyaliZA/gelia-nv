<?php

namespace App\Services\Rh;

use App\Models\RhSalidaPersonal;
use Illuminate\Pagination\LengthAwarePaginator;

class ListarSalidasPersonalesService
{
    public function ejecutar(array $filtros): LengthAwarePaginator
    {
        $query = RhSalidaPersonal::query()
            ->with(['colaborador.departamento', 'colaborador.area', 'registradoPor'])
            ->orderByDesc('fecha_evento')
            ->orderByDesc('id');

        // Búsqueda por folio o nombre del colaborador
        if (!empty($filtros['busqueda'])) {
            $busqueda = trim($filtros['busqueda']);
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                  ->orWhereHas('colaborador', function ($qc) use ($busqueda) {
                      $qc->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('apellido_paterno', 'like', "%{$busqueda}%")
                        ->orWhere('apellido_materno', 'like', "%{$busqueda}%");
                  });
            });
        }

        if (!empty($filtros['rh_colaborador_id'])) {
            $query->where('rh_colaborador_id', $filtros['rh_colaborador_id']);
        }

        if (!empty($filtros['departamento_id'])) {
            $departamentoId = $filtros['departamento_id'];
            $query->whereHas('colaborador', function ($q) use ($departamentoId) {
                $q->where('departamento_id', $departamentoId);
            });
        }

        if (!empty($filtros['area_id'])) {
            $areaId = $filtros['area_id'];
            $query->whereHas('colaborador', function ($q) use ($areaId) {
                $q->where('area_id', $areaId);
            });
        }

        if (!empty($filtros['fecha_inicio'])) {
            $query->where('fecha_evento', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $query->where('fecha_evento', '<=', $filtros['fecha_fin']);
        }

        if (!empty($filtros['estado_cobro'])) {
            if ($filtros['estado_cobro'] === 'cobrado') {
                $query->whereNotNull('fecha_deduccion_nomina');
            } elseif ($filtros['estado_cobro'] === 'pendiente') {
                $query->whereNull('fecha_deduccion_nomina');
            }
        }

        return $query->paginate(15)->withQueryString();
    }

    public function metricas(array $filtros): array
    {
        $query = RhSalidaPersonal::query();

        if (!empty($filtros['rh_colaborador_id'])) {
            $query->where('rh_colaborador_id', $filtros['rh_colaborador_id']);
        }

        if (!empty($filtros['departamento_id'])) {
            $departamentoId = $filtros['departamento_id'];
            $query->whereHas('colaborador', function ($q) use ($departamentoId) {
                $q->where('departamento_id', $departamentoId);
            });
        }

        if (!empty($filtros['fecha_inicio'])) {
            $query->where('fecha_evento', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $query->where('fecha_evento', '<=', $filtros['fecha_fin']);
        }

        $totalRegistros = (clone $query)->count();
        $minutosAusente = (clone $query)->sum('minutos_ausente');
        $montoDeduccion = (clone $query)->sum('monto_a_deducir');
        
        $hoy = now()->toDateString();
        $registrosHoy = (clone $query)->where('fecha_evento', $hoy)->count();

        $pendientesCobro = (clone $query)->whereNull('fecha_deduccion_nomina')->count();
        $montoPendienteCobro = (clone $query)->whereNull('fecha_deduccion_nomina')->sum('monto_a_deducir');

        return [
            'total_registros' => $totalRegistros,
            'registros_hoy' => $registrosHoy,
            'total_minutos' => $minutosAusente,
            'total_deduccion' => round((float) $montoDeduccion, 2),
            'pendientes_cobro' => $pendientesCobro,
            'monto_pendiente_cobro' => round((float) $montoPendienteCobro, 2),
        ];
    }
}
