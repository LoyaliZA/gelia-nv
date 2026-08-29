<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\FiltrarReportePagosPedidosRequest;
use App\Models\Almacen;
use App\Models\CatalogoBanco;
use App\Models\Departamento;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\ReportePagosPedidosExportacion;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\EstimarExportacionReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\ExportarReportePagosPedidosCsvService;
use App\Services\Reportes\PagosPedidos\ListarReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\ObtenerDetalleReportePagoPedidoService;
use App\Services\Reportes\PagosPedidos\SolicitarExportacionReportePagosPedidosService;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportePagosPedidosController extends Controller
{
    public function index(
        FiltrarReportePagosPedidosRequest $request,
        ListarReportePagosPedidosService $listar,
        CalcularMetricasReportePagosPedidosService $metricas,
    ): Response {
        Gate::authorize('reportes.pagos_pedidos.ver');

        $filtros = $request->filtrosNormalizados();
        $resultado = $listar->ejecutar(Auth::user(), $filtros);

        return Inertia::render('Reportes/PagosPedidos/Index', [
            'grupos' => $resultado['grupos'],
            'paginacion' => $resultado['paginacion'],
            'metricas' => $metricas->ejecutar(Auth::user(), $filtros),
            'filtros' => $filtros,
            'formas_pago' => PedidoBmaPago::formasPagoCatalogo(),
            'estados_exhibicion' => PedidoBmaPago::ESTADOS_REVISION,
            'bancos' => CatalogoBanco::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'departamentos' => Departamento::query()->orderBy('nombre')->get(['id', 'nombre']),
            'vendedores' => User::query()
                ->whereIn('id', PedidoBmaCierrePago::query()->distinct()->whereNotNull('vendedor_id')->pluck('vendedor_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'almacenes' => Almacen::query()->orderBy('nombre')->get(['id', 'nombre']),
            'origenes_pedido' => PedidoBmaCierrePago::query()
                ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(metadata_snapshot, '$.origen')) as origen")
                ->whereNotNull('metadata_snapshot->origen')
                ->pluck('origen')
                ->filter()
                ->sort()
                ->values(),
            'avisos' => [
                'requiere_backfill' => PedidoBma::query()->whereNotNull('pago_validado_at')->exists()
                    && PedidoBmaCierrePago::query()->count() === 0,
            ],
            'mis_exportaciones' => $this->listarExportacionesUsuario(Auth::user()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function listarExportacionesUsuario(User $usuario): array
    {
        if (! Gate::allows('reportes.pagos_pedidos.exportar_csv')
            && ! Gate::allows('reportes.pagos_pedidos.exportar_pdf')) {
            return [];
        }

        return ReportePagosPedidosExportacion::query()
            ->where('user_id', $usuario->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn (ReportePagosPedidosExportacion $e) => $e->paraApi())
            ->all();
    }

    public function listarExportaciones(): JsonResponse
    {
        if (! Gate::allows('reportes.pagos_pedidos.exportar_csv')
            && ! Gate::allows('reportes.pagos_pedidos.exportar_pdf')) {
            abort(403);
        }

        return response()->json([
            'exportaciones' => $this->listarExportacionesUsuario(Auth::user()),
        ]);
    }

    public function detalle(
        PedidoBmaCierrePago $cierre,
        ObtenerDetalleReportePagoPedidoService $detalle,
    ): JsonResponse {
        Gate::authorize('reportes.pagos_pedidos.ver');

        return response()->json(
            $detalle->ejecutar(Auth::user(), $cierre, true)
        );
    }

    public function evidenciaPago(PedidoBmaPago $pago): StreamedResponse
    {
        Gate::authorize('reportes.pagos_pedidos.ver_evidencias');

        $pago->load('pedido');
        if (! $pago->pedido || ! VisibilidadPedidoBma::puedeConsultar(Auth::user(), $pago->pedido)) {
            abort(403);
        }

        if (! $pago->ruta_archivo || ! Storage::disk('public')->exists($pago->ruta_archivo)) {
            abort(404);
        }

        Log::info('reporte_pagos_pedidos.evidencia_descarga', [
            'usuario_id' => Auth::id(),
            'pago_id' => $pago->id,
            'pedido_id' => $pago->pedido_bma_id,
        ]);

        $mime = $pago->mime_type ?: 'application/octet-stream';
        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
        $disposition = ($inline ? 'inline' : 'attachment').'; filename="'.($pago->nombre_original ?: 'evidencia').'"';

        return Storage::disk('public')->response($pago->ruta_archivo, $pago->nombre_original, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
        ]);
    }

    public function documento(PedidoBmaDocumento $documento): StreamedResponse
    {
        Gate::authorize('reportes.pagos_pedidos.ver_evidencias');

        $documento->load('pedido');
        if (! $documento->pedido || ! VisibilidadPedidoBma::puedeConsultar(Auth::user(), $documento->pedido)) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($documento->ruta_archivo)) {
            abort(404);
        }

        Log::info('reporte_pagos_pedidos.documento_descarga', [
            'usuario_id' => Auth::id(),
            'documento_id' => $documento->id,
            'pedido_id' => $documento->pedido_bma_id,
        ]);

        $mime = $documento->mime_type ?: 'application/octet-stream';

        return Storage::disk('public')->response(
            $documento->ruta_archivo,
            $documento->nombre_original,
            ['Content-Type' => $mime, 'Content-Disposition' => 'inline']
        );
    }

    public function estimarExportacion(
        FiltrarReportePagosPedidosRequest $request,
        EstimarExportacionReportePagosPedidosService $estimar,
    ): JsonResponse {
        Gate::authorize('reportes.pagos_pedidos.ver');

        if (! Gate::allows('reportes.pagos_pedidos.exportar_csv')
            && ! Gate::allows('reportes.pagos_pedidos.exportar_pdf')) {
            abort(403);
        }

        $filtros = $request->filtrosNormalizados();
        $formato = $request->input('formato', 'pdf');
        if (! in_array($formato, ['pdf', 'csv_resumen', 'csv_detalle'], true)) {
            $formato = 'pdf';
        }
        $filtros['formato'] = $formato;

        return response()->json($estimar->ejecutar(Auth::user(), $filtros));
    }

    public function csvResumen(
        FiltrarReportePagosPedidosRequest $request,
        ExportarReportePagosPedidosCsvService $exportar,
    ): StreamedResponse {
        Gate::authorize('reportes.pagos_pedidos.exportar_csv');

        Log::info('reporte_pagos_pedidos.export_csv_resumen', [
            'usuario_id' => Auth::id(),
            'filtros' => $request->filtrosNormalizados(),
        ]);

        return $exportar->resumen(Auth::user(), $request->filtrosNormalizados());
    }

    public function csvDetalle(
        FiltrarReportePagosPedidosRequest $request,
        ExportarReportePagosPedidosCsvService $exportar,
    ): StreamedResponse {
        Gate::authorize('reportes.pagos_pedidos.exportar_csv');

        Log::info('reporte_pagos_pedidos.export_csv_detalle', [
            'usuario_id' => Auth::id(),
            'filtros' => $request->filtrosNormalizados(),
        ]);

        return $exportar->detalle(Auth::user(), $request->filtrosNormalizados());
    }

    public function solicitarExportacion(
        FiltrarReportePagosPedidosRequest $request,
        SolicitarExportacionReportePagosPedidosService $solicitar,
    ): JsonResponse {
        $filtros = $request->filtrosNormalizados();
        $formato = $request->input('formato', 'pdf');
        if (! in_array($formato, ['pdf', 'csv_resumen', 'csv_detalle'], true)) {
            $formato = 'pdf';
        }
        $filtros['formato'] = $formato;

        if ($formato === 'pdf') {
            Gate::authorize('reportes.pagos_pedidos.exportar_pdf');
        } else {
            Gate::authorize('reportes.pagos_pedidos.exportar_csv');
        }

        $resultado = $solicitar->ejecutar(Auth::user(), $filtros);

        return response()->json([
            'job_id' => $resultado['job_id'],
            'exportacion' => $resultado['exportacion'],
            'message' => 'Generación en cola.',
        ]);
    }

    public function reintentarExportacion(
        string $exportacion,
        SolicitarExportacionReportePagosPedidosService $solicitar,
    ): JsonResponse {
        $anterior = ReportePagosPedidosExportacion::query()
            ->where('user_id', Auth::id())
            ->findOrFail($exportacion);

        $formato = $anterior->formato;
        if ($formato === 'pdf') {
            Gate::authorize('reportes.pagos_pedidos.exportar_pdf');
        } else {
            Gate::authorize('reportes.pagos_pedidos.exportar_csv');
        }

        $filtros = array_merge($anterior->filtros ?? [], ['formato' => $formato]);
        $resultado = $solicitar->ejecutar(Auth::user(), $filtros);

        return response()->json([
            'job_id' => $resultado['job_id'],
            'exportacion' => $resultado['exportacion'],
            'message' => 'Reintento en cola.',
        ]);
    }

    public function solicitarPdf(FiltrarReportePagosPedidosRequest $request): JsonResponse
    {
        $request->merge(['formato' => 'pdf']);

        return $this->solicitarExportacion($request, app(SolicitarExportacionReportePagosPedidosService::class));
    }

    public function estadoPdf(string $exportacion): JsonResponse
    {
        $this->autorizarVerExportacion($exportacion);

        $estado = ReportePagosPedidosProgreso::leer($exportacion);
        if ($estado) {
            return response()->json($estado);
        }

        return response()->json([
            'progress' => 0,
            'status' => 'pending',
            'estado' => 'pending',
            'etapa' => null,
            'etapa_label' => null,
            'registros_procesados' => 0,
            'registros_total' => 0,
            'started_at' => null,
            'cancelable' => false,
            'file_path' => null,
            'error' => null,
        ]);
    }

    public function cancelarPdf(string $exportacion): JsonResponse
    {
        $this->autorizarVerExportacion($exportacion);

        if (! ReportePagosPedidosProgreso::solicitarCancelacion($exportacion)) {
            return response()->json([
                'message' => 'No se puede cancelar en esta etapa o el reporte ya terminó.',
            ], 409);
        }

        return response()->json(['message' => 'Cancelación solicitada.']);
    }

    public function descargarPdf(string $exportacion)
    {
        $modelo = ReportePagosPedidosExportacion::query()
            ->where('user_id', Auth::id())
            ->findOrFail($exportacion);

        if ($modelo->formato === 'pdf') {
            Gate::authorize('reportes.pagos_pedidos.exportar_pdf');
        } else {
            Gate::authorize('reportes.pagos_pedidos.exportar_csv');
        }

        if ($modelo->estado !== ReportePagosPedidosExportacion::ESTADO_COMPLETED || $modelo->estaExpirado()) {
            abort(404, 'Reporte no disponible.');
        }

        if (! $modelo->ruta_archivo || ! Storage::disk('local')->exists($modelo->ruta_archivo)) {
            abort(404);
        }

        Log::info('reporte_pagos_pedidos.export_descarga', [
            'usuario_id' => Auth::id(),
            'job_id' => $exportacion,
            'formato' => $modelo->formato,
        ]);

        return Storage::disk('local')->download(
            $modelo->ruta_archivo,
            $modelo->nombre_archivo ?: ('pagos_pedidos_'.now()->format('Ymd_His'))
        );
    }

    private function autorizarVerExportacion(string $exportacion): void
    {
        $modelo = ReportePagosPedidosExportacion::query()
            ->where('user_id', Auth::id())
            ->findOrFail($exportacion);

        if ($modelo->formato === 'pdf') {
            Gate::authorize('reportes.pagos_pedidos.exportar_pdf');
        } else {
            Gate::authorize('reportes.pagos_pedidos.exportar_csv');
        }
    }
}
