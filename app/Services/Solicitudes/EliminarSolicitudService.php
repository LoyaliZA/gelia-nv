<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EliminarSolicitudService
{
    /**
     * Ejecuta la eliminación lógica de una solicitud y registra la auditoría.
     */
    public function ejecutar(SolicitudTag $solicitud, string $motivo): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            // 1. Registro de Auditoría de Eliminación
            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => null, // Representa la salida del sistema
                'motivo_reporte' => "ELIMINACIÓN DE REGISTRO: {$motivo}",
                'datos_snapshot' => $solicitud->toArray() // Captura el estado final antes de borrar
            ]);

            // 2. Eliminación Lógica (Soft Delete)
            $solicitud->delete();
        });
    }
}