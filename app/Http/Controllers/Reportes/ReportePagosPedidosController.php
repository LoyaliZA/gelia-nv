<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\FiltrarReportePagosPedidosRequest;
use App\Http\Requests\Reportes\ReportarErrorAdminReportePagosRequest;
use App\Models\Almacen;
use App\Models\CatalogoBanco;
use App\Models\Departamento;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\Reportes\PedidoBmaCierrePago;
use App\Models\Reportes\ReportePagosPedidosExportacion;
use App\Models\SaldosAFavor\PedidoBmaPago;
use App\Models\User;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\CalcularMetricasReporteVouchersValidadosService;
use App\Services\Reportes\PagosPedidos\EstimarExportacionReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\ExportarReportePagosPedidosCsvService;
use App\Services\Reportes\PagosPedidos\ListarReportePagosPedidosService;
use App\Services\Reportes\PagosPedidos\ListarReporteVouchersValidadosService;
use App\Services\Reportes\PagosPedidos\ObtenerDetalleReportePagoPedidoService;
use App\Services\Reportes\PagosPedidos\ConfirmarExhibicionAdminReportePagosService;
use App\Services\Reportes\PagosPedidos\ConfirmarPedidoAdminReportePagosService;
use App\Services\Reportes\PagosPedidos\ReportarErrorAdminReportePagosService;
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
        ListarReporteVouchersValidadosService $listarVouchers,
        CalcularMetricasReportePagosPedidosService $metricas,
        CalcularMetricasReporteVouchersValidadosService $metricasVouchers,
    ): Response {
        Gate::authorize('reportes.pagos_pedidos.ver');

        $filtros = $request->filtrosNormalizados();
        $tipoReporte = $filtros['tipo_reporte'] ?? 'pedido';

        $propsComunes = [
            'tipo_reporte' => $tipoReporte,
            'vouchers_disponible' => false,
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
        ];

        if ($tipoReporte === 'vouchers') {
            $resultadoVouchers = $listarVouchers->ejecutar(Auth::user(), $filtros);

            return Inertia::render('Reportes/PagosPedidos/Index', array_merge($propsComunes, [
                'grupos' => [],
                'metricas' => [],
                'grupos_vouchers' => $resultadoVouchers['grupos'],
                'metricas_vouchers' => $metricasVouchers->ejecutar(Auth::user(), $filtros),
                'paginacion' => $resultadoVouchers['paginacion'],
                'agrupar_por_vouchers' => $resultadoVouchers['agrupar_por'],
                'vouchers_disponible' => true,
                'capturadores' => $this->usuariosCapturadoresVouchers(),
                'validadores_vouchers' => $this->usuariosValidadoresVouchers(),
            ]));
        }

        $resultado = $listar->ejecutar(Auth::user(), $filtros);

        return Inertia::render('Reportes/PagosPedidos/Index', array_merge($propsComunes, [
            'grupos' => $resultado['grupos'],
            'paginacion' => $resultado['paginacion'],
            'metricas' => $metricas->ejecutar(Auth::user(), $filtros),
        ]));
    }

    /** @return list<array{id: int, name: string}> */
    private function usuariosCapturadoresVouchers(): array
    {
        return User::query()
            ->whereIn('id', \App\Models\Reportes\PedidoBmaCierrePagoItem::query()
                ->distinct()
                ->whereNotNull('capturado_por_id')
                ->pluck('capturado_por_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function usuariosValidadoresVouchers(): array
    {
        $revisores = \App\Models\Reportes\PedidoBmaCierrePagoItem::query()
            ->distinct()
            ->whereNotNull('revisado_por_id')
            ->pluck('revisado_por_id');
        $cierreValidadores = PedidoBmaCierrePago::query()
            ->distinct()
            ->whereNotNull('validado_por_id')
            ->pluck('validado_por_id');

        return User::query()
            ->whereIn('id', $revisores->merge($cierreValidadores)->unique())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function listarExportacionesUsuario(User $usuario): array
    {
        if (! Gate::allows('reportes.pagos_pedidos.exportar_csv')
            && ! Gate::allows('reportes.pagos_pedidos.exportar_pdf')) {
            return [];
        }

        return ReportePagosPedidosExportacion::query()
            ->with('user')
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

    public function confirmarPedidoAdmin(
        PedidoBmaCierrePago $cierre,
        ConfirmarPedidoAdminReportePagosService $confirmar,
    ): JsonResponse {
        Gate::authorize('reportes.pagos_pedidos.confirmar_admin');

        return response()->json($confirmar->ejecutar(Auth::user(), $cierre->id));
    }

    public function confirmarExhibicionAdmin(
        PedidoBmaCierrePago $cierre,
        int $item,
        ConfirmarExhibicionAdminReportePagosService $confirmar,
    ): JsonResponse {
        Gate::authorize('reportes.pagos_pedidos.confirmar_admin');

        return response()->json($confirmar->ejecutar(Auth::user(), $cierre->id, $item));
    }

    public function reportarErrorPedidoAdmin(
        PedidoBmaCierrePago $cierre,
        ReportarErrorAdminReportePagosRequest $request,
        ReportarErrorAdminReportePagosService $reportar,
    ): JsonResponse {
        return response()->json($reportar->ejecutar(
            Auth::user(),
            $cierre->id,
            ReportarErrorAdminReportePagosService::ALCANCE_PEDIDO,
            (string) $request->validated('comentario'),
            $request->file('evidencia'),
        ));
    }

    public function reportarErrorExhibicionAdmin(
        PedidoBmaCierrePago $cierre,
        int $item,
        ReportarErrorAdminReportePagosRequest $request,
        ReportarErrorAdminReportePagosService $reportar,
    ): JsonResponse {
        return response()->json($reportar->ejecutar(
            Auth::user(),
            $cierre->id,
            ReportarErrorAdminReportePagosService::ALCANCE_EXHIBICION,
            (string) $request->validated('comentario'),
            $request->file('evidencia'),
            $item,
        ));
    }

    public function evidenciaPago(PedidoBmaPago $pago): StreamedResponse
    {
        Gate::authorize('reportes.pagos_pedidos.ver_evidencias');

        $pago->load('pedido');

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

        $filtros = $this->validarExportacion($request->filtrosNormalizados());
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

        $filtros = $this->filtrosSoloReportePedido($request->filtrosNormalizados());

        Log::info('reporte_pagos_pedidos.export_csv_resumen', [
            'usuario_id' => Auth::id(),
            'filtros' => $filtros,
        ]);

        return $exportar->resumen(Auth::user(), $filtros);
    }

    public function csvDetalle(
        FiltrarReportePagosPedidosRequest $request,
        ExportarReportePagosPedidosCsvService $exportar,
    ): StreamedResponse {
        Gate::authorize('reportes.pagos_pedidos.exportar_csv');

        $filtros = $this->filtrosSoloReportePedido($request->filtrosNormalizados());

        Log::info('reporte_pagos_pedidos.export_csv_detalle', [
            'usuario_id' => Auth::id(),
            'filtros' => $filtros,
        ]);

        return $exportar->detalle(Auth::user(), $filtros);
    }

    public function solicitarExportacion(
        FiltrarReportePagosPedidosRequest $request,
        SolicitarExportacionReportePagosPedidosService $solicitar,
    ): JsonResponse {
        $filtros = $this->validarExportacion($request->filtrosNormalizados());
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

    /** @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function validarExportacion(array $filtros): array
    {
        $tipo = $filtros['tipo_reporte'] ?? 'pedido';
        if (! in_array($tipo, ['pedido', 'vouchers'], true)) {
            abort(422, 'Tipo de reporte no válido para exportación.');
        }

        return $filtros;
    }

    /** @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosSoloReportePedido(array $filtros): array
    {
        $filtros = $this->validarExportacion($filtros);
        if (($filtros['tipo_reporte'] ?? 'pedido') !== 'pedido') {
            abort(422, 'La exportación solo está disponible para Pagos por pedido.');
        }

        return $filtros;
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
