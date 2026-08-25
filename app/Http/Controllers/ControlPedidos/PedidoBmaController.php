<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\CancelarPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\EliminarRegistroPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\StorePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\UpdatePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\AnexarPagoEnvioPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ActualizarCamposDireccionPedidoRequest;
use App\Http\Requests\ControlPedidos\RegistrarDireccionDesdePedidoRequest;
use App\Http\Requests\ControlPedidos\CargarGuiaClientePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\CompletarEnvioResguardoPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\AtenderSinExistenciaPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SolicitarRepesajePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SolicitarPreparacionTiendaRequest;
use App\Http\Requests\ControlPedidos\CorregirTareaPreparacionRequest;
use App\Http\Requests\ControlPedidos\SubirAnexoPiezasPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SubirPdfPedidoBmaRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Services\ControlPedidos\ActualizarPedidoBmaService;
use App\Services\ControlPedidos\AnexarPagoEnvioPedidoBmaService;
use App\Services\ControlPedidos\AtenderSinExistenciaPedidoBmaService;
use App\Services\ControlPedidos\CargarGuiaClientePedidoBmaService;
use App\Services\ControlPedidos\CrearPedidoBmaService;
use App\Services\ControlPedidos\Direcciones\ActualizarCamposDireccionPedidoService;
use App\Services\ControlPedidos\Direcciones\RegistrarDireccionDesdePedidoService;
use App\Services\ControlPedidos\Direcciones\CambiarDireccionPedido;
use App\Services\ControlPedidos\CancelarPedidoBmaService;
use App\Services\ControlPedidos\RouterCancelacionPedidoBmaService;
use App\Services\ControlPedidos\MarcarEsperaPagoPedidoService;
use App\Services\ControlPedidos\ReactivarCancelacionOperativaService;
use App\Services\ControlPedidos\ResolverFinancieroCancelacionService;
use App\Services\ControlPedidos\FinalizarCancelacionOperativaService;
use App\Services\ControlPedidos\EliminarPedidoBmaService;
use App\Services\ControlPedidos\EliminarRegistroPedidoBmaService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\GestionarPdfPedidoBmaService;
use App\Services\ControlPedidos\LiberarResguardoPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\RestaurarRegistroPedidoBmaService;
use App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Services\ControlPedidos\CrearTareaPreparacionService;
use App\Services\ControlPedidos\SolicitarRepesajePedidoBmaService;
use App\Services\ControlPedidos\CerrarConsultaPedidoBmaService;
use App\Services\ControlPedidos\ReabrirConsultaPedidoBmaService;
use App\Services\ControlPedidos\CalcularProgresoPedidoBmaService;
use App\Services\ControlPedidos\VolverBorradorPedidoBmaService;
use App\Support\Clientes\FormatearDireccionEstructurada;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PedidoBmaController extends Controller
{
    public function index(
        Request $request,
        ListarPedidosBmaService $listarService,
        ObtenerCatalogosPedidoBmaService $catalogosService
    ): Response {
        Gate::authorize('control_pedidos.ver_listado');

        return Inertia::render('ControlPedidos/Index', [
            'pedidos' => fn () => $listarService->ejecutar(Auth::user(), $request->all()),
            'metricas' => fn () => $listarService->metricas(Auth::user()),
            'filtros' => $request->only(['tab', 'q', 'page']),
            'catalogos' => fn () => $catalogosService->ejecutar(),
            'direcciones_normalizadas' => (bool) config('control_pedidos.direcciones_normalizadas'),
        ]);
    }

    public function listado(
        Request $request,
        ListarPedidosBmaService $listarService
    ): JsonResponse {
        Gate::authorize('control_pedidos.ver_listado');

        return response()->json([
            'pedidos' => $listarService->ejecutar(Auth::user(), $request->all()),
            'metricas' => $listarService->metricas(Auth::user()),
            'filtros' => $request->only(['tab', 'q', 'page']),
        ]);
    }

    public function store(StorePedidoBmaRequest $request, CrearPedidoBmaService $crearService, EnviarPedidoBmaService $enviarService): RedirectResponse
    {
        try {
            $pedido = $crearService->ejecutar($request->validated(), Auth::id());

            if ($request->boolean('enviar')) {
                $enviarService->ejecutar($pedido, Auth::id());
                return redirect()->back()->with('success', 'Pedido enviado al auxiliar.');
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido guardado como borrador.');
    }

    public function autoguardar(
        StorePedidoBmaRequest $request,
        CrearPedidoBmaService $crearService,
        ActualizarPedidoBmaService $actualizarService,
        ListarPedidosBmaService $listarService,
        CalcularProgresoPedidoBmaService $progresoService
    ) {
        $datos = $request->validated();
        $pedidoId = $datos['pedido_id'] ?? null;
        $updatedEsperado = $datos['updated_at_esperado'] ?? null;
        unset(
            $datos['comprobantes'],
            $datos['enviar'],
            $datos['pedido_id'],
            $datos['updated_at_esperado'],
            $datos['documentos_eliminar'],
        );

        // Solo claves presentes en el request (no nullificar campos ocultos no enviados).
        $datos = array_intersect_key($datos, $request->all());

        try {
            if ($pedidoId) {
                $pedido = PedidoBma::findOrFail($pedidoId);
                $listarService->asegurarAcceso($pedido, Auth::user());
                if (! $pedido->esEditablePorVendedora()) {
                    return response()->json(['message' => 'Este pedido ya no admite autoguardado.'], 422);
                }
                if ($updatedEsperado) {
                    $esperado = \Illuminate\Support\Carbon::parse($updatedEsperado)->timestamp;
                    $actual = optional($pedido->updated_at)->timestamp;
                    if ($actual && abs($actual - $esperado) > 1) {
                        \Illuminate\Support\Facades\Log::info('control_pedidos.autosave_conflicto', [
                            'pedido_bma_id' => $pedido->id,
                            'updated_at' => optional($pedido->updated_at)?->toIso8601String(),
                        ]);

                        return response()->json([
                            'message' => 'El pedido cambió en otro lugar. Recargue antes de seguir editando.',
                            'updated_at' => optional($pedido->updated_at)?->toIso8601String(),
                            'progreso' => $progresoService->calcular($pedido),
                        ], 409);
                    }
                }
                $pedido = $actualizarService->ejecutar($pedido, $datos, Auth::id());
            } else {
                $pedido = $crearService->ejecutar($datos, Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $pedido = $pedido->fresh(['origen', 'estatus', 'documentos', 'paqueteria', 'zona', 'tipoGuia']);

        return response()->json([
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'saved_at' => now()->toIso8601String(),
            'updated_at' => optional($pedido->updated_at)?->toIso8601String(),
            'progreso' => $progresoService->calcular($pedido),
        ]);
    }

    public function candidatosPrincipal(Request $request): JsonResponse
    {
        Gate::authorize('control_pedidos.crear');

        $clienteId = (int) $request->query('cliente_id');
        $q = trim((string) $request->query('q', ''));
        if ($clienteId < 1) {
            return response()->json(['data' => []]);
        }

        $pedidos = PedidoBma::query()
            ->with([
                'estatus:id,fase_ciclo,nombre_visual',
                'cliente:id,nombre,numero_cliente',
                'origen:id,nombre,requiere_logistica',
                'paqueteria:id,nombre',
            ])
            ->where('cliente_id', $clienteId)
            ->whereNull('pedido_principal_id');

        VisibilidadPedidoBma::aplicarAlcanceListadoBma($pedidos, Auth::user());

        $pedidos = $pedidos
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('folio', 'like', "%{$q}%")
                        ->orWhere('folio_remision', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get([
                'id', 'folio', 'folio_remision', 'cliente_id', 'total_mercancia',
                'estatus_envio', 'catalogo_estatus_pedido_id', 'es_resguardo',
                'origen_id', 'almacen_id', 'cliente_direccion_id', 'domicilio_entrega',
                'codigo_postal', 'catalogo_paqueteria_id', 'catalogo_tipo_guia_id',
                'catalogo_zona_id', 'catalogo_tipo_caja_id', 'envia_a_otra_persona',
                'envia_otra_persona', 'anexar_remision', 'vendedor_id',
            ]);

        return response()->json(['data' => $pedidos]);
    }

    public function update(
        UpdatePedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        ActualizarPedidoBmaService $actualizarService,
        EnviarPedidoBmaService $enviarService
    ): RedirectResponse {
        if (! Auth::user()->can('control_pedidos.editar') && ! Auth::user()->can('control_pedidos.crear')) {
            abort(403);
        }
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $pedido = $actualizarService->ejecutar($pedidoBma, $request->validated(), Auth::id());

            if ($request->boolean('enviar')) {
                $enviarService->ejecutar($pedido, Auth::id());
                return redirect()->back()->with('success', 'Pedido enviado al auxiliar.');
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido actualizado correctamente.');
    }

    public function enviar(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        EnviarPedidoBmaService $enviarService
    ): RedirectResponse {
        Gate::authorize('control_pedidos.crear');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $enviarService->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido enviado al auxiliar.');
    }

    public function anexarPagoEnvio(
        AnexarPagoEnvioPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        AnexarPagoEnvioPedidoBmaService $service
    ): RedirectResponse {
        $user = Auth::user();
        if (! $user->can('control_pedidos.auditar')) {
            $listarService->asegurarAcceso($pedidoBma, $user);
        }

        try {
            $service->ejecutar(
                $pedidoBma,
                $request->validated(),
                $request->file('comprobante'),
                Auth::id()
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pago de envío anexado. Pendiente de revisión del auxiliar.');
    }

    public function cambiarDireccion(
        Request $request,
        PedidoBma $pedidoBma,
        CambiarDireccionPedido $cambiarDireccion,
    ): RedirectResponse {
        $validated = $request->validate([
            'cliente_direccion_id' => ['required', 'integer', 'exists:cliente_direcciones,id'],
            'motivo' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $cambiarDireccion->ejecutar($pedidoBma, [
                'cliente_direccion_id' => (int) $validated['cliente_direccion_id'],
                'motivo' => $validated['motivo'],
                'usuario_id' => Auth::id(),
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Dirección del pedido actualizada.');
    }

    public function actualizarCamposDireccion(
        ActualizarCamposDireccionPedidoRequest $request,
        ActualizarCamposDireccionPedidoService $service,
    ): JsonResponse {
        $datos = $request->validated();
        $pedido = ! empty($datos['pedido_id'])
            ? PedidoBma::query()->findOrFail((int) $datos['pedido_id'])
            : null;

        try {
            $resultado = $service->ejecutar(
                (int) $datos['cliente_id'],
                (int) $datos['cliente_direccion_id'],
                $datos,
                Auth::id(),
                $pedido,
                $datos['motivo'] ?? null,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $dir = $resultado['direccion'];

        return response()->json([
            'ok' => true,
            'message' => 'Dirección actualizada (nueva versión) y registrada en auditoría.',
            'direccion' => [
                'id' => $dir->id,
                'numero_direccion' => $dir->numero_direccion,
                'etiqueta' => $dir->etiqueta,
                'tipo_direccion' => $dir->tipo_direccion,
                'nombre_destinatario' => $dir->nombre_destinatario,
                'telefono_destinatario' => $dir->telefono_destinatario,
                'calle' => $dir->calle,
                'numero_exterior' => $dir->numero_exterior,
                'numero_interior' => $dir->numero_interior,
                'colonia' => $dir->colonia,
                'codigo_postal' => $dir->codigo_postal,
                'municipio' => $dir->municipio,
                'ciudad' => $dir->ciudad,
                'estado' => $dir->estado,
                'pais' => $dir->pais,
                'referencias' => $dir->referencias,
                'indicaciones_entrega' => $dir->indicaciones_entrega,
                'domicilio_irregular' => (bool) $dir->domicilio_irregular,
                'anexa_remision' => (bool) $dir->anexa_remision,
                'direccion_resumida' => FormatearDireccionEstructurada::resumida($dir),
                'es_principal' => (bool) $dir->es_principal,
                'estado_verificacion' => $dir->estado_verificacion,
            ],
            'pedido_id' => $resultado['pedido']?->id,
        ]);
    }

    public function registrarDireccionCatalogo(
        RegistrarDireccionDesdePedidoRequest $request,
        RegistrarDireccionDesdePedidoService $service,
    ): JsonResponse {
        $datos = $request->validated();
        $pedido = ! empty($datos['pedido_id'])
            ? PedidoBma::query()->findOrFail((int) $datos['pedido_id'])
            : null;

        try {
            $resultado = $service->ejecutar(
                (int) $datos['cliente_id'],
                $datos,
                (bool) $datos['es_principal'],
                Auth::id(),
                $pedido,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $dir = $resultado['direccion'];

        return response()->json([
            'ok' => true,
            'message' => $dir->es_principal
                ? 'Dirección guardada como principal y vinculada al pedido.'
                : 'Dirección guardada como adicional y vinculada al pedido.',
            'direccion' => [
                'id' => $dir->id,
                'numero_direccion' => $dir->numero_direccion,
                'etiqueta' => $dir->etiqueta,
                'tipo_direccion' => $dir->tipo_direccion,
                'nombre_destinatario' => $dir->nombre_destinatario,
                'telefono_destinatario' => $dir->telefono_destinatario,
                'calle' => $dir->calle,
                'numero_exterior' => $dir->numero_exterior,
                'numero_interior' => $dir->numero_interior,
                'colonia' => $dir->colonia,
                'codigo_postal' => $dir->codigo_postal,
                'municipio' => $dir->municipio,
                'ciudad' => $dir->ciudad,
                'estado' => $dir->estado,
                'pais' => $dir->pais,
                'referencias' => $dir->referencias,
                'indicaciones_entrega' => $dir->indicaciones_entrega,
                'domicilio_irregular' => (bool) $dir->domicilio_irregular,
                'anexa_remision' => (bool) $dir->anexa_remision,
                'direccion_resumida' => FormatearDireccionEstructurada::resumida($dir),
                'es_principal' => (bool) $dir->es_principal,
                'estado_verificacion' => $dir->estado_verificacion,
            ],
            'pedido_id' => $resultado['pedido']?->id,
        ]);
    }

    public function completarEnvioResguardo(
        CompletarEnvioResguardoPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        LiberarResguardoPedidoBmaService $service
    ): RedirectResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        if ($pedidoBma->esComplemento()) {
            return redirect()->back()->with('error', 'Complete el envío desde el pedido principal.');
        }

        try {
            $datos = $request->validated();
            $service->ejecutar(
                $pedidoBma,
                Auth::id(),
                $datos ?: null,
                $request->file('comprobante')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Envío del resguardo completado. Anexo pendiente de revisión.');
    }

    public function cargarGuiaCliente(
        CargarGuiaClientePedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        CargarGuiaClientePedidoBmaService $service
    ): RedirectResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar(
                $pedidoBma->load('estatus'),
                $request->validated('numero_rastreo'),
                $request->file('guia_pdf'),
                Auth::id()
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Guía del cliente cargada. CEDIS fue notificado.');
    }

    public function subirPdfPedido(
        SubirPdfPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        GestionarPdfPedidoBmaService $service
    ): RedirectResponse|JsonResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $pedido = $service->subir($pedidoBma->load('estatus'), $request->file('pdf_pedido'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            $documento = $pedido->documentos
                ->firstWhere('tipo', PedidoBmaDocumento::TIPO_PDF_PEDIDO);

            return response()->json([
                'ok' => true,
                'mensaje' => 'PDF o foto del pedido adjuntado.',
                'documento' => $documento,
            ]);
        }

        return redirect()->back()->with('success', 'PDF o foto del pedido adjuntado.');
    }

    public function subirAnexoPiezas(
        SubirAnexoPiezasPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        GestionarPdfPedidoBmaService $service
    ): RedirectResponse|JsonResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $documento = $service->subirAnexoPiezas($pedidoBma->load('estatus'), $request->file('anexo_piezas'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'mensaje' => 'Anexo de piezas adicionales adjuntado.',
                'documento' => $documento->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'Anexo de piezas adicionales adjuntado.');
    }

    public function solicitarPesaje(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        SolicitarPesajePedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.crear');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar($pedidoBma->load(['estatus', 'origen']), Auth::id());
        } catch (Throwable $e) {
            return $this->responderErrorOperacionPedido(
                $e,
                $pedidoBma,
                'solicitar_pesaje',
                'No se pudo solicitar el pesaje. Intente de nuevo o contacte a soporte.'
            );
        }

        return redirect()->back()->with('success', 'Consulta de pesaje enviada a CEDIS.');
    }

    public function solicitarPreparacionTienda(
        SolicitarPreparacionTiendaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        CrearTareaPreparacionService $service,
    ): RedirectResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $datos = $request->validated();
            $service->ejecutar(
                $pedidoBma->load(['estatus', 'origen', 'revisionesProducto', 'cliente']),
                Auth::user(),
                $datos['codigo_modalidad'],
                (int) $datos['almacen_id'],
                $datos['observaciones'] ?? null,
                $datos['idempotencia_clave'] ?? null,
                [
                    'destinatario_es_cliente' => (bool) ($datos['destinatario_es_cliente'] ?? true),
                    'destinatario_nombre' => $datos['destinatario_nombre'] ?? null,
                    'destinatario_telefono' => $datos['destinatario_telefono'] ?? null,
                    'municipio_destino' => $datos['municipio_destino'] ?? null,
                    'direccion_referencia' => $datos['direccion_referencia'] ?? null,
                    'catalogo_paqueteria_id' => $datos['catalogo_paqueteria_id'] ?? null,
                    'modalidad_cobro' => $datos['modalidad_cobro'] ?? null,
                ],
            );
        } catch (Throwable $e) {
            return $this->responderErrorOperacionPedido(
                $e,
                $pedidoBma,
                'solicitar_preparacion_tienda',
                'No se pudo solicitar la preparación en Tienda.'
            );
        }

        return redirect()->back()->with('success', 'Solicitud de preparación enviada a Tienda.');
    }

    public function solicitarRepesaje(
        SolicitarRepesajePedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        SolicitarRepesajePedidoBmaService $service
    ): RedirectResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar(
                $pedidoBma->load('estatus'),
                Auth::id(),
                (string) $request->validated('motivo')
            );
        } catch (Throwable $e) {
            return $this->responderErrorOperacionPedido(
                $e,
                $pedidoBma,
                'solicitar_repesaje',
                'No se pudo solicitar el re-pesaje. Intente de nuevo o contacte a soporte.'
            );
        }

        return redirect()->back()->with('success', 'Re-pesaje solicitado a CEDIS.');
    }

    public function cerrarConsulta(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        CerrarConsultaPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.crear');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar($pedidoBma->load(['estatus', 'origen', 'vendedor']), Auth::id());
        } catch (Throwable $e) {
            return $this->responderErrorOperacionPedido(
                $e,
                $pedidoBma,
                'cerrar_consulta',
                'No se pudo cerrar la consulta. Intente de nuevo o contacte a soporte.'
            );
        }

        return redirect()->back()->with('success', 'Consulta CEDIS cerrada. Ya puede capturar el monto y el pago.');
    }

    public function reabrirConsulta(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        ReabrirConsultaPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.crear');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar($pedidoBma->load(['estatus', 'origen', 'vendedor']), Auth::id());
        } catch (Throwable $e) {
            return $this->responderErrorOperacionPedido(
                $e,
                $pedidoBma,
                'reabrir_consulta',
                'No se pudo reabrir la consulta. Intente de nuevo o contacte a soporte.'
            );
        }

        return redirect()->back()->with('success', 'Consulta CEDIS reabierta. El monto y el pago quedan bloqueados hasta cerrar de nuevo.');
    }

    public function atenderSinExistencia(
        AtenderSinExistenciaPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        AtenderSinExistenciaPedidoBmaService $service
    ): RedirectResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $datos = $request->validated();
            $revisionId = (int) $datos['revision_id'];
            $accion = (string) $datos['accion'];
            unset($datos['revision_id'], $datos['accion']);
            $service->ejecutar(
                $pedidoBma->load(['estatus', 'revisionesProducto', 'documentos', 'paqueteria']),
                Auth::user(),
                $revisionId,
                $accion,
                $datos
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $ok = match ($request->validated('accion')) {
            'cancelar' => 'Pedido cancelado por sin existencias.',
            'contactar' => 'Se registró el contacto con el cliente. El pedido sigue detenido.',
            'esperar' => 'El cliente espera el producto. El pedido sigue detenido.',
            'retirar' => 'Producto retirado. Totales actualizados.',
            'sustituir' => 'Producto a sustituir. Totales actualizados.',
            default => 'Decisión de sin existencias registrada.',
        };

        return redirect()->back()->with('success', $ok);
    }

    public function volverBorrador(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        VolverBorradorPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.crear');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar($pedidoBma->load('estatus'), Auth::id());
        } catch (Throwable $e) {
            return $this->responderErrorOperacionPedido(
                $e,
                $pedidoBma,
                'volver_borrador',
                'No se pudo conservar el pedido como borrador. Intente de nuevo o contacte a soporte.'
            );
        }

        return redirect()->back()->with('success', 'Pedido listo para continuar.');
    }

    /**
     * Mensaje de negocio al usuario; detalle técnico solo en logs internos.
     */
    private function responderErrorOperacionPedido(
        Throwable $e,
        PedidoBma $pedido,
        string $operacion,
        string $mensajeGenerico
    ): RedirectResponse {
        $esNegocio = $e instanceof \InvalidArgumentException
            || ($e instanceof \RuntimeException && ! $e instanceof \Illuminate\Database\QueryException);

        Log::error('Control pedidos: error en operación', [
            'operacion' => $operacion,
            'pedido_bma_id' => $pedido->id,
            'usuario_id' => Auth::id(),
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile().':'.$e->getLine(),
        ]);
        report($e);

        return redirect()->back()->with('error', $esNegocio ? $e->getMessage() : $mensajeGenerico);
    }

    public function documento(PedidoBma $pedidoBma, PedidoBmaDocumento $documento): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (! VisibilidadPedidoBma::puedeConsultar(Auth::user(), $pedidoBma)) {
            abort(403, 'No tienes autorización para consultar documentos de este pedido.');
        }

        if ((int) $documento->pedido_bma_id !== (int) $pedidoBma->id) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($documento->ruta_archivo)) {
            abort(404, 'Archivo no encontrado.');
        }

        $mime = $documento->mime_type ?: 'application/octet-stream';
        $nombre = $documento->nombre_original ?: basename($documento->ruta_archivo);

        return Storage::disk('public')->response(
            $documento->ruta_archivo,
            $nombre,
            ['Content-Type' => $mime]
        );
    }

    public function destroy(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        EliminarPedidoBmaService $eliminarService
    ): RedirectResponse {
        Gate::authorize('control_pedidos.eliminar');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $eliminarService->ejecutar($pedidoBma);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido eliminado.');
    }

    public function eliminarRegistro(
        EliminarRegistroPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        EliminarRegistroPedidoBmaService $eliminarRegistroService
    ): RedirectResponse {
        Gate::authorize('control_pedidos.eliminar_registro');

        try {
            $eliminarRegistroService->ejecutar(
                $pedidoBma,
                Auth::id(),
                $request->validated('motivo')
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Registro eliminado. Quedó en la papelera administrativa.');
    }

    public function restaurarRegistro(
        int $pedidoBma,
        RestaurarRegistroPedidoBmaService $restaurarService
    ): RedirectResponse {
        Gate::authorize('control_pedidos.eliminados');

        try {
            $restaurarService->ejecutar($pedidoBma, Auth::id());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', 'Registro restaurado.');
    }

    public function cancelar(
        CancelarPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        RouterCancelacionPedidoBmaService $router
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cancelar');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $resultado = $router->ejecutar($pedidoBma, Auth::user(), $request->validated());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        $msg = $resultado instanceof \App\Models\ControlPedidos\PedidoBmaCancelacionOperativa
            ? 'Cancelación operativa solicitada. La liberación física queda pendiente.'
            : 'Pedido cancelado. Se liberaron reservas pendientes.';

        return redirect()->back()->with('success', $msg);
    }

    public function previewCancelacion(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        RouterCancelacionPedidoBmaService $router
    ): \Illuminate\Http\JsonResponse {
        Gate::authorize('control_pedidos.cancelar');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        return response()->json([
            'preview' => $router->preview($pedidoBma, Auth::user()),
            'motivos' => CancelarPedidoBmaService::MOTIVOS,
        ]);
    }

    public function marcarEsperaPago(
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        MarcarEsperaPagoPedidoService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.espera_pago');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->ejecutar($pedidoBma, Auth::user());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido marcado en espera de pago.');
    }

    public function reactivarCancelacionOperativa(
        Request $request,
        PedidoBma $pedidoBma,
        \App\Models\ControlPedidos\PedidoBmaCancelacionOperativa $cancelacion,
        ListarPedidosBmaService $listarService,
        ReactivarCancelacionOperativaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cancelacion_operativa.reactivar');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        if ((int) $cancelacion->pedido_bma_id !== (int) $pedidoBma->id) {
            abort(404);
        }

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:2000'],
            'folio_nuevo' => ['nullable', 'string', 'max:64'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $service->ejecutar($cancelacion, Auth::user(), $datos);
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido reactivado. Se notificó a las áreas físicas.');
    }

    public function resolverFinancieroCancelacion(
        Request $request,
        PedidoBma $pedidoBma,
        \App\Models\ControlPedidos\PedidoBmaCancelacionOperativa $cancelacion,
        ListarPedidosBmaService $listarService,
        ResolverFinancieroCancelacionService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cancelacion_operativa.resolver_financiera');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        if ((int) $cancelacion->pedido_bma_id !== (int) $pedidoBma->id) {
            abort(404);
        }

        $datos = $request->validate([
            'resolucion_financiera' => ['required', 'string', 'max:64'],
        ]);

        try {
            $service->ejecutar($cancelacion, Auth::user(), $datos);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Resolución financiera aplicada y cancelación finalizada.');
    }

    public function concluirCancelacionAdmin(
        PedidoBma $pedidoBma,
        \App\Models\ControlPedidos\PedidoBmaCancelacionOperativa $cancelacion,
        ListarPedidosBmaService $listarService,
        FinalizarCancelacionOperativaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cancelacion_operativa.concluir_admin');
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        if ((int) $cancelacion->pedido_bma_id !== (int) $pedidoBma->id) {
            abort(404);
        }

        try {
            $service->intentar($cancelacion, Auth::user(), true);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Cancelación operativa concluida administrativamente.');
    }

    public function exportar(Request $request, ListarPedidosBmaService $listarService): StreamedResponse
    {
        Gate::authorize('control_pedidos.exportar');

        $pedidos = $listarService->ejecutar(Auth::user(), $request->all(), paginar: false);
        $nombreArchivo = 'control_pedidos_' . date('Y-m-d_H-i-s') . '.csv';

        return (new FastExcel($pedidos))->download($nombreArchivo, function ($pedido) {
            return [
                'Folio Remisión' => $pedido->folio_remision ?? '',
                'Folio Interno' => $pedido->folio,
                'Fecha' => $pedido->fecha?->format('Y-m-d') ?? '',
                'Cliente' => $pedido->cliente?->nombre ?? '',
                'No. Cliente' => $pedido->cliente?->numero_cliente ?? '',
                'Almacén' => $pedido->almacen?->nombre ?? '',
                'Banco' => $pedido->banco?->nombre ?? '',
                'Total a Cobrar' => number_format((float) $pedido->total_a_cobrar, 2, '.', ''),
                'Estado' => $pedido->estatus?->etiquetaSemantica((bool) $pedido->es_resguardo) ?? '',
                'Fase' => $pedido->estatus?->fase_ciclo ?? '',
                'Vendedora' => $pedido->vendedor?->name ?? '',
            ];
        });
    }
}
