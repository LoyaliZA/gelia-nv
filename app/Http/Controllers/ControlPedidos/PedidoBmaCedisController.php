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
        Gate::authorize('control_pedidos.cedis');

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
                $pedidoBma->load('estatus'),
                Auth::id(),
                $request->validated('cajas'),
                [
                    'estado_fisico_general' => $request->validated('estado_fisico_general'),
                    'comentario_fisico_general' => $request->validated('comentario_fisico_general'),
                    'evidencias_generales' => $request->file('evidencias_generales', []),
                    'evidencias_envios' => $request->file('evidencias_envios', []),
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

        return redirect()->back()->with('success', 'Pesaje registrado. Se notificó a la vendedora.');
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
}
