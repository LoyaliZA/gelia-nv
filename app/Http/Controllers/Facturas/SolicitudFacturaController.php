<?php

namespace App\Http\Controllers\Facturas;

use App\Events\SolicitudFacturaActualizada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facturas\CorregirSolicitudFacturaEncargadaRequest;
use App\Http\Requests\Facturas\ActualizarBorradorFacturaRequest;
use App\Http\Requests\Facturas\RepararSolicitudFacturaRequest;
use App\Http\Requests\Facturas\ResponderSolicitudFacturaRequest;
use App\Http\Requests\Facturas\StoreSolicitudFacturaRequest;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudFactura;
use App\Models\User;
use App\Services\Facturas\ActualizarBorradorFacturaService;
use App\Services\Facturas\CrearSolicitudFacturaService;
use App\Services\Facturas\CorregirSolicitudFacturaEncargadaService;
use App\Services\Facturas\EliminarSolicitudFacturaService;
use App\Services\Facturas\GenerarEnlaceDatosFiscalesService;
use App\Services\Facturas\GestionarDatosFiscalesClienteService;
use App\Services\Facturas\ImportarDatosFiscalesService;
use App\Services\Facturas\ListarCatalogosFiscalesService;
use App\Services\Facturas\ListarSolicitudesFacturaService;
use App\Services\Facturas\RepararSolicitudFacturaService;
use App\Services\Facturas\ResponderSolicitudFacturaService;
use App\Notifications\AlertaFactura;
use App\Models\AuditoriaSolicitudFactura;
use App\Support\Facturas\FacturaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class SolicitudFacturaController extends Controller
{
    public function index(Request $request, ListarSolicitudesFacturaService $listarService, ListarCatalogosFiscalesService $catalogosService): Response
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
            'catalogos' => $catalogosService->activosParaUi(),
        ]);
    }

    public function store(StoreSolicitudFacturaRequest $request, CrearSolicitudFacturaService $crearService): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();
        if ($request->hasFile('archivo_fiscal')) {
            $datos['archivo_fiscal'] = $request->file('archivo_fiscal');
        }
        if ($request->hasFile('vouchers')) {
            $datos['vouchers'] = $request->file('vouchers');
        }
        $datos['pedir_formulario'] = $request->boolean('pedir_formulario');

        $resultado = $crearService->ejecutar($datos, Auth::id());
        $solicitud = $resultado['solicitud'];

        event(new SolicitudFacturaActualizada(
            solicitudId: $solicitud->id,
            accion: 'creada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        $mensaje = ($datos['modo'] ?? '') === 'borrador'
            ? 'Borrador guardado'.($resultado['enlace_url'] ? '. Enlace listo para compartir.' : '.')
            : 'Solicitud de factura creada correctamente.';

        if ($request->wantsJson() || $request->header('X-Inertia') === null && $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'mensaje' => $mensaje,
                'solicitud' => $solicitud,
                'enlace_url' => $resultado['enlace_url'],
            ]);
        }

        return redirect()->back()->with([
            'success' => $mensaje,
            'enlace_fiscal_url' => $resultado['enlace_url'],
            'factura_borrador_id' => ($datos['modo'] ?? '') === 'borrador' ? $solicitud->id : null,
        ]);
    }

    public function actualizarBorrador(
        ActualizarBorradorFacturaRequest $request,
        SolicitudFactura $factura,
        ActualizarBorradorFacturaService $service
    ): RedirectResponse|JsonResponse {
        $datos = $request->validated();
        $datos['pedir_formulario'] = $request->boolean('pedir_formulario');
        $datos['enviar_ahora'] = $request->boolean('enviar_ahora');
        $datos['eliminar_archivo_fiscal'] = $request->boolean('eliminar_archivo_fiscal');
        $datos['vouchers_conservar'] = $request->input('vouchers_conservar', []);
        if ($request->hasFile('archivo_fiscal')) {
            $datos['archivo_fiscal'] = $request->file('archivo_fiscal');
        }
        if ($request->hasFile('vouchers')) {
            $datos['vouchers'] = $request->file('vouchers');
        }

        try {
            $resultado = $service->ejecutar($factura, $datos, Auth::user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['borrador' => $e->getMessage()]);
        }

        $solicitud = $resultado['solicitud'];

        event(new SolicitudFacturaActualizada(
            solicitudId: $solicitud->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $solicitud->vendedor_id,
            departamentoId: $solicitud->departamento_id,
        ));

        $mensaje = $request->boolean('enviar_ahora')
            ? 'Solicitud enviada a encargada.'
            : 'Borrador actualizado.'.($resultado['enlace_url'] ? ' Enlace listo para compartir.' : '');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'mensaje' => $mensaje,
                'solicitud' => $solicitud,
                'enlace_url' => $resultado['enlace_url'],
            ]);
        }

        return redirect()->back()->with([
            'success' => $mensaje,
            'enlace_fiscal_url' => $resultado['enlace_url'],
            'factura_borrador_id' => $solicitud->id,
        ]);
    }

    public function regenerarEnlaceFiscal(
        Request $request,
        SolicitudFactura $factura,
        GenerarEnlaceDatosFiscalesService $generarEnlace,
        ListarSolicitudesFacturaService $listarService
    ): JsonResponse {
        Gate::authorize('facturas.crear');

        $usuario = Auth::user();
        if (! $listarService->usuarioPuedeVer($usuario, $factura)) {
            abort(403);
        }

        $idBorrador = CatalogoEstadoSolicitud::idDe('Borrador');
        $idRespondida = CatalogoEstadoSolicitud::idDe('Respondida');
        $idIncorrecta = CatalogoEstadoSolicitud::idDe('Incorrecta');
        $estadoId = (int) $factura->catalogo_estado_solicitud_id;
        $esBorrador = $idBorrador !== null && $estadoId === (int) $idBorrador;
        $esRespondida = $idRespondida !== null && $estadoId === (int) $idRespondida;
        $esIncorrecta = $idIncorrecta !== null && $estadoId === (int) $idIncorrecta;

        if (! $esBorrador && ! $esRespondida && ! $esIncorrecta) {
            throw ValidationException::withMessages([
                'enlace' => 'Solo se puede regenerar el enlace en borradores, solicitudes respondidas o incorrectas.',
            ]);
        }

        $esDuenio = (int) $factura->vendedor_id === (int) $usuario->id;
        $esAdmin = $usuario->hasAnyRole(['Super Admin', 'Administrador'])
            || $usuario->can('facturas.eliminar');
        $esEncargada = $usuario->can('facturas.responder') || $usuario->can('facturas.reportar_error');
        if (! $esDuenio && ! $esAdmin && ! $esEncargada) {
            abort(403, 'No tiene permiso para regenerar el enlace fiscal.');
        }

        $campos = $request->input('campos_fiscales', $factura->campos_fiscales_solicitados);
        if (! is_array($campos) || $campos === []) {
            $campos = \App\Models\EnlaceDatosFiscales::CAMPOS;
        }

        $accion = ($esRespondida || $esIncorrecta)
            ? \App\Models\EnlaceDatosFiscales::ACCION_ACTUALIZAR
            : $request->input('accion_formulario', \App\Models\EnlaceDatosFiscales::ACCION_PRIMERA);

        $resultado = $generarEnlace->ejecutar($factura, [
            'accion' => $accion,
            'campos' => $campos,
            'usuario_id' => Auth::id(),
        ]);

        return response()->json([
            'url' => $resultado['url'],
            'solicitud' => $factura->fresh(['enlacesFiscales', 'estado', 'cliente']),
        ]);
    }

    public function corregir(
        CorregirSolicitudFacturaEncargadaRequest $request,
        SolicitudFactura $factura,
        CorregirSolicitudFacturaEncargadaService $service
    ): RedirectResponse {
        $datos = $request->validated();
        if ($request->hasFile('archivo_fiscal')) {
            $datos['archivo_fiscal'] = $request->file('archivo_fiscal');
        }

        $service->ejecutar($factura, $datos, Auth::user());

        event(new SolicitudFacturaActualizada(
            solicitudId: $factura->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $factura->vendedor_id,
            departamentoId: $factura->departamento_id,
        ));

        return redirect()->back()->with('success', 'Corrección aplicada. La solicitud sigue pendiente.');
    }

    public function reparar(
        RepararSolicitudFacturaRequest $request,
        SolicitudFactura $factura,
        RepararSolicitudFacturaService $repararService
    ): RedirectResponse {
        $datos = $request->validated();
        $datos['vouchers_conservar'] = $request->input('vouchers_conservar', []);
        $datos['eliminar_archivo_fiscal'] = $request->boolean('eliminar_archivo_fiscal');
        $datos['generar_enlace_fiscal'] = $request->boolean('generar_enlace_fiscal');
        $datos['campos_fiscales'] = $request->input('campos_fiscales', []);
        if ($request->hasFile('archivo_fiscal')) {
            $datos['archivo_fiscal'] = $request->file('archivo_fiscal');
        }
        if ($request->hasFile('vouchers')) {
            $datos['vouchers'] = $request->file('vouchers');
        }

        $repararService->ejecutar($factura, $datos, Auth::user());

        event(new SolicitudFacturaActualizada(
            solicitudId: $factura->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $factura->vendedor_id,
            departamentoId: $factura->departamento_id,
        ));

        return redirect()->back()->with('success', 'Solicitud corregida y enviada a revisión.');
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
            'cliente:id,numero_cliente,nombre,rfc,codigo_postal,regimen_fiscal,correo_electronico,uso_factura,nombre_razon_social,telefono',
            'receptorFiscal:id,codigo_interno,rfc,codigo_postal,regimen_fiscal,correo_electronico,uso_factura,nombre_razon_social,telefono',
            'vouchers:id,solicitud_factura_id,path,nombre_original,orden,mime',
            'pdfsEmitidos:id,solicitud_factura_id,path,nombre_original,orden,mime',
            'enlacesFiscales',
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
        if ($request->hasFile('factura_pdfs')) {
            $datos['factura_pdfs'] = $request->file('factura_pdfs');
        }
        if ($request->hasFile('factura_xml')) {
            $datos['factura_xml'] = $request->file('factura_xml');
        }
        if ($request->hasFile('evidencia_error')) {
            $datos['evidencia_error'] = $request->file('evidencia_error');
        }

        $responderService->ejecutar($factura, $datos, Auth::user());

        event(new SolicitudFacturaActualizada(
            solicitudId: $factura->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $factura->vendedor_id,
            departamentoId: $factura->departamento_id,
        ));

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

        event(new SolicitudFacturaActualizada(
            solicitudId: $factura->id,
            accion: 'actualizada',
            porUsuarioId: Auth::id(),
            vendedorId: $factura->vendedor_id,
            departamentoId: $factura->departamento_id,
        ));

        return redirect()->back()->with('success', 'Solicitud verificada.');
    }

    public function destroy(SolicitudFactura $factura, Request $request, EliminarSolicitudFacturaService $eliminarService): RedirectResponse
    {
        $usuario = Auth::user();
        $idBorrador = CatalogoEstadoSolicitud::idDe('Borrador');
        $esBorradorPropio = $idBorrador !== null
            && (int) $factura->catalogo_estado_solicitud_id === (int) $idBorrador
            && (int) $factura->vendedor_id === (int) $usuario->id
            && $usuario->can('facturas.crear');

        if (! $esBorradorPropio && ! $usuario->can('facturas.eliminar')) {
            abort(403, 'No tiene permiso para eliminar esta solicitud.');
        }

        $request->validate(['motivo' => 'required|string|min:5|max:500']);

        $vendedorId = $factura->vendedor_id;
        $departamentoId = $factura->departamento_id;
        $facturaId = $factura->id;

        $eliminarService->ejecutar($factura, $request->motivo);

        event(new SolicitudFacturaActualizada(
            solicitudId: $facturaId,
            accion: 'eliminada',
            porUsuarioId: Auth::id(),
            vendedorId: $vendedorId,
            departamentoId: $departamentoId,
        ));

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

        if (!$factura->archivo_fiscal_path || !FacturaStorage::exists($factura->archivo_fiscal_path)) {
            return response()->json(['datos' => null, 'etiquetas' => $etiquetas]);
        }

        try {
            $extension = strtolower(pathinfo($factura->archivo_fiscal_path, PATHINFO_EXTENSION));
            $rutaAbsoluta = FacturaStorage::path($factura->archivo_fiscal_path);
            $datos = $importarService->extraerDesdeRuta($rutaAbsoluta, $extension);
            $factura->update(['datos_fiscales' => $datos]);

            return response()->json(['datos' => $datos, 'etiquetas' => $etiquetas]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $mensaje = collect($e->errors())->flatten()->first();

            return response()->json(['datos' => null, 'etiquetas' => $etiquetas, 'error' => $mensaje], 422);
        }
    }

    public function aplicarDatosFiscalesAlCliente(
        SolicitudFactura $factura,
        ImportarDatosFiscalesService $importarService,
        GestionarDatosFiscalesClienteService $gestionarService,
        ListarSolicitudesFacturaService $listarService
    ): JsonResponse {
        Gate::authorize('facturas.gestionar_datos_fiscales');

        if (!$listarService->usuarioPuedeVer(Auth::user(), $factura)) {
            abort(403);
        }

        if (!$factura->cliente_id) {
            return response()->json(['message' => 'La solicitud no tiene cliente asociado.'], 422);
        }

        $datos = $factura->datos_fiscales;

        if (empty($datos) && $factura->archivo_fiscal_path && FacturaStorage::exists($factura->archivo_fiscal_path)) {
            try {
                $extension = strtolower(pathinfo($factura->archivo_fiscal_path, PATHINFO_EXTENSION));
                $rutaAbsoluta = FacturaStorage::path($factura->archivo_fiscal_path);
                $datos = $importarService->extraerDesdeRuta($rutaAbsoluta, $extension);
                $factura->update(['datos_fiscales' => $datos]);
            } catch (ValidationException $e) {
                $mensaje = collect($e->errors())->flatten()->first();

                return response()->json(['message' => $mensaje], 422);
            }
        }

        if (empty($datos)) {
            return response()->json(['message' => 'No hay datos fiscales para aplicar.'], 422);
        }

        $cliente = $factura->cliente()->firstOrFail();

        // Snapshots previos a NUMERO TELEFONICO no deben vaciar clientes.telefono.
        if (!array_key_exists('telefono', $datos) || trim((string) $datos['telefono']) === '') {
            $datos['telefono'] = $cliente->telefono;
        }

        $gestionarService->actualizar($cliente, $datos);

        return response()->json(['message' => 'Datos fiscales del cliente actualizados.']);
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

        app(\App\Services\Facturas\RegistrarAuditoriaDatosFiscalesService::class)->exportSolicitudes(
            $filas->count(),
            [
                'q' => $request->input('q'),
                'estado' => $request->input('estado'),
            ]
        );

        return (new FastExcel($filas))->download('solicitudes-facturas-' . now()->format('Y-m-d') . '.xlsx');
    }
}
