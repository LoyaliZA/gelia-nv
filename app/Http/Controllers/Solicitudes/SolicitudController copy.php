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
        
        $procesos = \App\Models\CatalogoProceso::where('activo', true)->get();

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => $solicitudes,
            'filtros' => $request->all(),
            'procesos' => $procesos,
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
        Gate::authorize('solicitudes.editar');

        DB::transaction(function () use ($solicitud) {
            $solicitud->update(['pago_confirmado' => true]);

            // REGISTRO EN BITÁCORA: Confirmar pago es un evento auditable
            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $solicitud->catalogo_estado_solicitud_id,
                'estado_nuevo_id' => $solicitud->catalogo_estado_solicitud_id, // El estado no cambia, pero el evento sí
                'motivo_reporte' => 'PAGO CONFIRMADO POR ADMINISTRACIÓN'
            ]);
        });

        return back()->with('success', 'Pago confirmado. El área administrativa ha sido notificada.');
    }

    public function actualizarEstado(Request $request, SolicitudTag $solicitud)
    {
        Gate::any(['solicitudes.verificar', 'solicitudes.reportar']);

        $request->validate([
            'catalogo_estado_solicitud_id' => 'required|exists:catalogo_estados_solicitud,id',
            'motivo' => 'nullable|string'
        ]);

        $estadoAnteriorId = $solicitud->catalogo_estado_solicitud_id;
        $estadoNuevoId = $request->catalogo_estado_solicitud_id;

        DB::transaction(function () use ($solicitud, $estadoAnteriorId, $estadoNuevoId, $request) {
            
            $solicitud->update([
                'catalogo_estado_solicitud_id' => $estadoNuevoId,
            ]);

            AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'motivo_reporte' => $request->motivo ?: 'CAMBIO DE ESTADO OPERATIVO'
            ]);

            if ($estadoNuevoId == 3) {
                $tabulador = TabuladorComision::where('catalogo_proceso_id', $solicitud->catalogo_proceso_id)->first();
                if ($tabulador) {
                    $montoComision = $tabulador->monto_vendedora;
                    // Lógica de inserción de comisión aquí...
                }
            }
        });

        return back()->with('success', 'El estado de la solicitud ha sido actualizado correctamente.');
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
}