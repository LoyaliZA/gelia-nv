<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ListarActivosService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = Activo::with([
            'tipo',
            'departamento',
            'area',
            'responsable',
            'registradoPor',
            'fotos',
            'mantenimientoActivo',
        ])->orderByDesc('created_at');

        if ($usuario) {
            $this->aplicarAislamientoDeDatos($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros, $usuario);

        return $paginar ? $query->paginate(15)->withQueryString() : $query->get();
    }

    private function aplicarAislamientoDeDatos(Builder $query, User $usuario): void
    {
        if ($usuario->hasRole(['Super Admin', 'Administrador']) || $usuario->can('activos.ver_todos')) {
            return;
        }

        $departamentosUsuario = $usuario->departamentos->pluck('id')->toArray();

        if (!empty($departamentosUsuario)) {
            $query->whereIn('departamento_id', $departamentosUsuario);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    private function aplicarFiltros(Builder $query, array $filtros, ?User $usuario): void
    {
        if (!empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $query->where(function (Builder $q) use ($busqueda) {
                $q->where('folio', 'like', "%{$busqueda}%")
                    ->orWhere('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->serial', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->mac', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->ip', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->numero_serie', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->no_serie', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->imei', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->marca', 'like', "%{$busqueda}%")
                    ->orWhere('atributos->modelo', 'like', "%{$busqueda}%");
            });
        }

        if (!empty($filtros['catalogo_tipo_activo_id'])) {
            $query->where('catalogo_tipo_activo_id', $filtros['catalogo_tipo_activo_id']);
        }

        if (!empty($filtros['departamento_id'])) {
            $query->where('departamento_id', $filtros['departamento_id']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        } elseif (!empty($filtros['en_mantenimiento'])) {
            $query->where('estado', 'mantenimiento');
        }

        if (!empty($filtros['mis_activos']) && $usuario) {
            $query->where('responsable_user_id', $usuario->id);
        } elseif (!empty($filtros['sin_asignar'])) {
            $query->whereNull('responsable_user_id')->where('estado', 'disponible');
        } elseif (!empty($filtros['responsable_user_id'])) {
            $query->where('responsable_user_id', $filtros['responsable_user_id']);
        }

        if (!empty($filtros['pendientes_firma'])) {
            $query->where('estado', 'asignado')
                ->whereHas('asignaciones', function ($q) {
                    $q->where('activa', true)->where('firmado', false);
                });
        }
    }
}
