<?php

namespace App\Services\Activos;

use App\Models\Activo;
use App\Support\EtiquetaActivoAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GenerarEtiquetasActivosService
{
    public const MAX_ETIQUETAS = 500;

    public function __construct(
        private ResolverDimensionesEtiquetaService $dimensiones,
    ) {}

    public function ejecutar(
        Collection $activos,
        ?float $anchoMm = null,
        ?float $altoMm = null,
        array $opciones = [],
    ): DomPdfInstance {
        if ($activos->isEmpty()) {
            throw ValidationException::withMessages([
                'filtros' => 'No hay activos que coincidan con los filtros seleccionados.',
            ]);
        }

        if ($activos->count() > self::MAX_ETIQUETAS) {
            throw ValidationException::withMessages([
                'filtros' => 'Demasiadas etiquetas (' . $activos->count() . '). Acota los filtros (máximo ' . self::MAX_ETIQUETAS . ').',
            ]);
        }

        $layout = $this->dimensiones->resolver($anchoMm, $altoMm, $opciones);
        $porPagina = $layout['por_pagina'];
        $logosPdf = EtiquetaActivoAssets::logosParaPdf();

        $items = $activos->map(fn (Activo $activo) => [
            'folio' => $activo->folio,
            'nombre' => $activo->nombre,
            'tipo' => $activo->tipo?->nombre ?? 'Activo',
            'qr_base64' => $this->qrBase64($activo),
        ])->values()->all();

        $paginas = array_chunk($items, $porPagina);

        $logoAltoMm = round($layout['alto_mm'] * 0.24, 1);
        $logoDividerMm = round($layout['alto_mm'] * 0.18, 1);

        $qrRowVerticalMm = $layout['orientacion_etiqueta'] === 'vertical'
            ? round($layout['celda_alto_mm'] * 0.38, 1)
            : null;
        $bodyRowVerticalMm = $layout['orientacion_etiqueta'] === 'vertical'
            ? round($layout['celda_alto_mm'] - $qrRowVerticalMm, 1)
            : null;

        return Pdf::loadView('activos.etiquetas_hoja', [
            'paginas' => $paginas,
            'ancho_mm' => $layout['ancho_mm'],
            'alto_mm' => $layout['alto_mm'],
            'celda_ancho_mm' => $layout['celda_ancho_mm'],
            'celda_alto_mm' => $layout['celda_alto_mm'],
            'columnas' => $layout['columnas'],
            'filas' => $layout['filas'],
            'gap_mm' => $layout['gap_mm'],
            'margen_mm' => $layout['margen_mm'],
            'orientacion_hoja' => $layout['orientacion_hoja'],
            'orientacion_etiqueta' => $layout['orientacion_etiqueta'],
            'tamanio_hoja' => $layout['tamanio_hoja'],
            'page_ancho_mm' => $layout['page_ancho_mm'],
            'page_alto_mm' => $layout['page_alto_mm'],
            'logo_aromas_base64' => $logosPdf['aromas']['base64'],
            'logo_bellaroma_base64' => $logosPdf['bellaroma']['base64'],
            'logo_aromas_w' => $logosPdf['aromas']['w'],
            'logo_aromas_h' => $logosPdf['aromas']['h'],
            'logo_bellaroma_w' => $logosPdf['bellaroma']['w'],
            'logo_bellaroma_h' => $logosPdf['bellaroma']['h'],
            'logo_alto_mm' => max(4, $logoAltoMm),
            'logo_divider_mm' => max(3, $logoDividerMm),
            'qr_row_vertical_mm' => $qrRowVerticalMm,
            'body_row_vertical_mm' => $bodyRowVerticalMm,
            'total' => count($items),
        ])->setPaper($layout['dompdf_paper'], $layout['orientacion_hoja']);
    }

    private function qrBase64(Activo $activo, int $size = 240): string
    {
        $url = route('activos.consulta.publica', $activo->consulta_token, absolute: true);
        $qrCode = new QrCode(
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 2,
        );

        $result = (new PngWriter())->write($qrCode);

        return base64_encode($result->getString());
    }
}
