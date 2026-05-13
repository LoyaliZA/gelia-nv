<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitudes\StoreSolicitudRequest;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use Illuminate\Http\RedirectResponse;
use App\Models\SolicitudTag;
use App\Models\AuditoriaSolicitud;
use App\Models\Cliente;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use App\Models\CatalogoProceso;
use App\Models\CatalogoListaDescuento;
use App\Models\CatalogoTipoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class SolicitudController extends Controller
{
    public function index(Request $request, ListarSolicitudesService $listarService): Response
    {
        // El service se encarga de la lógica de filtrado y carga de relaciones (Eager Loading)
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all());

        $procesos = CatalogoProceso::where('activo', true)->get();

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->all(),
            'procesos' => $procesos,
            'listas' => CatalogoListaDescuento::where('activo', true)->get(),
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

            // 3. NOTIFICACIÓN: Del Colaborador hacia Administración (Gerentes/Admins)
            $encargados = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();
            
            if ($encargados->isNotEmpty()) {
                Notification::send($encargados, new AlertaSolicitud(
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
        // Seguridad: Solo el dueño puede reparar y solo si está marcada como incorrecta
        if ($solicitud->vendedor_id !== Auth::id() || $solicitud->catalogo_estado_solicitud_id != 4) {
            abort(403, 'No tienes permiso para editar esta solicitud o no está en estado Incorrecto.');
        }

        $request->validate([
            'monto_cotizado' => 'required|numeric|min:0',
            'catalogo_proceso_id' => 'required|exists:catalogo_procesos,id',
            'catalogo_tipo_cliente_id' => 'nullable|exists:catalogo_tipo_clientes,id',
            'catalogo_lista_descuento_id' => 'nullable|exists:catalogo_listas_descuento,id',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        DB::transaction(function () use ($solicitud, $request) {
            $rutaEvidencia = $solicitud->evidencia_path;
            
            if ($request->hasFile('evidencia')) {
                // Sincronizamos con el path del CrearSolicitudService
                $rutaEvidencia = $request->file('evidencia')->store('evidencias_solicitudes', 'public');
            }

            $solicitud->update([
                'monto_cotizado' => $request->monto_cotizado,
                'catalogo_proceso_id' => $request->catalogo_proceso_id,
                'catalogo_tipo_cliente_id' => $request->catalogo_tipo_cliente_id,
                'catalogo_lista_descuento_id' => $request->catalogo_lista_descuento_id,
                'evidencia_path' => $rutaEvidencia,
                'catalogo_estado_solicitud_id' => 1, // Regresa a Pendiente
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => 4,
                'estado_nuevo_id' => 1,
                'motivo_reporte' => 'El colaborador corrigió la solicitud y subió nueva evidencia.',
                'datos_snapshot' => [
                    'monto_cotizado' => $request->monto_cotizado,
                    'proceso_id' => $request->catalogo_proceso_id,
                    'tipo_cliente_id' => $request->catalogo_tipo_cliente_id,
                    'lista_descuento_id' => $request->catalogo_lista_descuento_id,
                    'evidencia_path' => $rutaEvidencia,
                ]
            ]);

            $gerentes = User::permission(['solicitudes.verificar', 'solicitudes.reportar'])->get();
            
            Notification::send($gerentes, new AlertaSolicitud(
                $solicitud,
                'reparada',
                'El colaborador ' . Auth::user()->name . ' ha reparado la solicitud FOL-' . $solicitud->id . '.'
            ));
        });

        return back()->with('success', 'Solicitud corregida y enviada a revisión.');
    }

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
                $rutaEvidencia = $request->file('evidencia_respuesta')->store('evidencias_respuestas', 'public');
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
                'datos_snapshot' => [
                    'evidencia_respuesta_path' => $rutaEvidencia
                ]
            ]);

            if ($solicitud->vendedor) {
                $tipoAlerta = $estadoNuevoId == 4 ? 'rechazada' : 'actualizacion';
                $mensaje = $estadoNuevoId == 4
                    ? 'Se ha reportado un error en tu solicitud. Revisa las observaciones.'
                    : 'El área administrativa ha emitido una resolución para tu solicitud.';

                $solicitud->vendedor->notify(new AlertaSolicitud(
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
        // Pedimos al service que no pagine para exportar todo
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
                'Tipo Cliente (Clasificación)' => $solicitud->tipoCliente->nombre ?? 'Normal',
                'Lista Solicitada' => $solicitud->listaDescuento->nombre ?? 'Mantener Actual',
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
            $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
            
            $solicitud->update([
                'catalogo_estado_solicitud_id' => 4, // Incorrecta
                'pago_confirmado' => false
            ]);

            // REVERSIÓN AUTOMÁTICA DEL CLIENTE (Si aplica)
            if ($solicitud->cliente_id) {
                $cliente = Cliente::find($solicitud->cliente_id);
                if ($cliente) {
                    $cliente->update(['catalogo_tipo_cliente_id' => null]);
                }
            }

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => 4,
                'motivo_reporte' => 'PAGO RECHAZADO: Se aplicó la reversión automática de las propiedades del cliente.'
            ]);

            if ($solicitud->vendedor) {
                $solicitud->vendedor->notify(new AlertaSolicitud(
                    $solicitud,
                    'pago_rechazado',
                    'Tu solicitud ha sido rebotada por falta de pago. Se revirtieron los cambios del cliente.'
                ));
            }
        });

        return back()->with('success', 'Pago rechazado. La vendedora ha sido notificada.');
    }
}