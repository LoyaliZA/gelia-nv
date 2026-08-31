<?php

namespace App\Services\Reportes\PagosPedidos;

use App\Exceptions\ReportePagosPedidosCanceladoException;
use App\Models\User;
use App\Support\Reportes\AlcanceExhibicionesReportePagosPedidos;
use App\Support\Reportes\AnexoEvidenciasReportePagosPedidosPdf;
use App\Support\Reportes\EncabezadoReportePagosPedidosPdf;
use App\Support\Reportes\ExhibicionesPedidoReportePagosPedidosPdf;
use App\Support\Reportes\FechasPagoReporte;
use App\Support\Reportes\FichaPedidoReportePagosPedidosPdf;
use App\Support\Reportes\ParametrosAplicadosReportePagosPedidosPdf;
use App\Support\Reportes\ReportePagosPedidosProgreso;
use App\Support\Reportes\ResumenDiaReportePagosPedidosPdf;
use App\Support\Reportes\ResumenPeriodoReportePagosPedidosPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class GenerarReportePagosPedidosPdfService
{
    public function __construct(
        private ListarReportePagosPedidosService $listar,
        private CalcularMetricasReportePagosPedidosService $metricas,
        private PrepararImagenVoucherReporteService $imagenes,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{path: string, nombre_archivo: string, tamano_bytes: int, num_paginas: ?int, num_registros: int}
     */
    public function generar(User $usuario, array $params, ?ReportePagosPedidosProgreso $progreso = null): array
    {
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PREPARANDO, 0, 0, 1);

        $cierres = $this->listar->cierresFiltrados($usuario, $params);
        $this->validarLimites($cierres, $params);

        $totalPedidos = $cierres->count();
        $progreso?->marcarTotalRegistros($totalPedidos);
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PREPARANDO, $totalPedidos, 1, 1);

        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_TOTALES, 0, 0, max(1, $totalPedidos));
        $metricas = $this->metricas->ejecutar($usuario, $params);
        $progreso?->assertNoCancelado();

        $dias = $this->agruparCierres($cierres, $params, $progreso);

        $encabezado = EncabezadoReportePagosPedidosPdf::construir($usuario, $params, $totalPedidos);

        $totalVouchers = $this->contarVouchers($cierres, $params);
        $vouchersHechos = 0;
        $progreso?->marcarTotalRegistros($totalVouchers);
        if ($totalVouchers === 0) {
            $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_VOUCHERS, 0, 1, 1);
        } else {
            $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_VOUCHERS, 0, 0, $totalVouchers);
        }

        $imagenPath = function ($item) use ($progreso, &$vouchersHechos, $totalVouchers) {
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

        $anexo = AnexoEvidenciasReportePagosPedidosPdf::presentar($cierres, $params, $imagenPath);
        $progreso?->assertNoCancelado();

        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PDF, $totalPedidos + $vouchersHechos, 0, 1);

        $pdf = Pdf::loadView('reportes.pagos_pedidos_pdf', [
            'encabezado' => $encabezado,
            'parametros_aplicados' => ParametrosAplicadosReportePagosPedidosPdf::filas($params),
            'metricas' => $metricas,
            'resumen_periodo' => ResumenPeriodoReportePagosPedidosPdf::presentar($metricas),
            'dias' => $dias,
            'anexo' => $anexo,
        ])->setPaper('letter', 'portrait');

        $this->registrarPiePagina($pdf->getDomPDF(), $encabezado['folio']);
        $output = $pdf->output();
        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_PDF, $totalPedidos + $vouchersHechos, 1, 1);

        $progreso?->avanzar(ReportePagosPedidosProgreso::ETAPA_FINALIZANDO, $totalPedidos + $vouchersHechos, 0, 1);

        Storage::disk('local')->makeDirectory('reportes_pagos_pedidos');
        $nombre = 'pagos_pedidos_'.now()->format('Ymd_His').'_'.uniqid().'.pdf';
        $path = 'reportes_pagos_pedidos/'.$nombre;
        Storage::disk('local')->put($path, $output);

        $numPaginas = (int) $pdf->getDomPDF()->getCanvas()->get_page_count();

        return [
            'path' => $path,
            'nombre_archivo' => $nombre,
            'tamano_bytes' => strlen($output),
            'num_paginas' => $numPaginas,
            'num_registros' => $totalPedidos,
        ];
    }

    /** Registra folio y paginación tras el render (DomPDF 3 ejecuta page_script al registrarlo, no al final). */
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

    /**
     * @param  Collection<int, \App\Models\Reportes\PedidoBmaCierrePago>  $cierres
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function agruparCierres(Collection $cierres, array $params, ?ReportePagosPedidosProgreso $progreso): array
    {
        $agrupar = $params['agrupar_por'] ?? 'dia';
        $totalPedidos = max(1, $cierres->count());
        $pedidosHechos = 0;

        $grupos = match ($agrupar) {
            'vendedora' => $cierres->groupBy(fn ($c) => $c->vendedor?->name ?? 'Sin vendedora'),
            'banco' => $cierres->groupBy(function ($c) use ($params) {
                $items = AlcanceExhibicionesReportePagosPedidos::filtrar($c->items, $params);

                return ($items[0] ?? null)?->banco_snapshot ?? 'Sin banco';
            }),
            default => $cierres->groupBy(fn ($c) => FechasPagoReporte::claveAgrupamientoPedido($c)),
        };

        $desc = ($params['orden'] ?? 'desc') !== 'asc';
        $grupos = $desc ? $grupos->sortKeysDesc() : $grupos->sortKeys();

        $dias = [];
        foreach ($grupos as $clave => $grupo) {
            $coleccion = $desc
                ? $grupo->sortByDesc(fn ($c) => sprintf('%s|%s', $c->pedido_fecha?->toDateString() ?? '', $c->validado_at?->timestamp ?? 0))->values()
                : $grupo->sortBy(fn ($c) => sprintf('%s|%s', $c->pedido_fecha?->toDateString() ?? '', $c->validado_at?->timestamp ?? 0))->values();

            $fechaLabel = match ($agrupar) {
                'vendedora' => (string) $clave,
                'banco' => 'Banco: '.(string) $clave,
                default => FechasPagoReporte::etiquetaGrupoPedido((string) $clave),
            };

            $resumenDia = $agrupar === 'dia'
                ? ResumenDiaReportePagosPedidosPdf::presentar($coleccion, $params)
                : ['meta_linea' => $coleccion->count().' pedidos', 'incidencias' => 0, 'pagos_es_pedido_completo' => true];

            $pedidos = [];
            foreach ($coleccion as $cierre) {
                $progreso?->assertNoCancelado();
                $pedidos[] = [
                    'ficha' => FichaPedidoReportePagosPedidosPdf::presentar($cierre, $params),
                    'exhibiciones' => ExhibicionesPedidoReportePagosPedidosPdf::presentar($cierre, $params),
                ];
                $pedidosHechos++;
                $progreso?->avanzar(
                    ReportePagosPedidosProgreso::ETAPA_TOTALES,
                    $pedidosHechos,
                    $pedidosHechos,
                    $totalPedidos
                );
            }

            $dias[] = [
                'fecha' => (string) $clave,
                'fecha_label' => $fechaLabel,
                'resumen' => $resumenDia,
                'pedidos' => $pedidos,
            ];
        }

        return $dias;
    }

    /**
     * @param  Collection<int, \App\Models\Reportes\PedidoBmaCierrePago>  $cierres
     * @param  array<string, mixed>  $params
     */
    private function contarVouchers(Collection $cierres, array $params): int
    {
        if (($params['incluir_vouchers'] ?? true) === false) {
            return 0;
        }

        $total = 0;
        foreach ($cierres as $cierre) {
            foreach (AlcanceExhibicionesReportePagosPedidos::filtrar($cierre->items, $params) as $item) {
                if (AlcanceExhibicionesReportePagosPedidos::itemTieneVoucher($item)) {
                    $total++;
                }
            }
        }

        return $total;
    }

    /** @param  Collection<int, \App\Models\Reportes\PedidoBmaCierrePago>  $cierres */
    private function validarLimites(Collection $cierres, array $params): void
    {
        if ($cierres->count() > 500) {
            throw new \InvalidArgumentException('El PDF admite máximo 500 pedidos. Reduzca los filtros.');
        }

        $desde = $params['fecha_validacion_desde'] ?? null;
        $hasta = $params['fecha_validacion_hasta'] ?? null;
        if ($desde && $hasta) {
            $dias = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta));
            if ($dias > 31) {
                throw new \InvalidArgumentException('El PDF admite máximo 31 días de rango. Reduzca el periodo.');
            }
        }
    }
}
