<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Models\Reportes\PedidoBmaCierrePagoItem;
use App\Models\User;
use App\Support\Reportes\AnexoVouchersValidadosPdf;
use App\Support\Reportes\EncabezadoReportePagosPedidosPdf;
use App\Support\Reportes\ParametrosAplicadosReportePagosPedidosPdf;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerarReporteVouchersValidadosPdfService
{
    public function __construct(
        private AplicarFiltrosReporteVouchersValidadosQuery $filtrosQuery,
        private CalcularMetricasReporteVouchersValidadosService $metricas,
        private ListarReporteVouchersValidadosService $listar,
        private PrepararImagenVoucherReporteService $imagenes,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_paginas: ?int, num_registros: int}
     */
    public function generar(User $usuario, array $params, ?ReportePagosPedidosProgreso $progreso = null): array
    {
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PREPARANDO, 0, 0, 1);

        $items = $this->filtrosQuery->itemsVisibles($usuario, $params);
        if (! empty($params['posible_duplicado'])) {
            $duplicados = $this->filtrosQuery->posiblesDuplicados($usuario, $params);
            $items = array_values(array_filter($items, fn (PedidoBmaCierrePagoItem $i) => isset($duplicados[$i->id])));
        }

        $total = count($items);
        $progreso?->marcarTotalRegistros($total);
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PREPARANDO, $total, 1, 1);

        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_TOTALES, 0, 0, max(1, $total));
        $metricas = $this->metricas->ejecutar($usuario, $params);
        $grupos = $this->listar->todosLosGrupos($usuario, $params);
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_TOTALES, $total, 1, 1);

        $encabezado = EncabezadoReportePagosPedidosPdf::construirVouchers($usuario, $params, $total);

        $incluirVouchers = ($params['incluir_vouchers'] ?? true) !== false;
        $totalVouchers = $incluirVouchers
            ? count(array_filter($items, fn (PedidoBmaCierrePagoItem $i) => ! empty($i->ruta_archivo_snapshot)))
            : 0;
        $vouchersHechos = 0;
        $progreso?->marcarTotalRegistros(max($totalVouchers, $total));
        if ($totalVouchers === 0) {
            $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_VOUCHERS, 0, 1, 1);
        } else {
            $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_VOUCHERS, 0, 0, $totalVouchers);
        }

        $imagenPath = function (PedidoBmaCierrePagoItem $item) use ($progreso, &$vouchersHechos, $totalVouchers) {
            $path = $this->imagenes->rutaTemporalParaPdf(
                $item->ruta_archivo_snapshot,
                $item->mime_type_snapshot
            );
            $vouchersHechos++;
            if ($totalVouchers > 0) {
                $progreso?->avanzar(
                    ReportePagosPedidosProgreso::ETAPA_VOUCHERS,
                    $vouchersHechos,
                    $vouchersHechos,
                    $totalVouchers
                );
            }

            return $path;
        };

        $anexo = $incluirVouchers
            ? AnexoVouchersValidadosPdf::presentar($items, $imagenPath)
            : ['titulo' => '', 'paginas' => [], 'remisiones' => [], 'vacio' => true];

        $progreso?->assertNoCancelado();
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PDF, $total + $vouchersHechos, 0, 1);

        $pdf = Pdf::loadView('reportes.vouchers_validados_pdf', [
            'encabezado' => $encabezado,
            'parametros_aplicados' => ParametrosAplicadosReportePagosPedidosPdf::filasVouchers($params),
            'metricas' => $metricas,
            'grupos' => $grupos,
            'anexo' => $anexo,
        ])->setPaper('letter', 'portrait');

        $this->registrarPiePagina($pdf->getDomPDF(), $encabezado['folio']);
        $output = $pdf->output();
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PDF, $total + $vouchersHechos, 1, 1);
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_FINALIZANDO, $total + $vouchersHechos, 0, 1);

        Storage::disk('local')->makeDirectory('reportes_pagos_pedidos');
        $nombre = 'vouchers_validados_'.now()->format('Ymd_His').'_'.uniqid().'.pdf';
        $path = 'reportes_pagos_pedidos/'.$nombre;
        Storage::disk('local')->put($path, $output);

        $numPaginas = (int) $pdf->getDomPDF()->getCanvas()->get_page_count();

        return [
            'path' => $path,
            'nombre_archivo' => $nombre,
            'tamano_bytes' => strlen($output),
            'num_paginas' => $numPaginas,
            'num_registros' => $total,
        ];
    }

    private function registrarPiePagina(\Dompdf\Dompdf $dompdf, string $folio): void
    {
        $dompdf->setCallbacks([
            [
                'event' => 'end_document',
                'f' => function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($folio) {
                    $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
                    $fontBold = $fontMetrics->get_font('DejaVu Sans', 'bold');
                    $w = $canvas->get_width();
                    $h = $canvas->get_height();
                    $yLine = $h - 42;
                    $yText = $h - 34;

                    $canvas->line(42, $yLine, $w - 42, $yLine, [0.82, 0.82, 0.82], 0.5);
                    $canvas->text(42, $yText, 'Folio: '.$folio, $font, 7, [0.45, 0.45, 0.45]);
                    $canvas->text(42, $yText - 10, 'Documento confidencial — Uso administrativo', $font, 6, [0.55, 0.55, 0.55]);

                    $pagina = "Página {$pageNumber} de {$pageCount}";
                    $textWidth = $fontMetrics->get_text_width($pagina, $fontBold, 7);
                    $canvas->text($w - 42 - $textWidth, $yText, $pagina, $fontBold, 7, [0.15, 0.15, 0.15]);

                    if ($pageNumber === 1) {
                        $canvas->text(378, 162, $pagina, $fontBold, 9, [0.09, 0.09, 0.09]);
                    }
                },
            ],
        ]);
    }
}
