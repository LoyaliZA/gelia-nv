<?php

namespace App\Services\Solicitudes;

use App\Models\SolicitudTag;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\AuditoriaSolicitud;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class SolicitarCancelacionSolicitudService
{
    public function __construct(
        private ValidarListaInferiorService $validarListaInferior,
    ) {}

    public function ejecutar(SolicitudTag $solicitud, string $motivo, ?int $listaRebajaId = null, ?string $permisoAuth = null, ?array $permisosNotificacion = null): void
    {
        Gate::authorize($permisoAuth ?? 'solicitudes.solicitar_cancelacion');

        $permisosEncargados = $permisosNotificacion ?? ['solicitudes.verificar', 'solicitudes.reportar', 'solicitudes.cancelar'];

        DB::transaction(function () use ($solicitud, $motivo, $listaRebajaId, $permisosEncargados) {
            $solicitud->loadMissing(['estado', 'vendedor', 'departamento', 'proceso', 'cliente.listaDescuento', 'listaDescuento']);

            if ($solicitud->vendedor_id !== Auth::id()) {
                abort(403, 'Solo la vendedora dueña puede solicitar la cancelación.');
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

            $listaRebaja = null;
            $esCambioLista = $this->validarListaInferior->esProcesoCambioLista($solicitud);

            if ($esCambioLista) {
                if (!$listaRebajaId) {
                    abort(422, 'Debe indicar a qué lista inferior debe rebajarse el cliente.');
                }
                $listaRebaja = $this->validarListaInferior->validarListaInferior($solicitud, $listaRebajaId);
            } elseif ($listaRebajaId) {
                abort(422, 'La lista de rebaja solo aplica a solicitudes de cambio de lista.');
            }

            $solicitud->update([
                'cancelacion_solicitada_at' => now(),
                'motivo_cancelacion' => $motivo,
                'catalogo_lista_rebaja_id' => $listaRebaja?->id,
            ]);

            $motivoAuditoria = 'SOLICITUD DE CANCELACIÓN: ' . $motivo;
            if ($listaRebaja) {
                $motivoAuditoria .= ' | Lista rebaja: ' . $listaRebaja->nombre;
            }

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => $motivoAuditoria,
                'datos_snapshot' => null,
            ]);

            $encargadosPorDepto = $solicitud->departamento_id
                ? User::permission($permisosEncargados)
                    ->whereHas('departamentos', function ($query) use ($solicitud) {
                        $query->where('departamentos.id', $solicitud->departamento_id);
                    })
                    ->get()
                : collect();

            $adminsGlobales = User::role(['Super Admin', 'Administrador'])->get();

            $encargados = $encargadosPorDepto->merge($adminsGlobales)
                ->unique('id')
                ->reject(fn ($u) => $u->id === Auth::id());

            if ($encargados->isNotEmpty()) {
                $mensaje = Auth::user()->name . ' solicita cancelar la solicitud FOL-' . $solicitud->id . '.';
                if ($listaRebaja) {
                    $mensaje .= ' Lista rebaja solicitada: ' . $listaRebaja->nombre . '.';
                }

                Notification::send(
                    $encargados,
                    new AlertaSolicitud(
                        $solicitud,
                        'cancelacion_solicitada',
                        $mensaje
                    )
                );
            }
        });
    }
}
