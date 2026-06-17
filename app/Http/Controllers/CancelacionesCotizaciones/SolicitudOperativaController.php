<?php

namespace App\Http\Controllers\CancelacionesCotizaciones;

use App\Events\SolicitudOperativaActualizada;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelacionesCotizaciones\StoreSolicitudOperativaRequest;
use App\Models\AuditoriaSolicitud;
use App\Models\CatalogoBanco;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoProceso;
use App\Models\SolicitudTag;
use App\Models\User;
use App\Notifications\AlertaSolicitud;
use App\Services\CancelacionesCotizaciones\ListarSolicitudesOperativasService;
use App\Services\Solicitudes\CancelarSolicitudService;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\EliminarSolicitudService;
use App\Services\Solicitudes\SolicitarCancelacionSolicitudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class SolicitudOperativaController extends Controller
{
    public function index(Request $request, ListarSolicitudesOperativasService $listarService): Response
    {
        Gate::authorize('cancelaciones_cotizaciones.ver_listado');

        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all());
        $metricas = $listarService->metricas(Auth::user());

        $procesos = CatalogoProceso::where('activo', true)
            ->where('categoria_flujo', CatalogoProceso::CATEGORIA_OPERATIVO)
            ->orderBy('nombre')
            ->get();

        $vendedores = User::permission('cancelaciones_cotizaciones.crear')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('CancelacionesCotizaciones/Index', [
            'solicitudes' => $solicitudes,
            'metricas' => $metricas,
            'filtros' => $request->all(),
            'procesos' => $procesos,
            'bancos' => CatalogoBanco::where('activo', true)->orderBy('nombre')->get(),
            'vendedores' => $vendedores,
            'estados' => CatalogoEstadoSolicitud::orderBy('id')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreSolicitudOperativaRequest $request, CrearSolicitudService $crearService): RedirectResponse
    {
        $solicitud = $crearService->ejecutar($request->validated(), Auth::id());

        event(new SolicitudOperativaActualizada(
            solicitudId: $solicitud->id,
            accion: 'creada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        return redirect()->back()->with('success', 'Solicitud operativa creada correctamente.');
    }

    public function actualizarEstado(
        Request $request,
        SolicitudTag $solicitud,
        ListarSolicitudesOperativasService $listarService
    ): RedirectResponse {
        $listarService->asegurarOperativa($solicitud);

        if (!$listarService->usuarioPuedeVer(Auth::user(), $solicitud)) {
            abort(403);
        }

        $usuario = Auth::user();
        $puedeGestionarEstado = $usuario->hasAnyPermission([
            'cancelaciones_cotizaciones.verificar',
            'cancelaciones_cotizaciones.reportar',
        ]) || $usuario->hasRole('Gerente');

        if (!$puedeGestionarEstado) {
            abort(403, 'No tienes permiso para actualizar el estado de esta solicitud.');
        }

        $request->validate([
            'catalogo_estado_solicitud_id' => 'required|exists:catalogo_estados_solicitud,id',
            'motivo' => 'nullable|string',
            'evidencia_respuesta' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
        $estadoNuevoId = (int) $request->catalogo_estado_solicitud_id;

        if ($estadoNuevoId === 2) {
            Gate::authorize('cancelaciones_cotizaciones.reportar');
            if ($estadoAnteriorId != 1) {
                abort(422, 'Solo se puede aprobar una solicitud en estado Pendiente.');
            }
        }

        if ($estadoNuevoId === 3) {
            Gate::authorize('cancelaciones_cotizaciones.verificar');
            if ($estadoAnteriorId != 2) {
                abort(422, 'Solo se pueden verificar solicitudes respondidas.');
            }
        }

        if ($estadoNuevoId === 4) {
            Gate::authorize('cancelaciones_cotizaciones.reportar');
            if ($estadoAnteriorId === 4) {
                abort(422, 'Esta solicitud ya está marcada como incorrecta.');
            }
        }

        DB::transaction(function () use ($solicitud, $estadoAnteriorId, $estadoNuevoId, $request) {
            $rutaEvidencia = $solicitud->evidencia_respuesta_path;
            if ($request->hasFile('evidencia_respuesta')) {
                if ($rutaEvidencia && Storage::disk('public')->exists($rutaEvidencia)) {
                    Storage::disk('public')->delete($rutaEvidencia);
                }
                $rutaEvidencia = $request->file('evidencia_respuesta')->store('evidencias_respuestas', 'public');
            }

            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
                'evidencia_respuesta_path' => $rutaEvidencia,
                'motivo_incorrecta' => $estadoNuevoId === 4 ? 'error_reportado' : null,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $request->motivo ?: 'CAMBIO DE ESTADO OPERATIVO',
                'datos_snapshot' => ['evidencia_respuesta_path' => $rutaEvidencia],
            ]);

            $destinatarios = $this->obtenerDestinatarios($solicitud, true);

            if ($destinatarios->isNotEmpty()) {
                $tipoAlerta = $estadoNuevoId === 4 ? 'rechazada' : 'actualizacion';
                $mensaje = $estadoNuevoId === 4
                    ? 'Se ha reportado un error en tu solicitud operativa. Revisa las observaciones.'
                    : 'El área administrativa ha emitido una resolución para tu solicitud operativa.';

                Notification::send($destinatarios, new AlertaSolicitud($solicitud, $tipoAlerta, $mensaje));
            }
        });

        event(new SolicitudOperativaActualizada(
            solicitudId: $solicitud->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        return back()->with('success', 'El estado ha sido actualizado correctamente.');
    }

    public function solicitarCancelacion(
        Request $request,
        SolicitudTag $solicitud,
        SolicitarCancelacionSolicitudService $service,
        ListarSolicitudesOperativasService $listarService
    ): RedirectResponse {
        $listarService->asegurarOperativa($solicitud);

        $request->validate([
            'motivo_cancelacion' => 'required|string|min:10|max:1000',
        ]);

        $service->ejecutar(
            $solicitud,
            $request->motivo_cancelacion,
            null,
            'cancelaciones_cotizaciones.solicitar_cancelacion',
            ['cancelaciones_cotizaciones.verificar', 'cancelaciones_cotizaciones.reportar', 'cancelaciones_cotizaciones.cancelar']
        );

        event(new SolicitudOperativaActualizada(
            solicitudId: $solicitud->id,
            accion: 'cancelacion_solicitada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        return back()->with('success', 'Solicitud de cancelación enviada al área administrativa.');
    }

    public function cancelar(
        Request $request,
        SolicitudTag $solicitud,
        CancelarSolicitudService $service,
        ListarSolicitudesOperativasService $listarService
    ): RedirectResponse {
        Gate::authorize('cancelaciones_cotizaciones.cancelar');
        $listarService->asegurarOperativa($solicitud);

        $request->validate([
            'motivo_cancelacion' => 'nullable|string|max:1000',
        ]);

        $service->ejecutar($solicitud, $request->motivo_cancelacion);

        event(new SolicitudOperativaActualizada(
            solicitudId: $solicitud->id,
            accion: 'cancelada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        return back()->with('success', 'La solicitud ha sido cancelada correctamente.');
    }

    public function destroy(
        SolicitudTag $solicitud,
        Request $request,
        EliminarSolicitudService $eliminarService,
        ListarSolicitudesOperativasService $listarService
    ): RedirectResponse {
        Gate::authorize('cancelaciones_cotizaciones.eliminar');
        $listarService->asegurarOperativa($solicitud);

        $request->validate([
            'motivo' => 'required|string|min:10|max:255',
        ]);

        $vendedorId = $solicitud->vendedor_id;
        $departamentoId = $solicitud->departamento_id;
        $solicitudId = $solicitud->id;

        $eliminarService->ejecutar($solicitud, $request->motivo);

        event(new SolicitudOperativaActualizada(
            solicitudId: $solicitudId,
            accion: 'eliminada',
            porUsuarioId: Auth::id(),
            vendedorId: $vendedorId,
            departamentoId: $departamentoId,
        ));

        return redirect()->route('cancelaciones_cotizaciones.index')
            ->with('success', 'La solicitud ha sido eliminada y el evento ha sido auditado.');
    }

    public function exportar(Request $request, ListarSolicitudesOperativasService $listarService)
    {
        Gate::authorize('cancelaciones_cotizaciones.exportar');

        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all(), paginar: false);
        $nombreArchivo = 'cancelaciones_cotizaciones_' . date('Y-m-d_H-i-s') . '.xlsx';

        return (new FastExcel($solicitudes))->download($nombreArchivo, function ($solicitud) {
            return [
                'Folio' => 'FOL-' . $solicitud->id,
                'Fecha' => $solicitud->created_at->format('Y-m-d H:i'),
                'Vendedora' => $solicitud->vendedor->name ?? 'N/A',
                'No. Cliente' => $solicitud->cliente->numero_cliente ?? 'N/A',
                'Nombre Cliente' => $solicitud->cliente->nombre ?? 'N/A',
                'Tipo de Proceso' => $solicitud->proceso->nombre ?? 'N/A',
                'Estado' => $solicitud->estado->nombre ?? 'N/A',
                'N° Remisión' => $solicitud->numero_remision ?? '',
                'N° Pedido' => $solicitud->numero_pedido ?? '',
                'Fecha Operación' => $solicitud->fecha_operacion?->format('Y-m-d') ?? '',
                'Motivo Operación' => $solicitud->motivo_operacion ?? '',
                'Banco' => $solicitud->banco?->nombre ?? '',
                'Solicitar Cotización' => $solicitud->solicitar_cotizacion ? 'Sí' : 'No',
                'Observaciones' => $solicitud->observaciones_vendedor ?? '',
                'Cancelación Solicitada' => $solicitud->cancelacion_solicitada_at?->format('Y-m-d H:i') ?? '',
            ];
        });
    }

    private function obtenerDestinatarios(SolicitudTag $solicitud, bool $incluirVendedor = false)
    {
        $destinatarios = collect();

        if ($incluirVendedor && $solicitud->vendedor) {
            $destinatarios->push($solicitud->vendedor);
        }

        $encargados = User::permission(['cancelaciones_cotizaciones.verificar', 'cancelaciones_cotizaciones.reportar'])
            ->whereHas('departamentos', function ($query) use ($solicitud) {
                $query->where('departamentos.id', $solicitud->departamento_id);
            })
            ->get();

        return $destinatarios->merge($encargados)
            ->unique('id')
            ->reject(fn ($usuario) => $usuario->id === Auth::id());
    }
}
