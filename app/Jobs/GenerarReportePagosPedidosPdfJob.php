<?php

namespace App\Jobs;

use App\Exceptions\ReportePagosPedidosCanceladoException;
use App\Models\Reportes\ReportePagosPedidosExportacion;
use App\Models\User;
use App\Notifications\ReportePagosPedidosExportacionNotification;
use App\Services\Reportes\PagosPedidos\ExportarReportePagosPedidosCsvService;
use App\Services\Reportes\PagosPedidos\ExportarReporteVouchersValidadosCsvService;
use App\Services\Reportes\PagosPedidos\GenerarReportePagosPedidosPdfService;
use App\Services\Reportes\PagosPedidos\GenerarReporteVouchersValidadosPdfService;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerarReportePagosPedidosPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct(
        public array $filtros,
        public User $usuarioSolicitante,
        public string $jobId,
    ) {}

    public function handle(
        GenerarReportePagosPedidosPdfService $pdf,
        GenerarReporteVouchersValidadosPdfService $pdfVouchers,
        ExportarReportePagosPedidosCsvService $csv,
        ExportarReporteVouchersValidadosCsvService $csvVouchers,
    ): void {
        $modelo = ReportePagosPedidosExportacion::query()->find($this->jobId);
        if ($modelo && $modelo->estado === ReportePagosPedidosExportacion::ESTADO_PENDING) {
            $modelo->update([
                'estado' => ReportePagosPedidosExportacion::ESTADO_PROCESSING,
                'started_at' => now(),
            ]);
        }

        $progreso = ReportePagosPedidosProgreso::iniciar($this->jobId);
        $formato = $this->filtros['formato'] ?? 'pdf';
        $tipo = $this->filtros['tipo_reporte'] ?? 'pedido';

        try {
            $resultado = match ([$tipo, $formato]) {
                ['vouchers', 'csv_resumen'] => $this->generarCsvVouchers($csvVouchers, $progreso),
                ['vouchers', 'pdf'] => $this->generarPdfVouchers($pdfVouchers, $progreso),
                ['vouchers', 'csv_detalle'] => throw new \InvalidArgumentException('CSV detalle no disponible para vouchers.'),
                ['pedido', 'csv_resumen'] => $this->generarCsv($csv, $progreso, 'resumen'),
                ['pedido', 'csv_detalle'] => $this->generarCsv($csv, $progreso, 'detalle'),
                default => $this->generarPdf($pdf, $progreso),
            };

            $progreso->completar($resultado['path'], [
                'nombre_archivo' => $resultado['nombre_archivo'],
                'tamano_bytes' => $resultado['tamano_bytes'],
                'num_paginas' => $resultado['num_paginas'] ?? null,
                'num_registros' => $resultado['num_registros'],
            ]);

            $modelo = ReportePagosPedidosExportacion::query()->find($this->jobId);
            if ($modelo) {
                $this->usuarioSolicitante->notify(new ReportePagosPedidosExportacionNotification($modelo->fresh(), true));
            }
        } catch (ReportePagosPedidosCanceladoException) {
            $progreso->cancelado();
        } catch (Throwable $e) {
            $progreso->fallar($e->getMessage());
            $this->notificarFallo();
            throw $e;
        }
    }

    private function generarPdf(GenerarReportePagosPedidosPdfService $pdf, ReportePagosPedidosProgreso $progreso): array
    {
        return $pdf->generar($this->usuarioSolicitante, $this->filtros, $progreso);
    }

    private function generarPdfVouchers(GenerarReporteVouchersValidadosPdfService $pdf, ReportePagosPedidosProgreso $progreso): array
    {
        return $pdf->generar($this->usuarioSolicitante, $this->filtros, $progreso);
    }

    private function generarCsvVouchers(ExportarReporteVouchersValidadosCsvService $csv, ReportePagosPedidosProgreso $progreso): array
    {
        $progreso->avanzar(ReportePagosPedidosProgreso::ETAPA_PREPARANDO, 0, 0, 1);
        $progreso->avanzar(ReportePagosPedidosProgreso::ETAPA_TOTALES, 0, 0, 1);
        $progreso->assertNoCancelado();

        $resultado = $csv->guardar($this->usuarioSolicitante, $this->filtros);

        $progreso->marcarTotalRegistros($resultado['num_registros']);
        $progreso->avanzar(ReportePagosPedidosProgreso::ETAPA_FINALIZANDO, $resultado['num_registros'], 1, 1);

        return array_merge($resultado, ['num_paginas' => null]);
    }

    private function generarCsv(
        ExportarReportePagosPedidosCsvService $csv,
        ReportePagosPedidosProgreso $progreso,
        string $tipo,
    ): array {
        $progreso->avanzar(ReportePagosPedidosProgreso::ETAPA_PREPARANDO, 0, 0, 1);
        $progreso->avanzar(ReportePagosPedidosProgreso::ETAPA_TOTALES, 0, 0, 1);
        $progreso->assertNoCancelado();

        $resultado = $tipo === 'detalle'
            ? $csv->guardarDetalle($this->usuarioSolicitante, $this->filtros)
            : $csv->guardarResumen($this->usuarioSolicitante, $this->filtros);

        $progreso->marcarTotalRegistros($resultado['num_registros']);
        $progreso->avanzar(ReportePagosPedidosProgreso::ETAPA_FINALIZANDO, $resultado['num_registros'], 1, 1);

        return array_merge($resultado, ['num_paginas' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $progreso = new ReportePagosPedidosProgreso($this->jobId);
        $progreso->fallar($exception?->getMessage() ?? 'Error desconocido al generar el reporte.');
        $this->notificarFallo();
    }

    private function notificarFallo(): void
    {
        $modelo = ReportePagosPedidosExportacion::query()->find($this->jobId);
        if ($modelo && $modelo->estado === ReportePagosPedidosExportacion::ESTADO_FAILED) {
            $this->usuarioSolicitante->notify(new ReportePagosPedidosExportacionNotification($modelo->fresh(), false));
        }
    }
}
