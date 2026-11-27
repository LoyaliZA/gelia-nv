<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use App\Notifications\AlertaSolicitud;
use App\Support\Solicitudes\EfectosCancelacionSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class SolicitarCancelacionSolicitudService
{
    public function ejecutar(SolicitudTag $solicitud, string $motivo, ?string $permisoAuth = null, ?array $permisosNotificacion = null): void
    {
        Gate::authorize($permisoAuth ?? 'solicitudes.solicitar_cancelacion');

        $permisosEncargados = $permisosNotificacion ?? ['solicitudes.verificar', 'solicitudes.reportar', 'solicitudes.cancelar'];

        DB::transaction(function () use ($solicitud, $motivo, $permisosEncargados) {
            $solicitud->loadMissing(['estado', 'vendedor', 'departamento', 'proceso', 'cliente.listaDescuento', 'listaDescuento', 'auditorias.estadoAnterior']);

            if ($solicitud->vendedor_id !== Auth::id()) {
                abort(403, 'Solo la persona dueña del folio puede solicitar la cancelación.');
            }

            if ($solicitud->cancelacion_solicitada_at) {
                abort(422, 'Ya existe una solicitud de cancelación pendiente para este folio.');
            }

            $estadosPermitidos = ['Pendiente', 'Respondida', 'Verificada'];

            if (!in_array($solicitud->estado?->nombre, $estadosPermitidos)) {
                abort(422, 'Esta solicitud no puede ser cancelada en su estado actual.');
            }

            if ($solicitud->motivo_incorrecta === 'vencimiento_pago') {
                abort(422, 'Las solicitudes con pago vencido no pueden solicitar cancelación.');
            }

            $efectos = app(EfectosCancelacionSolicitud::class);
            $textoBitacora = $efectos->textoBitacora($solicitud, $motivo);
            $mensajeAlerta = $efectos->mensajeAlerta($solicitud, Auth::user()->name);

            $solicitud->update([
                'cancelacion_solicitada_at' => now(),
                'motivo_cancelacion' => $motivo,
                'catalogo_lista_rebaja_id' => null,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => $textoBitacora,
                'datos_snapshot' => null,
            ]);

            $encargados = app(ResolverDestinatariosAlertaSolicitudService::class)
                ->porDepartamento(
                    $solicitud->departamento_id,
                    $permisosEncargados,
                    Auth::id(),
                );

            if ($encargados->isNotEmpty()) {
                Notification::send(
                    $encargados,
                    new AlertaSolicitud(
                        $solicitud,
                        'cancelacion_solicitada',
                        $mensajeAlerta,
                    )
                );
            }
        });
    }
}
