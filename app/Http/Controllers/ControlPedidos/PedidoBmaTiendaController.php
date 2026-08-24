<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\CorregirTareaPreparacionRequest;
use App\Http\Requests\ControlPedidos\RegenerarCaratulaPedidoRequest;
use App\Http\Requests\ControlPedidos\ReportarIncidenciaPreparacionRequest;
use App\Http\Requests\ControlPedidos\ResponderPreparacionTiendaRequest;
use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Services\ControlPedidos\CalcularRequisitosPreparacionService;
use App\Services\ControlPedidos\ConfirmarCaratulaColocadaService;
use App\Services\ControlPedidos\ConfirmarSalidaTrasladoTiendaService;
use App\Services\ControlPedidos\CorregirTareaPreparacionService;
use App\Services\ControlPedidos\CrearTraspasoDesdeTareaPreparacionService;
use App\Services\ControlPedidos\GenerarCaratulaPedidoService;
use App\Services\ControlPedidos\LiberarTareaPreparacionService;
use App\Services\ControlPedidos\ListarTareasTiendaService;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\RegenerarCaratulaPedidoService;
use App\Services\ControlPedidos\ReportarIncidenciaPreparacionService;
use App\Services\ControlPedidos\ResponderPreparacionTiendaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\ControlPedidos\SesionEvidenciaTareaPreparacionService;
use App\Services\ControlPedidos\TomarTareaPreparacionService;
use App\Models\ControlPedidos\PedidoBmaRevisionProducto;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\VisibilidadTareaPreparacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PedidoBmaTiendaController extends Controller
{
    public function index(
        Request $request,
        ListarTareasTiendaService $listarService,
        PreparacionTiendaConfig $config,
    ): Response {
        Gate::authorize('control_pedidos.tienda.ver');

        return Inertia::render('ControlPedidos/Tienda/Index', [
            'tareas' => fn () => $this->paginaTareas($listarService->ejecutar(Auth::user(), $request->all())),
            'metricas' => fn () => $listarService->metricas(Auth::user()),
            'filtros' => $request->only(['tab', 'q', 'modalidad', 'almacen_id', 'estado', 'page', 'tarea']),
            'config' => $config->todas(),
            'estados_fisicos' => PedidoBmaRevisionProducto::LABELS,
            'almacenes' => Almacen::query()
                ->where('activo', true)
                ->where('visible_en_pedidos', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function listado(Request $request, ListarTareasTiendaService $listarService): JsonResponse
    {
        Gate::authorize('control_pedidos.tienda.ver');

        return response()->json([
            'tareas' => $this->paginaTareas($listarService->ejecutar(Auth::user(), $request->all())),
            'metricas' => $listarService->metricas(Auth::user()),
            'filtros' => $request->only(['tab', 'q', 'modalidad', 'almacen_id', 'estado', 'page']),
        ]);
    }

    public function show(PedidoBmaTareaPreparacion $tarea, PreparacionTiendaConfig $config, CalcularRequisitosPreparacionService $requisitos): Response
    {
        Gate::authorize('control_pedidos.tienda.ver');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);

        $tarea->load([
            'modalidad',
            'almacen',
            'productos',
            'documentos',
            'historial.usuario',
            'pedido.cliente',
            'asignadaA',
            'solicitudTraspaso.estado',
            'enviadaCedisPor',
            'recibidaCedisPor',
        ]);

        return Inertia::render('ControlPedidos/Tienda/Show', [
            'tarea' => VisibilidadTareaPreparacion::payloadTienda($tarea, Auth::user()),
            'requisitos' => $requisitos->efectivos($tarea),
            'config' => $config->todas(),
            'estados_fisicos' => PedidoBmaRevisionProducto::LABELS,
            'historial' => $tarea->historial->map(fn ($h) => [
                'id' => $h->id,
                'estado_anterior' => $h->estado_anterior,
                'estado_nuevo' => $h->estado_nuevo,
                'accion' => $h->accion,
                'comentario' => $h->comentario,
                'usuario' => $h->usuario?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ]),
            'documentos' => $tarea->documentos->map(fn ($d) => [
                'id' => $d->id,
                'tipo_evidencia' => $d->tipo_evidencia,
                'nombre_original' => $d->nombre_original,
                'inmutable' => $d->inmutable,
            ]),
            'traspaso' => $tarea->solicitudTraspaso ? [
                'id' => $tarea->solicitudTraspaso->id,
                'folio' => $tarea->solicitudTraspaso->folio,
                'folio_traspaso' => $tarea->solicitudTraspaso->folio_traspaso,
                'estado' => $tarea->solicitudTraspaso->estado?->nombre,
                'url' => '/traspasos?q='.urlencode((string) $tarea->solicitudTraspaso->folio),
            ] : null,
        ]);
    }

    public function tomar(PedidoBmaTareaPreparacion $tarea, Request $request, TomarTareaPreparacionService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.tomar');

        try {
            $service->ejecutar($tarea, Auth::user(), $request->integer('version') ?: null);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Tarea tomada correctamente.');
    }

    public function responder(PedidoBmaTareaPreparacion $tarea, ResponderPreparacionTiendaRequest $request, ResponderPreparacionTiendaService $service): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $datos['productos'],
                $request->file('evidencias', []),
                $datos['observaciones_respuesta'] ?? null,
                $datos['version'] ?? null,
                [
                    'peso_real_kg' => $datos['peso_real_kg'] ?? null,
                    'peso_volumetrico_kg' => $datos['peso_volumetrico_kg'] ?? null,
                    'catalogo_tipo_caja_id' => $datos['catalogo_tipo_caja_id'] ?? null,
                    'observaciones_fisicas' => $datos['observaciones_fisicas'] ?? null,
                ],
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        $tab = $tarea->fresh()->requiere_traslado_cedis ? 'LISTAS_TRASLADO' : 'RESPONDIDAS_HOY';

        return redirect()->route('control_pedidos.tienda.index', ['tab' => $tab])
            ->with('success', 'Preparación respondida correctamente.');
    }

    public function confirmarSalida(
        PedidoBmaTareaPreparacion $tarea,
        Request $request,
        ConfirmarSalidaTrasladoTiendaService $service,
    ): RedirectResponse {
        Gate::authorize('control_pedidos.tienda.trasladar');

        try {
            $service->ejecutar($tarea, Auth::user(), $request->integer('version') ?: null);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.index', ['tab' => 'EN_TRASLADO'])
            ->with('success', 'Salida a CEDIS confirmada.');
    }

    public function regenerarTraspaso(
        PedidoBmaTareaPreparacion $tarea,
        CrearTraspasoDesdeTareaPreparacionService $service,
    ): RedirectResponse {
        Gate::authorize('control_pedidos.tienda.trasladar');

        try {
            $service->ejecutar($tarea, Auth::user());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Traspaso vinculado correctamente.');
    }

    public function reportarIncidencia(PedidoBmaTareaPreparacion $tarea, ReportarIncidenciaPreparacionRequest $request, ReportarIncidenciaPreparacionService $service): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $datos['tipo_incidencia'],
                $datos['motivo'],
                (int) $datos['almacen_solicitado_id'],
                isset($datos['almacen_aparente_id']) ? (int) $datos['almacen_aparente_id'] : null,
                $datos['productos_afectados'] ?? [],
                $datos['observacion'] ?? null,
                $request->file('evidencias', []),
                $datos['version'] ?? null,
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('control_pedidos.tienda.index', ['tab' => 'CON_INCIDENCIA'])
            ->with('success', 'Incidencia reportada. Ventas fue notificada.');
    }

    public function corregir(PedidoBmaTareaPreparacion $tarea, CorregirTareaPreparacionRequest $request, CorregirTareaPreparacionService $service): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                (int) $datos['almacen_id'],
                $datos['productos'],
                $datos['observaciones'] ?? null,
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->back()->with('success', 'Solicitud corregida y reenviada a Tienda.');
    }

    public function liberar(PedidoBmaTareaPreparacion $tarea, Request $request, LiberarTareaPreparacionService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.liberar');

        try {
            $service->ejecutar($tarea, Auth::user(), $request->input('motivo'), $request->integer('version') ?: null);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Operación de liberación registrada.');
    }

    public function subirEvidencia(PedidoBmaTareaPreparacion $tarea, Request $request): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.evidencias');

        $request->validate([
            'evidencia' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        if (! in_array($tarea->estado, [
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
        ], true)) {
            return redirect()->back()->with('error', 'No puede adjuntar evidencia en el estado actual.');
        }

        $archivo = $request->file('evidencia');
        $ruta = $archivo->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'public');
        $tarea->documentos()->create([
            'tipo_evidencia' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_GENERAL,
            'ruta_interna' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
            'subido_por_id' => Auth::id(),
            'subido_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Evidencia adjuntada.');
    }

    public function eliminarEvidencia(PedidoBmaTareaPreparacion $tarea, PedidoBmaTareaDocumento $tareaDocumento): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.evidencias');

        if ((int) $tareaDocumento->pedido_bma_tarea_preparacion_id !== (int) $tarea->id || $tareaDocumento->inmutable) {
            abort(404);
        }

        Storage::disk('public')->delete($tareaDocumento->ruta_interna);
        $tareaDocumento->delete();

        return redirect()->back()->with('success', 'Evidencia eliminada.');
    }

    public function crearSesionEvidencia(PedidoBmaTareaPreparacion $tarea, SesionEvidenciaTareaPreparacionService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.tienda.evidencias');

        $payload = $service->generar($tarea, Auth::id());

        return response()->json([
            'url' => $payload['url'],
            'qr_data_uri' => $payload['qr_data_uri'],
            'expira_en' => $payload['expira_en'],
        ]);
    }

    public function mostrarSesionEvidencia(PedidoBmaTareaPreparacion $tarea, SesionEvidenciaTareaPreparacionService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.tienda.evidencias');
        $sesion = $service->vigente($tarea);

        return response()->json([
            'sesion' => $sesion ? [
                'estado' => $sesion->estado,
                'expira_en' => $sesion->expira_en?->toIso8601String(),
                'url' => $sesion->urlPublica(),
                'fotos_count' => $sesion->fotos()->count(),
            ] : null,
        ]);
    }

    public function cancelarSesionEvidencia(PedidoBmaTareaPreparacion $tarea, SesionEvidenciaTareaPreparacionService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.tienda.evidencias');
        $service->cancelar($tarea);

        return response()->json(['ok' => true]);
    }

    public function promoverSesionEvidencia(PedidoBmaTareaPreparacion $tarea, SesionEvidenciaTareaPreparacionService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.tienda.evidencias');
        $service->promoverATarea($tarea, Auth::id());

        return response()->json(['ok' => true]);
    }

    public function generarCaratula(PedidoBmaTareaPreparacion $tarea, Request $request, GenerarCaratulaPedidoService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.generar_caratula');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);

        try {
            $service->ejecutar($tarea, Auth::user(), $request->integer('version') ?: null);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Carátula generada.');
    }

    public function regenerarCaratula(
        PedidoBmaTareaPreparacion $tarea,
        RegenerarCaratulaPedidoRequest $request,
        RegenerarCaratulaPedidoService $service,
    ): RedirectResponse {
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);
        $datos = $request->validated();

        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $datos['motivo_regeneracion'],
                isset($datos['version']) ? (int) $datos['version'] : null,
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->back()->with('success', 'Carátula regenerada.');
    }

    public function confirmarCaratula(
        PedidoBmaTareaPreparacion $tarea,
        Request $request,
        ConfirmarCaratulaColocadaService $service,
    ): RedirectResponse {
        Gate::authorize('control_pedidos.tienda.confirmar_caratula');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);

        try {
            $service->ejecutar($tarea, Auth::user(), $request->integer('version') ?: null);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.index', ['tab' => 'RESPONDIDAS_HOY'])
            ->with('success', 'Carátula confirmada. Ventas fue notificada.');
    }

    public function descargarCaratula(
        PedidoBmaTareaPreparacion $tarea,
        PedidoBmaCaratula $caratula,
        RegistrarHistorialPedidoService $historial,
    ): HttpResponse {
        Gate::authorize('control_pedidos.tienda.imprimir_caratula');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);
        abort_unless((int) $caratula->pedido_bma_tarea_preparacion_id === (int) $tarea->id, 404);
        abort_unless($caratula->ruta_pdf && Storage::disk('local')->exists($caratula->ruta_pdf), 404);

        $tarea->loadMissing('pedido.estatus');
        $pedido = $tarea->pedido;
        if ($pedido?->estatus) {
            $historial->ejecutar(
                $pedido->id,
                Auth::id(),
                $pedido->estatus->id,
                $pedido->estatus->id,
                "Descarga carátula v{$caratula->version}.",
                AccionesHistorialPedidoBma::DESCARGA_CARATULA
            );
        }

        return response(Storage::disk('local')->get($caratula->ruta_pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="caratula-v'.$caratula->version.'.pdf"',
        ]);
    }

    public function subirDocumentoMunicipal(PedidoBmaTareaPreparacion $tarea, Request $request): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.cargar_identificacion');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);

        $datos = $request->validate([
            'tipo' => ['required', 'in:identificacion,remision'],
            'archivo' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        if (! in_array($tarea->estado, [
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
        ], true)) {
            return redirect()->back()->with('error', 'No puede adjuntar documentos en el estado actual.');
        }

        $archivo = $request->file('archivo');
        $ruta = $archivo->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'local');
        $tarea->documentos()->create([
            'tipo_evidencia' => $datos['tipo'] === 'remision'
                ? PedidoBmaTareaDocumento::TIPO_REMISION
                : PedidoBmaTareaDocumento::TIPO_IDENTIFICACION,
            'ruta_interna' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
            'subido_por_id' => Auth::id(),
            'subido_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Documento adjuntado.');
    }

    public function descargarDocumentoTarea(
        PedidoBmaTareaPreparacion $tarea,
        PedidoBmaTareaDocumento $tareaDocumento,
        RegistrarHistorialPedidoService $historial,
    ): HttpResponse {
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);
        abort_unless((int) $tareaDocumento->pedido_bma_tarea_preparacion_id === (int) $tarea->id, 404);

        if ($tareaDocumento->tipo_evidencia === PedidoBmaTareaDocumento::TIPO_IDENTIFICACION) {
            Gate::authorize('control_pedidos.tienda.ver_identificacion');
            $tarea->loadMissing('pedido.estatus');
            $pedido = $tarea->pedido;
            if ($pedido?->estatus) {
                $historial->ejecutar(
                    $pedido->id,
                    Auth::id(),
                    $pedido->estatus->id,
                    $pedido->estatus->id,
                    'Descarga de identificación municipal.',
                    AccionesHistorialPedidoBma::DESCARGA_IDENTIFICACION
                );
            }
        } else {
            Gate::authorize('control_pedidos.tienda.ver');
        }

        $disk = Storage::disk('local')->exists($tareaDocumento->ruta_interna) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($tareaDocumento->ruta_interna), 404);

        return response(Storage::disk($disk)->get($tareaDocumento->ruta_interna), 200, [
            'Content-Type' => $tareaDocumento->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($tareaDocumento->nombre_original).'"',
        ]);
    }

    private function paginaTareas($paginator): array
    {
        $paginator->getCollection()->transform(
            fn (PedidoBmaTareaPreparacion $t) => VisibilidadTareaPreparacion::payloadTienda($t, Auth::user())
        );

        return $paginator->toArray();
    }
}
