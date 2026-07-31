<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\StorePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\UpdatePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\AnexarPagoEnvioPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\CompletarEnvioResguardoPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SolicitarRepesajePedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SubirPdfPedidoBmaRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\ActualizarPedidoBmaService;
use App\Services\ControlPedidos\AnexarPagoEnvioPedidoBmaService;
use App\Services\ControlPedidos\CrearPedidoBmaService;
use App\Services\ControlPedidos\Direcciones\CambiarDireccionPedido;
use App\Services\ControlPedidos\EliminarPedidoBmaService;
use App\Services\ControlPedidos\EnviarPedidoBmaService;
use App\Services\ControlPedidos\GestionarPdfPedidoBmaService;
use App\Services\ControlPedidos\LiberarResguardoPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosBmaService;
use App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService;
use App\Services\ControlPedidos\SolicitarPesajePedidoBmaService;
use App\Services\ControlPedidos\SolicitarRepesajePedidoBmaService;
use Illuminate\Http\JsonResponse;
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
        ListarPedidosBmaService $listarService
    ) {
        $datos = $request->validated();
        $pedidoId = $datos['pedido_id'] ?? null;
        unset($datos['comprobantes'], $datos['enviar'], $datos['pedido_id']);

        try {
            if ($pedidoId) {
                $pedido = PedidoBma::findOrFail($pedidoId);
                $listarService->asegurarAcceso($pedido, Auth::user());
                if (!$pedido->esEditablePorVendedora()) {
                    return response()->json(['message' => 'Este pedido ya no admite autoguardado.'], 422);
                }
                $pedido = $actualizarService->ejecutar($pedido, $datos, Auth::id());
            } else {
                $pedido = $crearService->ejecutar($datos, Auth::id());
            }
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'saved_at' => now()->toIso8601String(),
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
            ->whereNull('pedido_principal_id')
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
                'envia_otra_persona', 'anexar_remision',
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

    public function subirPdfPedido(
        SubirPdfPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ListarPedidosBmaService $listarService,
        GestionarPdfPedidoBmaService $service
    ): RedirectResponse {
        $listarService->asegurarAcceso($pedidoBma, Auth::user());

        try {
            $service->subir($pedidoBma->load('estatus'), $request->file('pdf_pedido'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'PDF del pedido adjuntado.');
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
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Consulta de pesaje enviada a CEDIS.');
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
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Re-pesaje solicitado a CEDIS.');
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
