<?php

namespace App\Services\Solicitudes;

use App\Models\ConsultaSolicitud;
use App\Models\SolicitudTag;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResponderConsultaSolicitudService
{
    public function ejecutar(SolicitudTag $solicitud, ConsultaSolicitud $consulta, array $datos): ConsultaSolicitud
    {
        return DB::transaction(function () use ($solicitud, $consulta, $datos) {
            if ($consulta->solicitud_id !== $solicitud->id) {
                abort(404);
            }

            if ($consulta->estado !== 'pendiente') {
                abort(422, 'Esta consulta ya fue respondida.');
            }

            $rutaEvidencia = $consulta->evidencia_respuesta_path;
            if (!empty($datos['evidencia_respuesta'])
                && $datos['evidencia_respuesta'] instanceof \Illuminate\Http\UploadedFile
                && $datos['evidencia_respuesta']->isValid()) {
                if ($rutaEvidencia && Storage::disk('public')->exists($rutaEvidencia)) {
                    Storage::disk('public')->delete($rutaEvidencia);
                }
                $rutaEvidencia = $datos['evidencia_respuesta']->store('evidencias_consultas', 'public');
            }

            $respuestaPositiva = filter_var($datos['respuesta_positiva'], FILTER_VALIDATE_BOOLEAN);

            $consulta->update([
                'estado' => 'respondida',
                'respuesta_positiva' => $respuestaPositiva,
                'comentario_encargada' => $datos['comentario_encargada'] ?? null,
                'evidencia_respuesta_path' => $rutaEvidencia,
                'encargada_id' => Auth::id(),
            ]);

            $temas = array_filter([
                $consulta->consulta_tag ? 'TAG' : null,
                $consulta->consulta_lista ? 'Lista' : null,
            ]);

            $resultado = $respuestaPositiva ? 'confirmada' : 'rechazada';

            if ($solicitud->vendedor) {
                $solicitud->vendedor->notify(new AlertaSolicitud(
                    $solicitud,
                    'consulta_respondida',
                    'Tu consulta de ' . implode(' y ', $temas) . ' fue ' . $resultado . ' por el área administrativa.',
                    [
                        'consulta_temas' => $temas,
                        'respuesta_positiva' => $respuestaPositiva,
                    ]
                ));
            }

            return $consulta->fresh(['encargada']);
        });
    }
}
