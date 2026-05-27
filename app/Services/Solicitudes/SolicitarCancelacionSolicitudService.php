<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\AuditoriaSolicitud;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SolicitarCancelacionSolicitudService
{
    public function ejecutar(SolicitudTag $solicitud, string $motivo): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            $solicitud->loadMissing(['estado', 'vendedor', 'departamento']);

            if ($solicitud->vendedor_id !== Auth::id()) {
                abort(403, 'Solo la vendedora dueña puede solicitar la cancelación.');
            }

            if ($solicitud->cancelacion_solicitada_at) {
                abort(422, 'Ya existe una solicitud de cancelación pendiente para este folio.');
            }

            $estadoCancelada = CatalogoEstadoSolicitud::where('nombre', 'Cancelada')->first();
            $estadosPermitidos = ['Pendiente', 'Respondida', 'Verificada'];

            if (!in_array($solicitud->estado?->nombre, $estadosPermitidos)) {
                abort(422, 'Esta solicitud no puede ser cancelada en su estado actual.');
            }

            if ($solicitud->motivo_incorrecta === 'vencimiento_pago') {
                abort(422, 'Las solicitudes con pago vencido no pueden solicitar cancelación.');
            }

            $solicitud->update([
                'cancelacion_solicitada_at' => now(),
                'motivo_cancelacion' => $motivo,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => 'SOLICITUD DE CANCELACIÓN: ' . $motivo,
                'datos_snapshot' => null,
            ]);

            $encargados = User::permission(['solicitudes.verificar', 'solicitudes.reportar', 'solicitudes.cancelar'])
                ->whereHas('departamentos', function ($query) use ($solicitud) {
                    $query->where('departamentos.id', $solicitud->departamento_id);
                })
                ->get();

            if ($encargados->isNotEmpty()) {
                Notification::send(
                    $encargados,
                    new AlertaSolicitud(
                        $solicitud,
                        'cancelacion_solicitada',
                        Auth::user()->name . ' solicita cancelar la solicitud FOL-' . $solicitud->id . '.'
                    )
                );
            }
        });
    }
}
