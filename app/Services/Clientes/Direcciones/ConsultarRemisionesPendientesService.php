<?php

namespace App\Services\Clientes\Direcciones;

use App\Models\SolicitudDireccion;
use Illuminate\Support\Collection;

class ConsultarRemisionesPendientesService
{
    /**
     * @return Collection<int, SolicitudDireccion>
     */
    public function porCliente(int $clienteId): Collection
    {
        return SolicitudDireccion::query()
            ->where('cliente_coincidente_id', $clienteId)
            ->where('estado', SolicitudDireccion::ESTADO_APPROVED)
            ->where('estado_remision', SolicitudDireccion::REMISION_PENDING_ORDER_LINK)
            ->orderByDesc('revisada_en')
            ->get([
                'id',
                'folio',
                'cliente_coincidente_id',
                'archivo_remision',
                'estado_remision',
                'revisada_por',
                'revisada_en',
                'created_at',
            ]);
    }
}
