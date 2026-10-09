<?php

namespace App\Services\Admin;

use App\Models\AuditoriaDespliegue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListarAuditoriasDesplieguesService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function ejecutar(array $filtros): LengthAwarePaginator
    {
        $query = AuditoriaDespliegue::query()->with('usuario')->latest();

        if (! empty($filtros['accion'])) {
            $query->where('accion', (string) $filtros['accion']);
        }

        if (! empty($filtros['superficie'])) {
            $query->where('superficie', (string) $filtros['superficie']);
        }

        if (! empty($filtros['fecha_inicio'])) {
            $query->whereDate('created_at', '>=', (string) $filtros['fecha_inicio']);
        }

        if (! empty($filtros['fecha_fin'])) {
            $query->whereDate('created_at', '<=', (string) $filtros['fecha_fin']);
        }

        return $query->paginate(15)->withQueryString();
    }
}
