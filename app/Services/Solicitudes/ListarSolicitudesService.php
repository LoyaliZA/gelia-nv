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
     * @return LengthAwarePaginator
     */
    public function ejecutar(?User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $query = SolicitudTag::with(['cliente', 'vendedor', 'proceso', 'estado'])
            ->orderBy('created_at', 'desc'); 

        // Lógica de aislamiento por roles (Verificamos que $usuario no sea nulo primero)
        if ($usuario && $usuario->hasRole('Vendedor')) {
            $query->where('vendedor_id', $usuario->id);
        }

        // Aplicación de filtros dinámicos
        $this->aplicarFiltros($query, $filtros);

        return $query->paginate(15);
    }

    /**
     * Aplica condiciones a la consulta base de Eloquent.
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['estado_id'])) {
            $query->where('catalogo_estado_solicitud_id', $filtros['estado_id']);
        }

        if (!empty($filtros['cliente_numero'])) {
            $query->whereHas('cliente', function ($q) use ($filtros) {
                $q->where('numero_cliente', $filtros['cliente_numero']);
            });
        }
        
        // Aquí se pueden agregar más filtros como fechas, vendedoras específicas, etc.
    }
}