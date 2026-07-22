<?php

namespace App\Services\Traspasos;

use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EliminarSolicitudTraspasoService
{
    public function ejecutar(SolicitudTraspaso $solicitud, string $motivo, User $usuario): void
    {
        DB::transaction(function () use ($solicitud, $motivo, $usuario) {
            AuditoriaSolicitudTraspaso::create([
                'solicitud_traspaso_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => $motivo,
                'datos_snapshot' => [
                    'folio' => $solicitud->folio,
                    'accion' => 'eliminada',
                ],
            ]);

            if ($solicitud->evidencia_respuesta_path) {
                Storage::disk('public')->delete($solicitud->evidencia_respuesta_path);
            }

            $solicitud->delete();
        });
    }
}
