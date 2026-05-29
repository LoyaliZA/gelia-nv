<?php

namespace App\Services\Facturas;

use App\Models\AuditoriaSolicitudFactura;
use App\Models\SolicitudFactura;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EliminarSolicitudFacturaService
{
    public function ejecutar(SolicitudFactura $solicitud, string $motivo): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            AuditoriaSolicitudFactura::create([
                'solicitud_factura_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => null,
                'motivo_reporte' => "ELIMINACIÓN: {$motivo}",
                'datos_snapshot' => $solicitud->only(['folio', 'razon_social', 'vendedor_id']),
            ]);

            $solicitud->delete();
        });
    }
}
