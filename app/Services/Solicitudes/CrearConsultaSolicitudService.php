<?php

namespace App\Services\Solicitudes;

use App\Models\ConsultaSolicitud;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CrearConsultaSolicitudService
{
    public function ejecutar(SolicitudTag $solicitud, array $datos): ConsultaSolicitud
    {
        return DB::transaction(function () use ($solicitud, $datos) {
            $authId = (int) Auth::id();
            $vendedorId = (int) $solicitud->vendedor_id;

            if ($vendedorId !== $authId) {
                abort(403, 'Solo la vendedora dueña puede consultar.');
            }

            $estadosAprobados = [
                (int) CatalogoEstadoSolicitud::idDe('Respondida'),
                (int) CatalogoEstadoSolicitud::idDe('Verificada'),
            ];
            if (!in_array((int) $solicitud->catalogo_estado_solicitud_id, $estadosAprobados, true) || $solicitud->pago_confirmado) {
                abort(403, 'La consulta solo está disponible en solicitudes aprobadas con pago pendiente.');
            }

            $consultaTag = filter_var($datos['consulta_tag'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $consultaLista = filter_var($datos['consulta_lista'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (!$consultaTag && !$consultaLista) {
                abort(422, 'Debe seleccionar al menos TAG o Lista para consultar.');
            }

            $consultaPendiente = $solicitud->consultas()->where('estado', 'pendiente')->exists();
            if ($consultaPendiente) {
                abort(422, 'Ya existe una consulta pendiente para este folio.');
            }

            $consulta = ConsultaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'vendedor_id' => Auth::id(),
                'consulta_tag' => $consultaTag,
                'consulta_lista' => $consultaLista,
                'comentario_vendedor' => $datos['comentario_vendedor'] ?? null,
                'estado' => 'pendiente',
            ]);

            $temas = array_filter([
                $consultaTag ? 'TAG' : null,
                $consultaLista ? 'Lista' : null,
            ]);

            $encargadas = User::permission(['solicitudes.responder_consulta', 'solicitudes.reportar'])
                ->whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $solicitud->departamento_id))
                ->get();

            if ($encargadas->isNotEmpty()) {
                Notification::send($encargadas, new AlertaSolicitud(
                    $solicitud,
                    'consulta_nueva',
                    Auth::user()->name . ' consulta ' . implode(' y ', $temas) . ' del cliente en FOL-' . $solicitud->id . '.',
                    ['consulta_temas' => $temas]
                ));
            }

            return $consulta;
        });
    }
}
