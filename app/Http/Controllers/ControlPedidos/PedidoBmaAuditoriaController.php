<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\RechazarPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SubirRemisionPedidoBmaRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\GestionarRemisionPedidoBmaService;
use App\Services\ControlPedidos\LiberarResguardoPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosAuditoriaService;
use App\Services\ControlPedidos\RechazarPedidoBmaService;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PedidoBmaAuditoriaController extends Controller
{
    public function index(Request $request, ListarPedidosAuditoriaService $listarService): Response
    {
        Gate::authorize('control_pedidos.auditar');

        return Inertia::render('ControlPedidos/Auditar/Index', [
            'pedidos' => $listarService->ejecutar($request->all()),
            'metricas' => $listarService->metricas(),
            'filtros' => $request->all(),
        ]);
    }

    public function validarPago(PedidoBma $pedidoBma, ValidarPagoPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.auditar');

        try {
            $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pago validado correctamente.');
    }

    public function subirRemision(
        SubirRemisionPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        GestionarRemisionPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->subir($pedidoBma, $request->file('remision'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Remisión adjuntada correctamente.');
    }

    public function eliminarRemision(PedidoBma $pedidoBma, GestionarRemisionPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.auditar');

        try {
            $service->eliminar($pedidoBma);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Remisión eliminada.');
    }

    public function aprobar(PedidoBma $pedidoBma, AprobarPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.auditar');

        try {
            $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido aprobado y enviado a Registro General.');
    }

    public function rechazar(
        RechazarPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        RechazarPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar($pedidoBma, Auth::id(), $request->validated('motivo'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Pedido rechazado y devuelto a la vendedora.');
    }

    public function liberarResguardo(PedidoBma $pedidoBma, LiberarResguardoPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.auditar');

        try {
            $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Resguardo liberado correctamente.');
    }
}
