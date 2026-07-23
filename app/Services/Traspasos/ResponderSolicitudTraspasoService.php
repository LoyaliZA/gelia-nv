<?php

namespace App\Services\Traspasos;

use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTraspaso;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResponderSolicitudTraspasoService
{
    public function __construct(
        private NotificarTraspasoService $notificar
    ) {}

    public function ejecutar(SolicitudTraspaso $solicitud, array $datos, User $usuario): SolicitudTraspaso
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $estadoNuevoId = (int) $datos['catalogo_estado_solicitud_id'];
            $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
            $esError = $idIncorrecta !== null && $estadoNuevoId === $idIncorrecta;

            $updates = [
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'motivo_respuesta' => $datos['motivo'] ?? null,
                'respondida_por_id' => $usuario->id,
                'respondida_at' => now(),
            ];

            if ($esError) {
                $updates['motivo_incorrecta'] = 'error_reportado';
                $updates['folio_traspaso'] = null;
            } else {
                $updates['motivo_incorrecta'] = null;
                $updates['folio_traspaso'] = $datos['folio_traspaso'] ?? null;
            }

            if (isset($datos['evidencia_respuesta']) && $datos['evidencia_respuesta'] instanceof UploadedFile && $datos['evidencia_respuesta']->isValid()) {
                if ($solicitud->evidencia_respuesta_path) {
                    Storage::disk('public')->delete($solicitud->evidencia_respuesta_path);
                }
                $updates['evidencia_respuesta_path'] = $datos['evidencia_respuesta']->store(
                    "traspasos/evidencias/{$solicitud->id}",
                    'public'
                );
            }

            $solicitud->update($updates);

            $rutaEvidencia = $updates['evidencia_respuesta_path'] ?? $solicitud->evidencia_respuesta_path;

            AuditoriaSolicitudTraspaso::create([
                'solicitud_traspaso_id' => $solicitud->id,
                'usuario_id' => $usuario->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $datos['motivo'] ?? 'Cambio de estado',
                'datos_snapshot' => [
                    'folio_traspaso' => $updates['folio_traspaso'] ?? $solicitud->folio_traspaso,
                    'evidencia_respuesta_path' => $rutaEvidencia,
                    'tiene_evidencia' => ! empty($rutaEvidencia),
                ],
            ]);

            $tipo = $esError ? 'rechazada' : 'respondida';
            $mensaje = $esError
                ? 'Se reportó un error en tu solicitud de traspaso.'
                : 'Tu solicitud de traspaso fue procesada. Revisa el folio y la captura.';

            $fresh = $solicitud->fresh(['vendedor', 'estado', 'cliente']);

            $this->notificar->respuesta(
                $fresh,
                $tipo,
                $mensaje,
                $usuario->id
            );

            if (! $esError) {
                $this->notificar->listoParaCedis($fresh, $usuario->id);
            }

            return $solicitud->fresh([
                'vendedor',
                'estado',
                'cliente',
                'almacenOrigen',
                'horario',
                'productos',
                'respondidaPor',
            ]);
        });
    }
}
