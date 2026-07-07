<?php

namespace App\Services\Admin;

use App\Models\AuditoriaAcceso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ListarAuditoriasAccesosService
{
    /**
     * @param array<string, mixed> $filtros
     */
    public function ejecutar(array $filtros): LengthAwarePaginator
    {
        $query = AuditoriaAcceso::with('usuario')
            ->orderByDesc('inicio_sesion_at');

        if (!empty($filtros['user_id'])) {
            $query->where('user_id', $filtros['user_id']);
        }

        if (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('inicio_sesion_at', '>=', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['fecha_fin'])) {
            $query->whereDate('inicio_sesion_at', '<=', $filtros['fecha_fin']);
        }

        if (!empty($filtros['motivo_cierre'])) {
            if ($filtros['motivo_cierre'] === 'activa') {
                $query->whereNull('cierre_sesion_at');
            } else {
                $query->where('motivo_cierre', $filtros['motivo_cierre']);
            }
        }

        if (!empty($filtros['ip'])) {
            $query->where('ip_address', 'like', '%' . $filtros['ip'] . '%');
        }

        if (!empty($filtros['ubicacion'])) {
            $termino = $filtros['ubicacion'];
            $query->where(function ($q) use ($termino) {
                $q->where('ubicacion_ciudad', 'like', "%{$termino}%")
                    ->orWhere('ubicacion_region', 'like', "%{$termino}%")
                    ->orWhere('ubicacion_pais', 'like', "%{$termino}%");
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * @return array{sesiones_activas: int, promedio_duracion_segundos: int}
     */
    public function resumen(): array
    {
        $sesionesActivas = AuditoriaAcceso::whereNull('cierre_sesion_at')->count();

        $promedio = AuditoriaAcceso::whereDate('inicio_sesion_at', today())
            ->whereNotNull('duracion_activa_segundos')
            ->avg('duracion_activa_segundos');

        return [
            'sesiones_activas'            => $sesionesActivas,
            'promedio_duracion_segundos'  => (int) round($promedio ?? 0),
        ];
    }
}
