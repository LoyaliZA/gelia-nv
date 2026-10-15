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
        abort_if($solicitud->esAbastecimiento(), 422, 'El abastecimiento conserva su trazabilidad. Resuelva sus incidencias desde la preparación receptora.');
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
