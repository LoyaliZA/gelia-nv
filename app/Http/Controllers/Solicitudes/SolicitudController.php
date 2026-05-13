<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitudes\StoreSolicitudRequest;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use Illuminate\Http\RedirectResponse;
use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use App\Models\TabuladorComision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use \App\Models\CatalogoProceso;
use \App\Models\CatalogoListaDescuento;
use \App\Models\CatalogoTipoCliente;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class SolicitudController extends Controller
{
    public function index(Request $request, ListarSolicitudesService $listarService): Response
    {
        // Asegúrate de que el Service esté configurado para incluir: 
        // ->with(['auditorias.usuario', 'auditorias.estado_nuevo'])
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all());

        $procesos = CatalogoProceso::where('activo', true)->get();

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->all(),
            'procesos' => $procesos,
            // ESTAS DOS LÍNEAS SON LAS QUE FALTABAN PARA LLENAR LOS SELECTS:
            'listas'        => CatalogoListaDescuento::where('activo', true)->get(),
            'tipos_cliente' => CatalogoTipoCliente::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(StoreSolicitudRequest $request, CrearSolicitudService $crearSolicitudService): RedirectResponse
    {
        $datosValidados = $request->validated();
        $crearSolicitudService->ejecutar($datosValidados, Auth::id());

        return redirect()->back()->with('success', 'Solicitud creada correctamente.');
    }

    public function confirmarPago(Request $request, SolicitudTag $solicitud)
    {
        // 1. PARCHE DE SEGURIDAD: Bloquear confirmación si hay incidencia (Estado 4 = Incorrecta)
        if ($solicitud->catalogo_estado_solicitud_id == 4) {
            abort(403, 'Acción Bloqueada: No puedes confirmar el pago de una solicitud con incidencias. Repárala primero.');
        }

        // 2. AUTORIZACIÓN: Permitir a la colaboradora dueña de la solicitud o a un administrador
        if ($solicitud->vendedor_id !== Auth::id() && !Auth::user()->can('solicitudes.confirmar_pago')) {
            abort(403, 'No tienes autorización para confirmar este pago.');
        }

        DB::transaction(function () use ($solicitud) {
            $solicitud->update(['pago_confirmado' => true]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id,
                'motivo_reporte' => 'PAGO CONFIRMADO POR EL COLABORADOR'
            ]);

            // 3. NOTIFICACIÓN INVERTIDA: Del Colaborador hacia Administración (Gerentes/Admins)
            $encargados = \App\Models\User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();
            
            if ($encargados->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($encargados, new \App\Notifications\AlertaSolicitud(
                    $solicitud, 
                    'pago_confirmado', 
                    'El colaborador ' . Auth::user()->name . ' ha notificado la confirmación de pago para la solicitud FOL-' . $solicitud->id . '.'
                ));
            }
        });

        return back()->with('success', 'Pago confirmado y notificado a la administración.');
    }

    public function update(Request $request, SolicitudTag $solicitud)
    {
        if ($solicitud->vendedor_id !== Auth::id() || $solicitud->catalogo_estado_solicitud_id != 4) {
            abort(403, 'No tienes permiso para editar esta solicitud o no está en estado Incorrecto.');
        }

        $request->validate([
            'monto_cotizado' => 'required|numeric|min:0',
            'catalogo_proceso_id' => 'required|exists:catalogo_procesos,id',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        DB::transaction(function () use ($solicitud, $request) {
            $rutaEvidencia = $solicitud->evidencia_path;
            if ($request->hasFile('evidencia')) {
                $rutaEvidencia = $request->file('evidencia')->store('evidencias/solicitudes', 'public');
            }

            $solicitud->update([
                'monto_cotizado' => $request->monto_cotizado,
                'catalogo_proceso_id' => $request->catalogo_proceso_id,
                'evidencia_path' => $rutaEvidencia,
                'catalogo_estado_solicitud_id' => 1, 
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => 4,
                'estado_nuevo_id' => 1,
                'motivo_reporte' => 'El colaborador corrigió la solicitud y subió nueva evidencia.',
                // SNAPSHOT: Guardamos la foto y datos de esta corrección exacta
                'datos_snapshot' => [
                    'monto_cotizado' => $request->monto_cotizado,
                    'proceso_id' => $request->catalogo_proceso_id,
                    'evidencia_path' => $rutaEvidencia,
                ]
            ]);

            $gerentes = \App\Models\User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();
            
            \Illuminate\Support\Facades\Notification::send($gerentes, new \App\Notifications\AlertaSolicitud(
                $solicitud,
                'reparada',
                'El colaborador ' . Auth::user()->name . ' ha reparado la solicitud FOL-' . $solicitud->id . '.'
            ));
        });

        return back()->with('success', 'Solicitud corregida y enviada a revisión.');
    }

    // --- FUNCIÓN ACTUALIZADA CON EVIDENCIA DE RESPUESTA Y NOTIFICACIÓN ---
    public function actualizarEstado(Request $request, SolicitudTag $solicitud)
    {
        Gate::any(['solicitudes.verificar', 'solicitudes.reportar']);

        $request->validate([
            'catalogo_estado_solicitud_id' => 'required|exists:catalogo_estados_solicitud,id',
            'motivo' => 'nullable|string',
            'evidencia_respuesta' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
        $estadoNuevoId = $request->catalogo_estado_solicitud_id;

        DB::transaction(function () use ($solicitud, $estadoAnteriorId, $estadoNuevoId, $request) {

            $rutaEvidencia = $solicitud->evidencia_respuesta_path;
            if ($request->hasFile('evidencia_respuesta')) {
                $rutaEvidencia = $request->file('evidencia_respuesta')->store('evidencias/respuestas', 'public');
            }

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'evidencia_respuesta_path' => $rutaEvidencia,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $request->motivo ?: 'CAMBIO DE ESTADO OPERATIVO',
                // SNAPSHOT: Guardamos la foto exacta que subió la encargada en este ciclo
                'datos_snapshot' => [
                    'evidencia_respuesta_path' => $rutaEvidencia
                ]
            ]);

            if ($solicitud->vendedor) {
                $tipoAlerta = $estadoNuevoId == 4 ? 'rechazada' : 'actualizacion';
                $mensaje = $estadoNuevoId == 4
                    ? 'Se ha reportado un error en tu solicitud. Revisa las observaciones.'
                    : 'El área administrativa ha emitido una resolución para tu solicitud.';

                $solicitud->vendedor->notify(new \App\Notifications\AlertaSolicitud(
                    $solicitud,
                    $tipoAlerta,
                    $mensaje
                ));
            }
        });

        return back()->with('success', 'El estado ha sido actualizado correctamente.');
    }

    public function exportar(Request $request, ListarSolicitudesService $listarService)
    {
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all(), false);
        $nombreArchivo = 'reporte_solicitudes_' . date('Y-m-d_H-i-s') . '.xlsx';

        return (new FastExcel($solicitudes))->download($nombreArchivo, function ($solicitud) {
            return [
                'Folio' => $solicitud->id,
                'Fecha Solicitud' => $solicitud->created_at->format('Y-m-d H:i'),
                'Vendedora' => $solicitud->vendedor->name ?? 'N/A',
                'No. Cliente' => $solicitud->cliente->numero_cliente ?? 'N/A',
                'Nombre Cliente' => $solicitud->cliente->nombre ?? 'N/A',
                'Tipo de Proceso' => $solicitud->proceso->nombre ?? 'N/A',
                'Estado' => $solicitud->estado->nombre ?? 'N/A',
                'Monto Cotizado ($)' => $solicitud->monto_cotizado,
                'Pago Confirmado' => $solicitud->pago_confirmado ? 'Sí' : 'No',
                'Observaciones' => $solicitud->observaciones_vendedor ?? 'Ninguna',
            ];
        });
    }

    public function rechazarPago(Request $request, SolicitudTag $solicitud)
    {
        Gate::authorize('solicitudes.confirmar_pago');

        DB::transaction(function () use ($solicitud) {
            // 1. Mandamos la solicitud a estado Incorrecta (ID 4)
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            $solicitud->update([
                'catalogo_estado_solicitud_id' => 4,
                'pago_confirmado' => false
            ]);

            // 2. REVERSIÓN AUTOMÁTICA DEL CLIENTE
            // Si la vendedora le había asignado un tipo (ej. REACTIVADO), lo regresamos a null o a su estado original
            if ($solicitud->catalogo_tipo_cliente_id && $solicitud->cliente_id) {
                $cliente = \App\Models\Cliente::find($solicitud->cliente_id);
                if ($cliente) {
                    // Aquí reviertes la lista o el tipo. Como medida de seguridad, lo desvinculamos de la sugerencia nueva.
                    $cliente->update(['catalogo_tipo_cliente_id' => null]);
                }
            }

            // 3. REGISTRO EN LA BITÁCORA
            \App\Models\AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => \Illuminate\Support\Facades\Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => 4, // Incorrecta
                'motivo_reporte' => 'PAGO RECHAZADO: Se aplicó la reversión automática de las propiedades del cliente.'
            ]);

            // 4. NOTIFICAR A LA VENDEDORA (Vía correo y web)
            if ($solicitud->vendedor) {
                $solicitud->vendedor->notify(new \App\Notifications\AlertaSolicitud(
                    $solicitud,
                    'pago_rechazado',
                    'Tu solicitud ha sido rebotada por falta de pago. Se revirtieron los cambios del cliente.'
                ));
            }
        });

        return back()->with('success', 'Pago rechazado. La vendedora ha sido notificada y el cliente fue revertido.');
    }
}
