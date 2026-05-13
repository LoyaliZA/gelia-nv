<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListarSolicitudesService
{
    /**
     * Obtiene el listado de solicitudes filtrado por el rol del usuario y los parámetros de búsqueda.
     *
     * @param User|null $usuario El usuario autenticado (puede ser nulo si no hay sesión)
     * @param array $filtros Filtros provenientes de la URL
     * @param bool $paginar Define si el resultado debe ser paginado (true) o la colección completa (false)
     * @return LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        // Eager Loading configurado correctamente para renderizar en la tabla
        $query = SolicitudTag::with([
            'cliente', 
            'vendedor', 
            'proceso', 
            'estado', 
            'auditorias.usuario', 
            'auditorias.estadoNuevo', 
            'listaDescuento', 
            'tipoCliente'
        ])->orderBy('created_at', 'desc'); 

        if ($usuario) {
            $esAdminOGerente = $usuario->hasAnyRole(['Super Admin', 'Administrador', 'Gerente']);
            $esVerificador = $usuario->hasAnyPermission(['solicitudes.verificar', 'solicitudes.reportar', 'solicitudes.editar']);

            if (!$esAdminOGerente && !$esVerificador) {
                $query->where('vendedor_id', $usuario->id);
            }
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15) : $query->get();
    }

    /**
     * Aplica condiciones a la consulta base de Eloquent.
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['estado_id'])) $query->where('catalogo_estado_solicitud_id', $filtros['estado_id']);
        if (!empty($filtros['proceso_id'])) $query->where('catalogo_proceso_id', $filtros['proceso_id']);
        if (!empty($filtros['vendedor_id'])) $query->where('vendedor_id', $filtros['vendedor_id']);

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('created_at', [$filtros['fecha_inicio'] . ' 00:00:00', $filtros['fecha_fin'] . ' 23:59:59']);
        } elseif (!empty($filtros['fecha_inicio'])) {
            $query->whereDate('created_at', $filtros['fecha_inicio']);
        }

        if (!empty($filtros['mes'])) {
            $query->whereMonth('created_at', $filtros['mes']);
            if (!empty($filtros['anio'])) $query->whereYear('created_at', $filtros['anio']);
        }

        if (!empty($filtros['cliente_numero']) || !empty($filtros['cliente_nombre']) || isset($filtros['es_heredado'])) {
            $query->whereHas('cliente', function ($q) use ($filtros) {
                if (!empty($filtros['cliente_numero'])) {
                    $q->where('numero_cliente', 'like', '%' . $filtros['cliente_numero'] . '%');
                }
                if (!empty($filtros['cliente_nombre'])) {
                    $q->where('nombre', 'like', '%' . $filtros['cliente_nombre'] . '%');
                }
                // Solución del error: Se elimina 'clone' y se asigna el valor directamente
                if (isset($filtros['es_heredado']) && $filtros['es_heredado'] !== '') {
                    $q->where('es_heredado', filter_var($filtros['es_heredado'], FILTER_VALIDATE_BOOLEAN));
                }
            });
        }
    }
}