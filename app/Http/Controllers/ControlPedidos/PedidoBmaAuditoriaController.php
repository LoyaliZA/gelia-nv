<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\AnexarPagoEnvioPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\LiberarResguardoPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\RechazarAnexoEnvioPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\RechazarPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ReportarErrorDatosPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\SubirRemisionPedidoBmaRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\SaldosAFavor\SafIncidencia;
use App\Services\ControlPedidos\AnexarPagoEnvioPedidoBmaService;
use App\Services\ControlPedidos\AprobarAnexoEnvioPedidoBmaService;
use App\Services\ControlPedidos\AprobarPedidoBmaService;
use App\Services\ControlPedidos\GestionarRemisionPedidoBmaService;
use App\Services\ControlPedidos\LiberarResguardoPedidoBmaService;
use App\Services\ControlPedidos\ListarPedidosAuditoriaService;
use App\Services\ControlPedidos\ObtenerCatalogosPedidoBmaService;
use App\Services\ControlPedidos\RechazarAnexoEnvioPedidoBmaService;
use App\Services\ControlPedidos\RechazarPedidoBmaService;
use App\Services\ControlPedidos\ReportarErrorDatosPedidoBmaService;
use App\Services\ControlPedidos\ValidarPagoPedidoBmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PedidoBmaAuditoriaController extends Controller
{
    public function index(
        Request $request,
        ListarPedidosAuditoriaService $listarService,
        ObtenerCatalogosPedidoBmaService $catalogosService
    ): Response {
        Gate::authorize('control_pedidos.auditar');

        return Inertia::render('ControlPedidos/Auditar/Index', [
            // Closures: en reload parcial solo se evalúan las props pedidas (only).
            'pedidos' => fn () => $listarService->ejecutar($request->all()),
            'metricas' => fn () => $listarService->metricas(),
            'filtros' => $request->only(['tab', 'q', 'page']),
            'catalogos' => fn () => $catalogosService->ejecutar(),
        ]);
    }

    public function listado(Request $request, ListarPedidosAuditoriaService $listarService): JsonResponse
    {
        Gate::authorize('control_pedidos.auditar');

        return response()->json([
            'pedidos' => $listarService->ejecutar($request->all()),
            'metricas' => $listarService->metricas(),
            'filtros' => $request->only(['tab', 'q', 'page']),
        ]);
    }

    public function validarPago(PedidoBma $pedidoBma, ValidarPagoPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.auditar');

        try {
            $resultado = $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $redirect = redirect()->back()->with('success', 'Pago validado correctamente.');
        $excedente = (float) ($resultado['resumen']['excedente'] ?? 0);
        if ($excedente > 0.01) {
            $redirect->with('saf_excedente', [
                'pedido_bma_id' => $pedidoBma->id,
                'folio' => $pedidoBma->folio,
                'excedente' => $excedente,
                'mensaje' => sprintf(
                    'Hay un excedente de $%s en este pedido. El saldo a favor se genera al registrar o enviar el pedido (no en auditoría).',
                    number_format($excedente, 2, '.', ',')
                ),
            ]);
        }

        return $redirect;
    }

    public function subirRemision(
        SubirRemisionPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        GestionarRemisionPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->subir($pedidoBma, $request->file('remision'), Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Remisión adjuntada correctamente.');
    }

    public function eliminarRemision(PedidoBma $pedidoBma, GestionarRemisionPedidoBmaService $service): RedirectResponse
    {
        Gate::authorize('control_pedidos.auditar');

        try {
            $service->eliminar($pedidoBma, Auth::id());
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

        return redirect()->back()->with('success', 'Error reportado al área correspondiente.');
    }

    public function liberarResguardo(
        LiberarResguardoPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        LiberarResguardoPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.auditar');

        try {
            $datos = $request->validated();
            $comprobante = $request->file('comprobante');
            $service->ejecutar(
                $pedidoBma,
                Auth::id(),
                $datos ?: null,
                $comprobante
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Resguardo liberado correctamente.');
    }

    public function anexarPagoEnvio(
        AnexarPagoEnvioPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        AnexarPagoEnvioPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.auditar');

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

        return redirect()->back()->with('success', 'Pago de envío anexado. Pendiente de revisión.');
    }

    public function aprobarAnexoEnvio(
        PedidoBma $pedidoBma,
        AprobarAnexoEnvioPedidoBmaService $service
    ): RedirectResponse {
        Gate::authorize('control_pedidos.auditar');

        try {
            $service->ejecutar($pedidoBma, Auth::id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Anexo de envío aprobado.');
    }

    public function rechazarAnexoEnvio(
        RechazarAnexoEnvioPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        RechazarAnexoEnvioPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar($pedidoBma, Auth::id(), $request->validated('motivo'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Anexo de envío rechazado.');
    }

    public function resolverIncidenciaSaf(
        Request $request,
        PedidoBma $pedidoBma,
        SafIncidencia $incidencia,
    ): RedirectResponse {
        Gate::authorize('control_pedidos.auditar');

        if ((int) $incidencia->pedido_bma_id !== (int) $pedidoBma->id) {
            return redirect()->back()->with('error', 'La incidencia no pertenece a este pedido.');
        }

        $datos = $request->validate([
            'nota' => ['nullable', 'string', 'max:2000'],
        ]);

        app(\App\Services\SaldosAFavor\RegistrarIncidenciaSafService::class)
            ->resolver($incidencia, Auth::id(), $datos['nota'] ?? 'Corregido en auditoría; se continúa el pedido.');

        return redirect()->back()->with('success', 'Incidencia de saldo a favor marcada como revisada.');
    }
}
