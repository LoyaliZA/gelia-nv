<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\AsignarGuiaPedidoBmaRequest;
use App\Http\Requests\ControlPedidos\ImportarGuiasPedidoRequest;
use App\Http\Requests\ControlPedidos\ReportarErrorDatosPedidoBmaRequest;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\AsignarGuiaPedidoBmaService;
use App\Services\ControlPedidos\ActualizarGuiaPedidoBmaService;
use App\Services\ControlPedidos\ExportarPlantillaGuiasPedidoService;
use App\Http\Requests\ControlPedidos\SubirGuiaPdfPedidoBmaRequest;
use App\Services\ControlPedidos\GestionarGuiaPdfPedidoBmaService;
use App\Services\ControlPedidos\ImportarGuiasPedidoService;
use App\Services\ControlPedidos\ListarPedidosDelegadoService;
use App\Services\ControlPedidos\ReportarErrorDatosPedidoBmaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PedidoBmaDelegadoController extends Controller
{
    public function index(Request $request, ListarPedidosDelegadoService $listarService): Response
    {
        Gate::authorize('control_pedidos.delegado');

        return Inertia::render('ControlPedidos/Delegado/Index', [
            'pedidos' => $listarService->ejecutar($request->all()),
            'metricas' => $listarService->metricas(),
            'filtros' => $request->all(),
        ]);
    }

    public function exportar(ExportarPlantillaGuiasPedidoService $service): StreamedResponse
    {
        Gate::authorize('control_pedidos.delegado');

        return $service->ejecutar();
    }

    public function asignarGuia(
        AsignarGuiaPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        AsignarGuiaPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar(
                $pedidoBma->load('estatus'),
                $request->validated('numero_rastreo'),
                Auth::id()
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Guía asignada correctamente.');
    }

    public function actualizarGuia(
        AsignarGuiaPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        ActualizarGuiaPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->ejecutar(
                $pedidoBma->load('estatus'),
                $request->validated('numero_rastreo'),
                Auth::id()
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Guía actualizada correctamente.');
    }

    public function subirGuiaPdf(
        SubirGuiaPdfPedidoBmaRequest $request,
        PedidoBma $pedidoBma,
        GestionarGuiaPdfPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->subir($pedidoBma->load('estatus'), $request->file('guia_pdf'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'PDF de guía subido correctamente.');
    }

    public function eliminarGuiaPdf(
        PedidoBma $pedidoBma,
        GestionarGuiaPdfPedidoBmaService $service
    ): RedirectResponse {
        try {
            $service->eliminar($pedidoBma->load('estatus'));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'PDF de guía eliminado.');
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

        return redirect()->back()->with('success', 'Error de datos reportado. CEDIS, auxiliar y vendedora fueron notificados.');
    }

    public function importar(ImportarGuiasPedidoRequest $request, ImportarGuiasPedidoService $service): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $ruta = $archivo->storeAs('temp', 'import_guias_' . uniqid() . '.' . $archivo->getClientOriginalExtension());

        try {
            $resultado = $service->ejecutar(Storage::path($ruta), Auth::id());
        } catch (\RuntimeException $e) {
            Storage::delete($ruta);

            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            Storage::delete($ruta);
        }

        $mensaje = "{$resultado['actualizados']} pedido(s) actualizado(s).";
        if ($resultado['omitidos'] > 0) {
            $mensaje .= " {$resultado['omitidos']} fila(s) omitida(s).";
        }

        return redirect()->back()->with([
            'success' => $mensaje,
            'import_resultado' => $resultado,
        ]);
    }
}
