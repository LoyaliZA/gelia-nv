<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitudes\StoreSolicitudRequest;
use App\Services\Solicitudes\CrearSolicitudService;
use App\Services\Solicitudes\ListarSolicitudesService;
use Illuminate\Http\RedirectResponse;
use App\Models\SolicitudTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SolicitudController extends Controller
{
    public function index(Request $request, ListarSolicitudesService $listarService): Response
    {
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
        Gate::authorize('confirmar_pago');

        $solicitud->update(['pago_confirmado' => true]);

        return back()->with('success', 'Pago confirmado. El área administrativa ha sido notificada.');
    }

    /**
     * Endpoint para que el Auxiliar o la Encargada aprueben o rechacen la solicitud.
     */
    public function actualizarEstado(Request $request, SolicitudTag $solicitud)
    {
        // Autorización dual: Debe tener cualquiera de los dos permisos operativos
        Gate::any(['verificar_auxiliar', 'ejecutar_tags']);

        $request->validate([
            'catalogo_estado_solicitud_id' => 'required|exists:catalogo_estados_solicitud,id',
            'motivo' => 'nullable|string' // En caso de que se marque como "Incorrecta"
        ]);

        $solicitud->update([
            'catalogo_estado_solicitud_id' => $request->catalogo_estado_solicitud_id,
        ]);

        // Si envían un motivo de error, lo podemos guardar en la auditoría directamente
        if ($request->filled('motivo')) {
            \App\Models\AuditoriaSolicitud::create([
                'solicitud_id' => $solicitud->id,
                'usuario_id' => Auth::id(),
                'estado_nuevo_id' => $request->catalogo_estado_solicitud_id,
                'motivo_reporte' => $request->motivo
            ]);
        }

        return back()->with('success', 'El estado de la solicitud ha sido actualizado.');
    }

    /**
     * Endpoint para exportar las solicitudes a formato Excel (.xlsx) o CSV.
     */
    public function exportar(Request $request, ListarSolicitudesService $listarService)
    {
        // Reutilizamos el servicio pasando 'false' para no paginar
        $solicitudes = $listarService->ejecutar(Auth::user(), $request->all(), false);

        $nombreArchivo = 'reporte_solicitudes_' . date('Y-m-d_H-i-s') . '.xlsx';

        // FastExcel itera sobre la colección y mapea las columnas como se define en el callback
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