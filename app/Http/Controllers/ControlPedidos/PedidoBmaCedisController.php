<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\ConfirmarStockSinExistenciaPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\MarcarEnviadoPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\MarcarResguardoApartadoPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ReportarErrorDatosPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ReportarIncidenciaEmpaqueRequest;
use App\Http\Requests\ControlPedidos\ReportarSinExistenciaPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ResponderPesajePedidoBmaRequest;
use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaSesionEvidenciaFoto;
use App\Services\ControlPedidos\GestionarSinExistenciaCedisPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarResguardoApartadoPedidoBmaService;
use App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService;
use App\Services\ControlPedidos\ReportarErrorDatosPedidoBmaService;
use App\Services\ControlPedidos\ResponderPesajePedidoBmaService;
use App\Services\ControlPedidos\ReabrirEnvioPedidoBmaService;
use App\Services\ControlPedidos\RevertirEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\SesionEvidenciaCedisService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PedidoBmaCedisController extends Controller
{
    public function index(Request $request, ListarPedidosCedisService $listarService, ObtenerCatalogosPedidoBmaService $catalogosService): Response
    {
        Gate::authorize('control_pedidos.cedis');

        $catalogos = $catalogosService->ejecutar();

        return Inertia::render('ControlPedidos/Cedis/Index', [
            'pedidos' => fn () => $listarService->ejecutar($request->all()),
            'metricas' => fn () => $listarService->metricas(),
            'filtros' => $request->only(['tab', 'q', 'page']),
            'tipos_caja' => $catalogos['tipos_caja'] ?? [],
            'almacenes_busqueda' => Almacen::query()
                ->where('activo', true)
                ->where('permite_busqueda_productos', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function listado(Request $request, ListarPedidosCedisService $listarService): JsonResponse
    {
        Gate::authorize('control_pedidos.cedis');

        return response()->json([
            'pedidos' => $listarService->ejecutar($request->all()),
            'metricas' => $listarService->metricas(),
            'filtros' => $request->only(['tab', 'q', 'page']),
        ]);
    }

    public function marcarEmpacado(PedidoBma $pedidoBma, MarcarEmpacadoPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.cedis');

        try {
            $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido marcado como empacado.');
    }

    public function marcarEnviado(
        MarcarEnviadoPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        MarcarEnviadoPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cedis.enviar');

        $cajas = $request->validated('cajas');

        try {
            $pedido = $service->ejecutar(
                $pedidoBma->load(['estatus', 'paqueteria', 'origen', 'cajas']),
                Auth::id(),
                $cajas
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $completo = $pedido->estatus?->fase_ciclo === \App\Models\ControlPedidos\CatalogoEstatusPedido::FASE_ENVIADO;

        return redirect()->back()->with('success', $completo
            ? 'Paquetería confirmada: el pedido fue recogido.'
            : 'Se registraron los envíos recolectados; el pedido sigue pendiente mientras queden cajas.');
    }

    public function reabrirEnvio(PedidoBma $pedidoBma, ReabrirEnvioPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.reabrir');

        try {
            $service->ejecutar($pedidoBma->load('estatus'), Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Envío reabierto; pendiente de recolección.');
    }

    public function revertirEmpacado(PedidoBma $pedidoBma, RevertirEmpacadoPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.cedis');

        try {
            $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Empaque revertido; pedido en pendiente.');
    }

    public function reportarIncidencia(
        ReportarIncidenciaEmpaqueRequest $request,
        PedidoBma $pedidoBma,
        ReportarErrorDatosPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar(
                $pedidoBma->load(['estatus', 'documentos']),
                Auth::id(),
                ['empaque'],
                (string) $request->validated('detalle')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Error reportado correctamente.');
    }

    public function reportarErrorDatos(
        ReportarErrorDatosPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ReportarErrorDatosPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar(
                $pedidoBma->load(['estatus', 'documentos']),
                Auth::id(),
                $request->validated('campos_incorrectos'),
                (string) ($request->validated('detalle') ?? '')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Error de datos reportado. Encargado de guías, auxiliar y vendedora fueron notificados.');
    }

    public function marcarResguardoApartado(
        MarcarResguardoApartadoPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        MarcarResguardoApartadoPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar(
                $pedidoBma->load('estatus'),
                Auth::id(),
                $request->file('evidencias', []),
                (string) ($request->validated('detalle') ?? '')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Resguardo marcado como apartado. Se notificó a quien realizó el pedido.');
    }

    public function responderPesaje(
        ResponderPesajePedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ResponderPesajePedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cedis');

        try {
            $service->ejecutar(
                $pedidoBma->load(['estatus', 'origen']),
                Auth::id(),
                $request->validated('cajas') ?? [],
                [
                    'estado_fisico_general' => $request->validated('estado_fisico_general'),
                    'comentario_fisico_general' => $request->validated('comentario_fisico_general'),
                    'evidencias_generales' => $request->file('evidencias_generales', []),
                    'evidencias_envios' => $request->file('evidencias_envios', []),
                    'motivo_retiro' => $request->validated('motivo_retiro'),
                    'revisiones' => collect($request->validated('revisiones') ?? [])->map(function (array $rev, int $i) use ($request) {
                        $files = $request->file("revisiones.{$i}.evidencias") ?? [];

                        return [
                            ...$rev,
                            'evidencias' => is_array($files) ? $files : ($files ? [$files] : []),
                        ];
                    })->all(),
                ]
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $label = $pedidoBma->fresh(['origen'])->esConsultaMercancia()
            ? 'Consulta de mercancía registrada. Se notificó a la vendedora.'
            : 'Pesaje registrado. Se notificó a la vendedora.';

        return redirect()->back()->with('success', $label);
    }

    public function reportarSinExistencia(
        ReportarSinExistenciaPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        GestionarSinExistenciaCedisPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cedis');

        try {
            $service->reportar(
                $pedidoBma->load(['estatus', 'revisionesProducto']),
                Auth::id(),
                $request->validated()
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Sin existencias reportada. El pedido quedó detenido; se notificó a Ventas.');
    }

    public function confirmarStockSinExistencia(
        ConfirmarStockSinExistenciaPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        GestionarSinExistenciaCedisPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.cedis');

        try {
            $service->confirmarStock(
                $pedidoBma->load(['estatus', 'revisionesProducto']),
                Auth::id(),
                (int) $request->validated('revision_id'),
                $request->validated('nota')
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Existencias confirmadas. El estado físico se conserva.');
    }

    public function crearSesionEvidencia(PedidoBma $pedidoBma, SesionEvidenciaCedisService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.cedis');
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);

        $resultado = $service->generar($pedidoBma, Auth::id());

        return response()->json([
            'sesion_id' => $resultado['sesion']->id,
            'url' => $resultado['url'],
            'qr_data_uri' => $resultado['qr_data_uri'],
            'expira_en' => $resultado['expira_en'],
            'estado' => $resultado['sesion']->estado,
        ]);
    }

    public function mostrarSesionEvidencia(PedidoBma $pedidoBma, SesionEvidenciaCedisService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.cedis');
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);

        $sesion = $service->vigente($pedidoBma);
        if (! $sesion) {
            return response()->json(['sesion' => null]);
        }

        $sesion->load('fotos');

        return response()->json([
            'sesion_id' => $sesion->id,
            'estado' => $sesion->estado,
            'expira_en' => $sesion->expira_en?->toIso8601String(),
            'fotos' => $sesion->fotos->map(fn ($f) => $service->fotoParaPc($f))->all(),
        ]);
    }

    public function snapshotSesionEvidencia(Request $request, PedidoBma $pedidoBma, SesionEvidenciaCedisService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.cedis');
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);

        $datos = $request->validate([
            'productos' => ['nullable', 'array'],
            'productos.*.client_uuid' => ['required', 'string', 'max:64'],
            'productos.*.sku' => ['nullable', 'string', 'max:64'],
            'productos.*.descripcion' => ['nullable', 'string', 'max:255'],
            'cajas' => ['nullable', 'array'],
            'cajas.*.client_uuid' => ['required', 'string', 'max:64'],
            'cajas.*.indice' => ['nullable', 'integer', 'min:0'],
            'cajas.*.etiqueta' => ['nullable', 'string', 'max:120'],
        ]);

        $service->guardarSnapshot($pedidoBma, $datos, Auth::id());

        return response()->json(['ok' => true]);
    }

    public function cancelarSesionEvidencia(PedidoBma $pedidoBma, SesionEvidenciaCedisService $service): JsonResponse
    {
        Gate::authorize('control_pedidos.cedis');
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);

        $service->cancelar($pedidoBma, Auth::id());

        return response()->json(['ok' => true]);
    }

    public function verFotoSesionEvidencia(
        PedidoBma $pedidoBma,
        PedidoBmaSesionEvidenciaFoto $foto,
        SesionEvidenciaCedisService $service
    ) {
        Gate::authorize('control_pedidos.cedis');
        VisibilidadPedidoBma::assertPuedeConsultar(Auth::user(), $pedidoBma);

        $foto->load('sesion');
        if ((int) $foto->sesion?->pedido_bma_id !== (int) $pedidoBma->id) {
            abort(404);
        }

        $path = storage_path('app/public/'.$foto->ruta_archivo);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $foto->mime_type ?: 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
