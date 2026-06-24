<?php

namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\ActualizarComisionesPlataformasRequest;
use App\Http\Requests\Contabilidad\ConfirmarLoteRetiroRequest;
use App\Http\Requests\Contabilidad\ConfirmarRetiroPedidoRequest;
use App\Http\Requests\Contabilidad\StorePedidoContabilidadRequest;
use App\Http\Requests\Contabilidad\UpdatePedidoContabilidadRequest;
use App\Models\Contabilidad\Pedido;
use App\Services\Contabilidad\ActualizarComisionesPlataformasService;
use App\Services\Contabilidad\ActualizarPedidoContabilidadService;
use App\Services\Contabilidad\ConfirmarRetiroIndividualContabilidadService;
use App\Services\Contabilidad\ConfirmarRetiroLoteContabilidadService;
use App\Services\Contabilidad\CrearPedidoContabilidadService;
use App\Services\Contabilidad\EliminarPedidoContabilidadService;
use App\Services\Contabilidad\ObtenerDashboardContabilidadService;
use App\Services\Contabilidad\ObtenerIndiceContabilidadService;
use App\Services\Contabilidad\ObtenerRetirosContabilidadService;
use App\Services\Contabilidad\ProcesarListaPreciosContabilidadService;
use App\Services\Contabilidad\ExportarReporteContabilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContabilidadController extends Controller
{
    public function index(Request $request, ObtenerIndiceContabilidadService $service): Response
    {
        $this->authorize('contabilidad.ver');

        $datos = $service->ejecutar($request->only([
            'mes', 'anio', 'q', 'plataforma_id', 'estatus_pago_id', 'tipo_transaccion_id',
        ]));

        return Inertia::render('Contabilidad/Index', $datos);
    }

    public function retiros(ObtenerRetirosContabilidadService $service): Response
    {
        $this->authorize('contabilidad.ver');

        return Inertia::render('Contabilidad/Retiros', $service->ejecutar());
    }

    public function store(
        StorePedidoContabilidadRequest $request,
        CrearPedidoContabilidadService $service
    ): RedirectResponse {
        $service->ejecutar($request->validated());

        return redirect()
            ->route('contabilidad.index', [
                'mes' => (int) date('m', strtotime($request->fecha_salida)),
                'anio' => (int) date('Y', strtotime($request->fecha_salida)),
            ])
            ->with('success', 'Pedido registrado con éxito.');
    }

    public function update(
        UpdatePedidoContabilidadRequest $request,
        Pedido $pedido,
        ActualizarPedidoContabilidadService $service
    ): RedirectResponse {
        $service->ejecutar($pedido, $request->validated());

        return back()->with('success', 'Pedido actualizado correctamente.');
    }

    public function destroy(Pedido $pedido, EliminarPedidoContabilidadService $service): RedirectResponse
    {
        $this->authorize('contabilidad.pedidos.eliminar');

        $service->ejecutar($pedido);

        return back()->with('success', 'Pedido eliminado.');
    }

    public function confirmarRetiro(
        ConfirmarRetiroPedidoRequest $request,
        Pedido $pedido,
        ConfirmarRetiroIndividualContabilidadService $service
    ): RedirectResponse {
        $service->ejecutar(
            $pedido,
            (float) $request->monto_real_banco,
            $request->fecha_deposito
        );

        return back()->with('success', 'Pago confirmado y transferido a utilidad neta.');
    }

    public function confirmarLote(
        ConfirmarLoteRetiroRequest $request,
        ConfirmarRetiroLoteContabilidadService $service
    ): RedirectResponse {
        $service->ejecutar(
            (int) $request->plataforma_pago_id,
            $request->fecha_deposito,
            $request->pedidos
        );

        return redirect()
            ->route('contabilidad.retiros')
            ->with('success', 'Retiros confirmados correctamente.');
    }

    public function actualizarComisiones(
        ActualizarComisionesPlataformasRequest $request,
        ActualizarComisionesPlataformasService $service
    ): RedirectResponse {
        $service->ejecutar($request->plataformas);

        return back()->with('success', 'Comisiones de plataformas actualizadas.');
    }

    public function procesarLista(
        Request $request,
        ProcesarListaPreciosContabilidadService $service
    ): JsonResponse {
        $this->authorize('contabilidad.importar');

        $request->validate([
            'lista_resurtido' => ['required', 'file', 'mimes:xlsx,csv,xls'],
        ]);

        try {
            $diccionario = $service->ejecutar($request->file('lista_resurtido'));

            return response()->json([
                'success' => true,
                'data' => $diccionario,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function dashboardData(
        Request $request,
        ObtenerDashboardContabilidadService $service
    ): JsonResponse {
        $this->authorize('contabilidad.ver');

        return response()->json($service->ejecutar($request));
    }

    public function exportarPdf(
        Request $request,
        ExportarReporteContabilidadService $service
    ) {
        $this->authorize('contabilidad.ver');

        return $service->descargarPdf($request->all());
    }

    public function exportarCsv(
        Request $request,
        ExportarReporteContabilidadService $service
    ) {
        $this->authorize('contabilidad.ver');

        return $service->descargarCsv($request->all());
    }
}
