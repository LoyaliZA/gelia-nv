<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\UsuarioAreaComisionEmpaque;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AlcanceAreaComisionEmpaque
{
    /**
     * null = acceso global explícito (administrador o permiso ver_todas).
     * Un arreglo vacío significa que no hay áreas asignadas.
     */
    public function ids(?User $usuario): ?array
    {
        if (! $usuario) {
            return [];
        }
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])
            || $usuario->can('gestion_interna.comisiones_empaque.ver_todas')) {
            return null;
        }

        return UsuarioAreaComisionEmpaque::query()
            ->where('user_id', $usuario->id)
            ->pluck('area_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function aplicar(Builder $query, ?User $usuario, string $columna = 'area_id'): Builder
    {
        $ids = $this->ids($usuario);
        if (is_array($ids)) {
            $query->whereIn($columna, $ids);
        }

        return $query;
    }

    public function permite(?User $usuario, int $areaId): bool
    {
        $ids = $this->ids($usuario);

        return $ids === null || in_array($areaId, $ids, true);
    }

    /** @return \Illuminate\Support\Collection<int, AreaComisionEmpaque> */
    public function areas(?User $usuario)
    {
        $query = AreaComisionEmpaque::query()->orderBy('nombre');
        $this->aplicar($query, $usuario, 'id');

        return $query->get();
    }
}
