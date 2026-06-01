<?php

namespace App\Services\Rh;

use App\Models\RhColaborador;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListarColaboradoresService
{
    public function ejecutar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = RhColaborador::query()
            ->with(['departamento', 'area', 'puesto.bonos', 'usuario', 'bonos'])
            ->orderByDesc('id');

        if (!empty($filtros['busqueda'])) {
            $busqueda = trim($filtros['busqueda']);
            $query->where(function ($q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                    ->orWhere('uuid', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('apellido_paterno', 'like', "%{$busqueda}%")
                    ->orWhere('apellido_materno', 'like', "%{$busqueda}%");
            });
        }

        if (!empty($filtros['departamento_id'])) {
            $query->where('departamento_id', $filtros['departamento_id']);
        }

        if (!empty($filtros['area_id'])) {
            $query->where('area_id', $filtros['area_id']);
        }

        if (!empty($filtros['catalogo_puesto_id'])) {
            $query->where('catalogo_puesto_id', $filtros['catalogo_puesto_id']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '' && $filtros['activo'] !== null) {
            $query->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOL));
        }

        if (!empty($filtros['vinculo'])) {
            if ($filtros['vinculo'] === 'con_cuenta') {
                $query->whereNotNull('user_id');
            } elseif ($filtros['vinculo'] === 'sin_cuenta') {
                $query->whereNull('user_id');
            }
        }

        return $query->paginate($porPagina)->withQueryString();
    }
}
