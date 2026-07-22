<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\MarcarResguardoApartadoPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ReportarErrorDatosPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ReportarIncidenciaEmpaqueRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ListarPedidosCedisService;
use App\Services\ControlPedidos\MarcarEmpacadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarEnviadoPedidoBmaService;
use App\Services\ControlPedidos\MarcarResguardoApartadoPedidoBmaService;
use App\Services\ControlPedidos\ReportarErrorDatosPedidoBmaService;
use App\Services\ControlPedidos\ReportarIncidenciaEmpaqueService;
use App\Services\ControlPedidos\RevertirEmpacadoPedidoBmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PedidoBmaCedisController extends Controller
{
    public function index(Request $request, ListarPedidosCedisService $listarService): Response
    {
        Gate::authorize('control_pedidos.cedis');

        return Inertia::render('ControlPedidos/Cedis/Index', [
            'pedidos' => $listarService->ejecutar($request->all()),
            'metricas' => $listarService->metricas(),
            'filtros' => $request->all(),
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

    public function marcarEnviado(PedidoBma $pedidoBma, MarcarEnviadoPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.cedis');

        try {
            $service->ejecutar($pedidoBma->load(['estatus', 'paqueteria', 'origen']), Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido marcado como enviado.');
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
        ReportarIncidenciaEmpaqueService $service
    ): RedirectResponse {
        try {
            $service->ejecutar($pedidoBma, Auth::id(), $request->validated('detalle'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Incidencia reportada correctamente.');
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
}
