<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\StorePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\UpdatePedidoBmaRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ActualizarPedidoBmaService;
use App\Services\ControlPedidos\CrearPedidoBmaService;
use App\Services\ControlPedidos\EliminarPedidoBmaService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PedidoBmaController extends Controller
{
    public function index(
        Request $request,
        ListarPedidosBmaService $listarService,
        ObtenerCatalogosPedidoBmaService $catalogosService
    ): Response {
        Gate::authorize('control_pedidos.ver_listado');

        return Inertia::render('ControlPedidos/Index', [
            'pedidos' => $listarService->ejecutar(Auth::user(), $request->all()),
            'metricas' => $listarService->metricas(Auth::user()),
            'filtros' => $request->all(),
            'catalogos' => $catalogosService->ejecutar(),
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

    public function update(
        UpdatePedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        ActualizarPedidoBmaService $actualizarService,
        EnviarPedidoBmaService $enviarService
    ): RedirectResponse {
        Gate::authorize('control_pedidos.editar');
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

    public function exportar(Request $request, ListarPedidosBmaService $listarService): StreamedResponse
    {
        Gate::authorize('control_pedidos.exportar');

        $pedidos = $listarService->ejecutar(Auth::user(), $request->all(), paginar: false);
        $nombreArchivo = 'control_pedidos_' . date('Y-m-d_H-i-s') . '.csv';

        return (new FastExcel($pedidos))->download($nombreArchivo, function ($pedido) {
            return [
                'Folio' => $pedido->folio,
                'Fecha' => $pedido->fecha?->format('Y-m-d') ?? '',
                'Cliente' => $pedido->cliente?->nombre ?? '',
                'No. Cliente' => $pedido->cliente?->numero_cliente ?? '',
                'Almacén' => $pedido->almacenSalida?->nombre ?? '',
                'Banco' => $pedido->banco?->nombre ?? '',
                'Total a Cobrar' => number_format((float) $pedido->total_a_cobrar, 2, '.', ''),
                'Estado' => $pedido->estatus?->nombre_visual ?? '',
                'Fase' => $pedido->estatus?->fase_ciclo ?? '',
                'Vendedora' => $pedido->vendedor?->name ?? '',
            ];
        });
    }
}
