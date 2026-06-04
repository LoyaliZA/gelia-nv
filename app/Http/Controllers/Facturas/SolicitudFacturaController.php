<?php

namespace App\Http\Controllers\Facturas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturas\ResponderSolicitudFacturaRequest;
use App\Http\Requests\Facturas\StoreSolicitudFacturaRequest;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Services\Facturas\CrearSolicitudFacturaService;
use App\Services\Facturas\EliminarSolicitudFacturaService;
use App\Services\Facturas\ImportarDatosFiscalesService;
use App\Services\Facturas\ListarSolicitudesFacturaService;
use App\Services\Facturas\ResponderSolicitudFacturaService;
use App\Notifications\AlertaFactura;
use App\Models\AuditoriaSolicitudFactura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class SolicitudFacturaController extends Controller
{
    public function index(Request $request, ListarSolicitudesFacturaService $listarService): Response
    {
        Gate::authorize('facturas.ver_listado');

        $facturas = $listarService->ejecutar(Auth::user(), $request->all());
        $metricas = $listarService->metricas(Auth::user());

        $vendedores = User::permission('facturas.crear')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Facturas/Index', [
            'facturas' => $facturas,
            'metricas' => $metricas,
            'filtros' => $request->all(),
            'vendedores' => $vendedores,
            'estados' => CatalogoEstadoSolicitud::orderBy('id')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreSolicitudFacturaRequest $request, CrearSolicitudFacturaService $crearService): RedirectResponse
    {
        $datos = $request->validated();
        if ($request->hasFile('archivo_fiscal')) {
            $datos['archivo_fiscal'] = $request->file('archivo_fiscal');
        }
        if ($request->hasFile('vouchers')) {
            $datos['vouchers'] = $request->file('vouchers');
        }

        $crearService->ejecutar($datos, Auth::id());

        return redirect()->back()->with('success', 'Solicitud de factura creada correctamente.');
    }

    public function show(SolicitudFactura $factura, ListarSolicitudesFacturaService $listarService): JsonResponse
    {
        Gate::authorize('facturas.ver_listado');

        if (!$listarService->usuarioPuedeVer(Auth::user(), $factura)) {
            abort(403);
        }

        $factura->load([
            'vendedor:id,name',
            'estado:id,nombre',
            'cliente:id,numero_cliente,nombre,rfc,codigo_postal,regimen_fiscal,correo_electronico,uso_factura,nombre_razon_social',
            'vouchers:id,solicitud_factura_id,path,nombre_original,orden,mime',
            'respondidaPor:id,name',
            'auditorias.usuario:id,name',
            'auditorias.estadoNuevo:id,nombre',
        ]);

        return response()->json(['factura' => $factura]);
    }

    public function actualizarEstado(
        ResponderSolicitudFacturaRequest $request,
        SolicitudFactura $factura,
        ResponderSolicitudFacturaService $responderService
    ): RedirectResponse {
        $idPendiente = CatalogoEstadoSolicitud::idDe('Pendiente');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');

        if ($idPendiente !== null && $idRespondida !== null
            && (int) $factura->catalogo_estado_solicitud_id !== $idPendiente
            && (int) $request->catalogo_estado_solicitud_id === $idRespondida) {
            abort(422, 'Solo se puede aprobar una solicitud en estado Pendiente.');
        }

        $datos = $request->validated();
        if ($request->hasFile('factura_pdf')) {
            $datos['factura_pdf'] = $request->file('factura_pdf');
        }
        if ($request->hasFile('factura_xml')) {
            $datos['factura_xml'] = $request->file('factura_xml');
        }
        if ($request->hasFile('evidencia_error')) {
            $datos['evidencia_error'] = $request->file('evidencia_error');
        }

        $responderService->ejecutar($factura, $datos, Auth::user());

        return redirect()->back()->with('success', 'Solicitud de factura actualizada.');
    }

    public function verificar(SolicitudFactura $factura): RedirectResponse
    {
        Gate::authorize('facturas.verificar');

        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idVerificada = CatalogoEstadoSolicitud::idDe('Verificada');

        if ($idRespondida === null || (int) $factura->catalogo_estado_solicitud_id !== $idRespondida) {
            abort(422, 'Solo se pueden verificar solicitudes respondidas.');
        }

        $estadoAnterior = $factura->catalogo_estado_solicitud_id;
        $factura->update(['catalogo_estado_solicitud_id' => $idVerificada]);

        AuditoriaSolicitudFactura::create([
            'solicitud_factura_id' => $factura->id,
            'usuario_id' => Auth::id(),
            'estado_anterior_id' => $estadoAnterior,
            'estado_nuevo_id' => $idVerificada,
            'motivo_reporte' => 'Solicitud verificada por auxiliar.',
        ]);

        if ($factura->vendedor) {
            $factura->vendedor->notify(new AlertaFactura($factura, 'verificada', 'Tu solicitud de factura fue verificada.'));
        }

        return redirect()->back()->with('success', 'Solicitud verificada.');
    }

    public function destroy(SolicitudFactura $factura, Request $request, EliminarSolicitudFacturaService $eliminarService): RedirectResponse
    {
        Gate::authorize('facturas.eliminar');

        $request->validate(['motivo' => 'required|string|min:5|max:500']);
        $eliminarService->ejecutar($factura, $request->motivo);

        return redirect()->back()->with('success', 'Solicitud eliminada.');
    }

    public function descargarPlantilla(ImportarDatosFiscalesService $importarService)
    {
        Gate::authorize('facturas.crear');

        return $importarService->descargarPlantilla();
    }

    public function datosFiscales(SolicitudFactura $factura, ImportarDatosFiscalesService $importarService, ListarSolicitudesFacturaService $listarService): JsonResponse
    {
        Gate::authorize('facturas.ver_listado');

        if (!$listarService->usuarioPuedeVer(Auth::user(), $factura)) {
            abort(403);
        }

        $etiquetas = $importarService->etiquetasParaUi();

        if (!empty($factura->datos_fiscales)) {
            return response()->json(['datos' => $factura->datos_fiscales, 'etiquetas' => $etiquetas]);
        }

        if (!$factura->archivo_fiscal_path || !Storage::disk('public')->exists($factura->archivo_fiscal_path)) {
            return response()->json(['datos' => null, 'etiquetas' => $etiquetas]);
        }

        try {
            $extension = strtolower(pathinfo($factura->archivo_fiscal_path, PATHINFO_EXTENSION));
            $rutaAbsoluta = Storage::disk('public')->path($factura->archivo_fiscal_path);
            $datos = $importarService->extraerDesdeRuta($rutaAbsoluta, $extension);
            $factura->update(['datos_fiscales' => $datos]);

            return response()->json(['datos' => $datos, 'etiquetas' => $etiquetas]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensaje = collect($e->errors())->flatten()->first();

            return response()->json(['datos' => null, 'etiquetas' => $etiquetas, 'error' => $mensaje], 422);
        }
    }

    public function exportar(Request $request, ListarSolicitudesFacturaService $listarService)
    {
        Gate::authorize('facturas.exportar');

        $facturas = $listarService->ejecutar(Auth::user(), $request->all(), paginar: false);

        $filas = $facturas->map(fn (SolicitudFactura $f) => [
            'Folio' => $f->folio,
            'Razón Social' => $f->razon_social,
            'RFC' => $f->datos_fiscales['rfc'] ?? '',
            'Estado' => $f->estado->nombre ?? '',
            'Vendedor' => $f->vendedor->name ?? '',
            'Vouchers' => $f->vouchers->count(),
            'PDF Emitido' => $f->tiene_pdf_emitido ? 'Sí' : 'No',
            'XML' => $f->tiene_xml ? 'Sí' : 'No',
            'Fecha' => $f->created_at?->format('Y-m-d H:i'),
        ]);

        return (new FastExcel($filas))->download('solicitudes-facturas-' . now()->format('Y-m-d') . '.xlsx');
    }
}
