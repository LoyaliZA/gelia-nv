<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\AutorizarSalidaPreparacionRequest;
use App\Http\Requests\ControlPedidos\CambiarModalidadPreparacionRequest;
use App\Http\Requests\ControlPedidos\ConfirmarEntregaRecogeHoyRequest;
use App\Http\Requests\ControlPedidos\ConfirmarPagoSalidaRequest;
use App\Http\Requests\ControlPedidos\ConfirmarDevolucionAnaquelRequest;
use App\Http\Requests\ControlPedidos\CorregirTareaPreparacionRequest;
use App\Http\Requests\ControlPedidos\ProrrogarCumplimientoVipRequest;
use App\Http\Requests\ControlPedidos\RegenerarCaratulaPedidoRequest;
use App\Http\Requests\ControlPedidos\ReportarIncidenciaPreparacionRequest;
use App\Http\Requests\ControlPedidos\SepararCumplimientoFisicoRequest;
use App\Http\Requests\ControlPedidos\SolicitarDevolucionCumplimientoRequest;
use App\Http\Requests\ControlPedidos\ResponderPreparacionTiendaRequest;
use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoTipoCajaPedido;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaDocumento;
use App\Services\ControlPedidos\AsegurarCumplimientoFisicoService;
use App\Services\ControlPedidos\AutorizarSalidaPreparacionService;
use App\Services\ControlPedidos\ConfirmarEntregaRecogeHoyService;
use App\Services\ControlPedidos\ConfirmarPagoSalidaPreparacionService;
use App\Services\ControlPedidos\CalcularRequisitosPreparacionService;
use App\Services\ControlPedidos\CambiarModalidadPreparacionService;
use App\Services\ControlPedidos\ConfirmarCaratulaColocadaService;
use App\Services\ControlPedidos\ConfirmarDespachoMunicipioService;
use App\Services\ControlPedidos\ConfirmarDevolucionAnaquelService;
use App\Services\ControlPedidos\ConfirmarEmpaqueMunicipioService;
use App\Services\ControlPedidos\ConfirmarSalidaTrasladoTiendaService;
use App\Services\ControlPedidos\CorregirTareaPreparacionService;
use App\Services\ControlPedidos\CrearTraspasoDesdeTareaPreparacionService;
use App\Services\ControlPedidos\GenerarCaratulaPedidoService;
use App\Services\ControlPedidos\LiberarTareaPreparacionService;
use App\Services\ControlPedidos\ColaEstadosCuentaPreparacionService;
use App\Services\ControlPedidos\ConciliarGrupoComplementoEnvioBodegaService;
use App\Services\ControlPedidos\ListarTareasTiendaService;
use App\Models\ControlPedidos\PedidoBmaTraspasoDiscrepancia;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use App\Services\ControlPedidos\ProrrogarCumplimientoVipService;
use App\Services\ControlPedidos\RegenerarCaratulaPedidoService;
use App\Services\ControlPedidos\ReportarIncidenciaPreparacionService;
use App\Services\ControlPedidos\ResponderPreparacionTiendaService;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Services\ControlPedidos\SepararCumplimientoFisicoService;
use App\Services\ControlPedidos\SesionEvidenciaTareaPreparacionService;
use App\Services\ControlPedidos\SolicitarDevolucionCumplimientoService;
use App\Services\ControlPedidos\TomarTareaPreparacionService;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PedidoBmaTiendaController extends Controller
{
    public function index(
        Request $request,
        ListarTareasTiendaService $listarService,
        PreparacionTiendaConfig $config,
        ColaEstadosCuentaPreparacionService $colaEstadosCuenta,
    ): Response {
        Gate::authorize('control_pedidos.tienda.ver');

        return Inertia::render('ControlPedidos/Tienda/Index', [
            'tareas' => fn () => $this->paginaTareas($listarService->ejecutar(Auth::user(), $request->all())),
            'metricas' => fn () => $listarService->metricas(Auth::user()),
            'cola_estados_cuenta' => fn () => $colaEstadosCuenta->resumen(Auth::user(), $config),
            'filtros' => $request->only(['tab', 'q', 'modalidad', 'almacen_id', 'estado', 'origen_solicitud', 'prioridad_md', 'page', 'tarea']),
            'origenes_solicitud' => \App\Models\ControlPedidos\PedidoBma::ORIGENES_SOLICITUD,
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
            'filtros' => $request->only(['tab', 'q', 'modalidad', 'almacen_id', 'estado', 'origen_solicitud', 'prioridad_md', 'page']),
        ]);
    }

    public function show(
        PedidoBmaTareaPreparacion $tarea,
        PreparacionTiendaConfig $config,
        CalcularRequisitosPreparacionService $requisitos,
        AsegurarCumplimientoFisicoService $asegurar,
        ConciliarGrupoComplementoEnvioBodegaService $conciliarGrupo,
    ): Response {
        Gate::authorize('control_pedidos.tienda.ver');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);

        $asegurar->ejecutar($tarea);

        $tarea->load([
            'modalidad',
            'almacen',
            'productos',
            'documentos',
            'historial.usuario',
            'pedido.cliente',
            'pedido.referencias',
            'asignadaA',
            'solicitudTraspaso.estado',
            'enviadaCedisPor',
            'recibidaCedisPor',
            'cumplimientoFisico.eventos.usuario',
        ]);

        return Inertia::render('ControlPedidos/Tienda/Show', [
            'tarea' => VisibilidadTareaPreparacion::payloadTienda($tarea, Auth::user()),
            'requisitos' => array_merge($requisitos->efectivos($tarea), [
                'causa_remision_salida' => $requisitos->causaRemisionSalida($tarea),
            ]),
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
                'pedido_bma_tarea_producto_id' => $d->pedido_bma_tarea_producto_id,
            ]),
            'almacenes' => Almacen::query()
                ->where('activo', true)
                ->where('visible_en_pedidos', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'tipos_caja' => CatalogoTipoCajaPedido::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'traspaso' => $tarea->solicitudTraspaso ? [
                'id' => $tarea->solicitudTraspaso->id,
                'folio' => $tarea->solicitudTraspaso->folio,
                'folio_traspaso' => $tarea->solicitudTraspaso->folio_traspaso,
                'estado' => $tarea->solicitudTraspaso->estado?->nombre,
                'url' => '/traspasos?q='.urlencode((string) $tarea->solicitudTraspaso->folio),
            ] : null,
            'eventos_apartado' => $tarea->cumplimientoFisico
                ? $tarea->cumplimientoFisico->eventos->map(fn ($evento) => [
                    'id' => $evento->id,
                    'tipo' => $evento->tipo,
                    'tipo_label' => PedidoBmaCumplimientoEvento::LABELS[$evento->tipo] ?? $evento->tipo,
                    'motivo' => $evento->motivo,
                    'usuario' => $evento->usuario?->name,
                    'created_at' => $evento->created_at?->toIso8601String(),
                ])->values()
                : [],
            'conciliacion_grupo' => $tarea->pedido
                ? $conciliarGrupo->paraPedido($tarea->pedido)
                : null,
            'discrepancias_traspaso' => $tarea->solicitud_traspaso_id
                ? PedidoBmaTraspasoDiscrepancia::query()
                    ->where('solicitud_traspaso_id', $tarea->solicitud_traspaso_id)
                    ->orderBy('sku')
                    ->get()
                    ->map(fn (PedidoBmaTraspasoDiscrepancia $d) => [
                        'sku' => $d->sku,
                        'piezas_tienda' => $d->piezas_tienda,
                        'piezas_cedis' => $d->piezas_cedis,
                        'pedido_bma_origen_id' => $d->pedido_bma_origen_id,
                    ])
                    ->values()
                : [],
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
                $request->file('evidencias', []) ?: [],
                $datos['observaciones_respuesta'] ?? null,
                $datos['version'] ?? null,
                [
                    'peso_real_kg' => $datos['peso_real_kg'] ?? null,
                    'peso_volumetrico_kg' => $datos['peso_volumetrico_kg'] ?? null,
                    'catalogo_tipo_caja_id' => $datos['catalogo_tipo_caja_id'] ?? null,
                    'observaciones_fisicas' => $datos['observaciones_fisicas'] ?? null,
                ],
                $request->file('evidencias_producto', []) ?: [],
            );
        } catch (ValidationException $e) {
            $mensaje = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return redirect()->back()->with('error', $mensaje)->withInput();
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        $estado = $tarea->fresh()->estado;
        $tab = match ($estado) {
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_TRASLADO => 'LISTAS_TRASLADO',
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA => 'LISTAS_CARATULA',
            default => 'RESPONDIDAS_HOY',
        };

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
        } catch (ValidationException $e) {
            $mensaje = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return redirect()->back()->with('error', $mensaje);
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

        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:2000'],
            'version' => ['nullable', 'integer', 'min:1'],
            'cantidad_liberada' => ['nullable', 'integer', 'min:0'],
            'incidencia' => ['nullable', 'string', 'max:2000'],
            'confirmacion' => ['required', 'accepted'],
        ]);

        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $datos['motivo'] ?? null,
                isset($datos['version']) ? (int) $datos['version'] : null,
                array_merge($datos, ['area' => 'TIENDA'])
            );
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Mercancía liberada. Confirmó devolución a disponibilidad.');
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
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $request->integer('version') ?: null,
                $request->integer('caratula_id') ?: null,
            );
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

        $estadosDoc = [
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
            PedidoBmaTareaPreparacion::ESTADO_LISTA_PARA_CARATULA,
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
        ];
        if ($datos['tipo'] === 'remision') {
            $estadosDoc[] = PedidoBmaTareaPreparacion::ESTADO_RESPONDIDA;
        }
        if (! in_array($tarea->estado, $estadosDoc, true)) {
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

    public function separarApartado(
        PedidoBmaTareaPreparacion $tarea,
        SepararCumplimientoFisicoRequest $request,
        SepararCumplimientoFisicoService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar($tarea, Auth::user(), (int) $datos['cantidad'], $datos['ubicacion'], $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Mercancía apartada. Este registro no sincroniza inventario.');
    }

    public function solicitarDevolucionApartado(
        PedidoBmaTareaPreparacion $tarea,
        SolicitarDevolucionCumplimientoRequest $request,
        SolicitarDevolucionCumplimientoService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $datos['motivo'],
                'manual:'.$tarea->id,
                $datos['version'] ?? null
            );
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.index', ['tab' => 'DEVOLUCION_PENDIENTE'])
            ->with('success', 'La recogida quedó en devolución pendiente.');
    }

    public function confirmarDevolucionApartado(
        PedidoBmaTareaPreparacion $tarea,
        ConfirmarDevolucionAnaquelRequest $request,
        ConfirmarDevolucionAnaquelService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar($tarea, Auth::user(), $datos['ubicacion'], $request->file('foto'), $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.index', ['tab' => 'HISTORIAL_DEVUELTAS'])
            ->with('success', 'Devolución al anaquel confirmada con ubicación y foto.');
    }

    public function prorrogarApartado(
        PedidoBmaTareaPreparacion $tarea,
        ProrrogarCumplimientoVipRequest $request,
        ProrrogarCumplimientoVipService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar($tarea, Auth::user(), $datos['motivo'], $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Prórroga registrada. Solo se movió el vencimiento.');
    }

    public function confirmarPagoSalida(
        PedidoBmaTareaPreparacion $tarea,
        ConfirmarPagoSalidaRequest $request,
        ConfirmarPagoSalidaPreparacionService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar(
                $tarea,
                Auth::user(),
                $datos['condicion_cobro'],
                isset($datos['pedido_bma_pago_id']) ? (int) $datos['pedido_bma_pago_id'] : null,
                $datos['folio_operacion'] ?? null,
                $datos['version'] ?? null
            );
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Cobro registrado para la salida.');
    }

    public function autorizarSalida(
        PedidoBmaTareaPreparacion $tarea,
        AutorizarSalidaPreparacionRequest $request,
        AutorizarSalidaPreparacionService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar($tarea, Auth::user(), $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Salida autorizada sin PDF de remisión.');
    }

    public function confirmarEntregaRecogeHoy(
        PedidoBmaTareaPreparacion $tarea,
        ConfirmarEntregaRecogeHoyRequest $request,
        ConfirmarEntregaRecogeHoyService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $service->ejecutar($tarea, Auth::user(), $datos['receptor'], $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Entrega registrada con receptor, hora y persona que entregó.');
    }

    public function subirEvidenciaEmpaqueMunicipal(PedidoBmaTareaPreparacion $tarea, Request $request): RedirectResponse
    {
        Gate::authorize('control_pedidos.tienda.empacar_municipio');
        abort_unless(VisibilidadTareaPreparacion::puedeVer(Auth::user(), $tarea), 403);

        $datos = $request->validate([
            'tipo' => ['required', 'in:evidencia_bascula,evidencia_bulto,evidencia_entrega,evidencia_caratula_colocada'],
            'archivo' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $map = [
            'evidencia_bascula' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_BASCULA,
            'evidencia_bulto' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_BULTO,
            'evidencia_entrega' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_ENTREGA,
            'evidencia_caratula_colocada' => PedidoBmaTareaDocumento::TIPO_EVIDENCIA_CARATULA_COLOCADA,
        ];

        $archivo = $request->file('archivo');
        $ruta = $archivo->store("pedidos_bma/tareas_preparacion/{$tarea->id}", 'local');
        $tarea->documentos()->create([
            'tipo_evidencia' => $map[$datos['tipo']],
            'ruta_interna' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
            'subido_por_id' => Auth::id(),
            'subido_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Evidencia de empaque adjuntada.');
    }

    public function confirmarEmpaqueMunicipal(
        PedidoBmaTareaPreparacion $tarea,
        Request $request,
        ConfirmarEmpaqueMunicipioService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'bultos' => ['required', 'integer', 'min:1', 'max:99'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);
        try {
            $service->ejecutar($tarea, Auth::user(), (int) $datos['bultos'], $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Empaque municipal registrado.');
    }

    public function confirmarDespachoMunicipal(
        PedidoBmaTareaPreparacion $tarea,
        Request $request,
        ConfirmarDespachoMunicipioService $service,
    ): RedirectResponse {
        $datos = $request->validate([
            'receptor' => ['required', 'string', 'max:160'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);
        try {
            $service->ejecutar($tarea, Auth::user(), $datos['receptor'], $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $tarea)->with('success', 'Despacho al transportista registrado.');
    }

    public function cambiarModalidad(
        PedidoBmaTareaPreparacion $tarea,
        CambiarModalidadPreparacionRequest $request,
        CambiarModalidadPreparacionService $service,
    ): RedirectResponse {
        $datos = $request->validated();
        try {
            $nueva = $service->ejecutar($tarea, Auth::user(), $datos['codigo_modalidad'], $datos['motivo'], $datos['version'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first() ?: $e->getMessage());
        }

        return redirect()->route('control_pedidos.tienda.show', $nueva)
            ->with('success', 'Modalidad actualizada. El historial y los documentos de la tarea anterior se conservan.');
    }

    private function paginaTareas($paginator): array
    {
        $paginator->getCollection()->transform(
            fn (PedidoBmaTareaPreparacion $t) => VisibilidadTareaPreparacion::payloadTienda($t, Auth::user())
        );

        return $paginator->toArray();
    }
}
