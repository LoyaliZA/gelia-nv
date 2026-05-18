<?php
namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListarSolicitudesService
{
    public function ejecutar(?User $usuario, array $filtros = [], bool $paginar = true)
    {
        $query = SolicitudTag::with([
            'cliente.listaDescuento', 
            'vendedor', 
            'departamento',
            'proceso', 
            'estado', 
            'auditorias.usuario', 
            'auditorias.estadoNuevo', 
            'listaDescuento', 
            'tipoCliente'
        ])->orderBy('created_at', 'desc'); 

        if ($usuario) {
            $this->aplicarAislamientoDeDatos($query, $usuario);
        }

        $this->aplicarFiltros($query, $filtros);

        return $paginar ? $query->paginate(15) : $query->get();
    }

    private function aplicarAislamientoDeDatos(Builder $query, User $usuario): void
    {
        // 1. Roles con acceso global absoluto
        if ($usuario->hasAnyRole(['Super Admin', 'Administrador'])) {
            return;
        }

        // 2. Permisos para visibilidad de área (Encargadas, Gerentes, Auxiliares)
        // Se omite intencionalmente 'solicitudes.editar' para evitar escalamiento de visibilidad en operadoras.
        $tieneVisibilidadArea = $usuario->hasRole('Gerente') || 
                                $usuario->hasAnyPermission(['solicitudes.verificar', 'solicitudes.reportar']);

        if ($tieneVisibilidadArea) {
            $this->filtrarPorDepartamento($query, $usuario);
            return;
        }

        // 3. Aislamiento estricto (Vendedoras / Colaboradores estándar)
        // Confinamiento del usuario a sus propios registros independientemente de la interfaz.
        $query->where('vendedor_id', $usuario->id);
    }

    private function filtrarPorDepartamento(Builder $query, User $usuario): void
    {
        $departamentosUsuario = $usuario->departamentos->pluck('id')->toArray();
        
        if (!empty($departamentosUsuario)) {
            $query->whereIn('departamento_id', $departamentosUsuario);
        } else {
            // Cierre de seguridad si un verificador no tiene departamentos asignados
            $query->where('id', 0); 
        }
    }

    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['estado_id'])) $query->where('catalogo_estado_solicitud_id', $filtros['estado_id']);
        if (!empty($filtros['proceso_id'])) $query->where('catalogo_proceso_id', $filtros['proceso_id']);
        
        // El filtro de vendedor proveniente del frontend solo tendrá efecto 
        // si el usuario superó el aislamiento estricto previo.
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
                if (isset($filtros['es_heredado']) && $filtros['es_heredado'] !== '') {
                    $q->where('es_heredado', filter_var($filtros['es_heredado'], FILTER_VALIDATE_BOOLEAN));
                }
            });
        }
    }
}