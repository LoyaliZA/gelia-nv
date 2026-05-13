<?php

namespace App\Observers;

use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use Illuminate\Support\Facades\Auth;

class SolicitudTagObserver
{
    /**
     * Escucha las actualizaciones del modelo para registrar auditorías.
     
    public function updated(SolicitudTag $solicitudTag): void
    {
          Solo registramos si cambió el estado o el vendedor
        if ($solicitudTag->wasChanged('catalogo_estado_solicitud_id') || $solicitudTag->wasChanged('vendedor_id')) {
            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitudTag->id,
                'usuario_id' => Auth::id() ?? 1, // Fallback por si ocurre en consola
                'estado_anterior_id' => $solicitudTag->getOriginal('catalogo_estado_solicitud_id'),
                'estado_nuevo_id' => $solicitudTag->catalogo_estado_solicitud_id,
                'motivo_reporte' => 'Cambio registrado automáticamente por el sistema.'
            ]);
        }
    }*/
}