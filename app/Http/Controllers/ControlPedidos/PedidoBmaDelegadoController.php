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
use App\Support\ControlPedidos\BusquedaDelegadoPedidoBma;
use App\Support\ControlPedidos\FiltroPaqueteriaDelegadoPedidoBma;
use App\Support\ControlPedidos\SituacionOperativaDelegadoPedidoBma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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

        $filtros = $this->filtrosDelegado($request);

        return Inertia::render('ControlPedidos/Delegado/Index', [
            'pedidos' => fn () => $listarService->ejecutar($filtros),
            'metricas' => fn () => $listarService->metricas(),
            'filtros' => $this->filtrosParaRespuesta($filtros),
            'catalogos' => fn () => [
                'paqueterias' => FiltroPaqueteriaDelegadoPedidoBma::catalogoComercialActivo(),
            ],
        ]);
    }

    public function listado(Request $request, ListarPedidosDelegadoService $listarService): JsonResponse
    {
        Gate::authorize('control_pedidos.delegado');

        $filtros = $this->filtrosDelegado($request);

        return response()->json([
            'pedidos' => $listarService->ejecutar($filtros),
            'metricas' => $listarService->metricas(),
            'filtros' => $this->filtrosParaRespuesta($filtros),
            'catalogos' => [
                'paqueterias' => FiltroPaqueteriaDelegadoPedidoBma::catalogoComercialActivo(),
            ],
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
            $service->subir($pedidoBma->load('estatus'), $request->file('guia_pdf'), Auth::id());
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
            $service->eliminar($pedidoBma->load('estatus'), Auth::id());
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

    /** @return array<string, mixed> */
    private function filtrosDelegado(Request $request): array
    {
        $filtros = [];

        $tab = $request->input('tab', 'PENDIENTES_GUIA');
        if (is_string($tab) && $tab !== '') {
            $filtros['tab'] = $tab;
        }

        $q = $request->input('q');
        if (is_string($q) && trim($q) !== '') {
            $filtros['q'] = trim($q);
        }

        if ($request->has('q_campo')) {
            $filtros['q_campo'] = BusquedaDelegadoPedidoBma::normalizar(
                is_string($request->input('q_campo')) ? $request->input('q_campo') : null
            );
        }

        $page = $request->input('page');
        if (is_numeric($page) && (int) $page > 0) {
            $filtros['page'] = (int) $page;
        }

        $ordenar = strtolower(trim((string) $request->input('ordenar', 'fecha_desc')));
        $filtros['ordenar'] = in_array($ordenar, ['fecha_desc', 'fecha_asc'], true) ? $ordenar : 'fecha_desc';

        $situacion = strtolower(trim((string) $request->input('situacion', '')));
        if ($situacion !== '' && in_array($situacion, SituacionOperativaDelegadoPedidoBma::valoresPermitidos(), true)) {
            $filtros['situacion'] = $situacion;
        }

        if ($request->has('paqueteria_ids')) {
            $raw = $request->input('paqueteria_ids');
            if ($raw !== null && $raw !== '' && $raw !== []) {
                $filtros['paqueteria_ids'] = is_array($raw)
                    ? $raw
                    : FiltroPaqueteriaDelegadoPedidoBma::normalizarIds((string) $raw);
            }
        }

        return $filtros;
    }

    /** @return array<string, mixed> */
    private function filtrosParaRespuesta(array $filtros): array
    {
        $resuelto = FiltroPaqueteriaDelegadoPedidoBma::resolver($filtros);
        $paqueteriaIds = $resuelto['aplicar'] ? $resuelto['ids'] : [];

        $qCampo = isset($filtros['q_campo'])
            ? BusquedaDelegadoPedidoBma::normalizar((string) $filtros['q_campo'])
            : BusquedaDelegadoPedidoBma::CAMPO_GENERAL;

        $respuesta = [
            'tab' => $filtros['tab'] ?? 'PENDIENTES_GUIA',
            'q' => $filtros['q'] ?? null,
            'q_campo' => $qCampo !== BusquedaDelegadoPedidoBma::CAMPO_GENERAL ? $qCampo : null,
            'page' => $filtros['page'] ?? 1,
            'ordenar' => ($filtros['ordenar'] ?? 'fecha_desc') !== 'fecha_desc'
                ? ($filtros['ordenar'] ?? 'fecha_desc')
                : null,
            'situacion' => $filtros['situacion'] ?? null,
            'paqueteria_ids' => $paqueteriaIds,
        ];

        if ($resuelto['forzar_vacio'] && array_key_exists('paqueteria_ids', $filtros)) {
            $respuesta['paqueteria_ids'] = FiltroPaqueteriaDelegadoPedidoBma::normalizarIds($filtros['paqueteria_ids']);
        }

        return array_filter($respuesta, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
