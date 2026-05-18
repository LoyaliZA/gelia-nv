<?php

namespace App\Services\Admin;

use App\Models\AuditoriaListaDescuento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListarAuditoriasListasService
{
    /**
     * Resuelve el listado de auditorías aplicando cargas ansiosas y filtros.
     */
    public function ejecutar(array $filtros = []): LengthAwarePaginator
    {
        $query = AuditoriaListaDescuento::with(['lista', 'usuario'])
            ->orderBy('created_at', 'desc');

        $this->aplicarFiltros($query, $filtros);

        return $query->paginate(20)->withQueryString();
    }

    /**
     * Aplica condiciones condicionales a la consulta de auditoría.
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        if (!empty($filtros['lista_id'])) {
            $query->where('lista_id', $filtros['lista_id']);
        }

        if (!empty($filtros['origen'])) {
            $query->where('origen_cambio', $filtros['origen']);
        }

        if (!empty($filtros['fecha_inicio']) && !empty($filtros['fecha_fin'])) {
            $query->whereBetween('created_at', [$filtros['fecha_inicio'] . ' 00:00:00', $filtros['fecha_fin'] . ' 23:59:59']);
        }
    }
}