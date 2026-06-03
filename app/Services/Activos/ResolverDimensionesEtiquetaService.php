<?php

namespace App\Services\Activos;

use App\Support\EtiquetaActivoAssets;
use Illuminate\Validation\ValidationException;

class ResolverDimensionesEtiquetaService
{
    public const RATIO = 2.0;

    public const DEFAULT_ANCHO_MM = 100.0;

    public const MIN_ANCHO_MM = 40.0;

    public const MAX_ANCHO_MM = 200.0;

    public const MIN_ALTO_MM = 20.0;

    public const MAX_ALTO_MM = 200.0;

    public const PAGE_LANDSCAPE_ANCHO_MM = 297.0;

    public const PAGE_LANDSCAPE_ALTO_MM = 210.0;

    public const DEFAULT_TAMANIO_HOJA = 'a4';

    public const MARGEN_MM = 3.0;

    public const DEFAULT_GAP_MM = 0.0;

    public const MAX_GAP_MM = 10.0;

    public function resolver(?float $anchoMm, ?float $altoMm, array $opciones = []): array
    {
        $proporcion = $this->normalizarProporcion($opciones['proporcion'] ?? '2:1');
        $orientacionHoja = $this->normalizarOrientacionHoja($opciones['orientacion_hoja'] ?? 'landscape');
        $orientacionEtiqueta = $this->normalizarOrientacionEtiqueta($opciones['orientacion_etiqueta'] ?? 'horizontal');
        $tamanioHoja = $this->normalizarTamanioHoja($opciones['tamanio_hoja'] ?? self::DEFAULT_TAMANIO_HOJA);
        $gapMm = $this->normalizarGap($opciones['gap_mm'] ?? self::DEFAULT_GAP_MM);
        $margenMm = self::MARGEN_MM;

        if ($anchoMm === null && $altoMm === null) {
            $anchoMm = self::DEFAULT_ANCHO_MM;
        }

        if ($proporcion === '1:1') {
            if ($anchoMm === null && $altoMm !== null) {
                $anchoMm = $altoMm;
            } elseif ($anchoMm !== null && $altoMm === null) {
                $altoMm = $anchoMm;
            } elseif ($anchoMm !== null && $altoMm !== null) {
                $promedio = ($anchoMm + $altoMm) / 2;
                $anchoMm = $promedio;
                $altoMm = $promedio;
            }
        } elseif ($anchoMm !== null && $altoMm === null) {
            $altoMm = $anchoMm / self::RATIO;
        } elseif ($altoMm !== null && $anchoMm === null) {
            $anchoMm = $altoMm * self::RATIO;
        } elseif ($anchoMm !== null && $altoMm !== null) {
            $ratio = $anchoMm / max($altoMm, 0.1);
            if (abs($ratio - self::RATIO) > 0.08) {
                throw ValidationException::withMessages([
                    'ancho_mm' => 'El ancho y alto deben mantener proporción 2:1.',
                ]);
            }
        }

        $anchoMm = round($this->clampAncho($anchoMm ?? self::DEFAULT_ANCHO_MM), 1);

        if ($proporcion === '1:1') {
            $altoMm = $anchoMm;
        } else {
            $altoMm = round($anchoMm / self::RATIO, 1);
        }

        if ($altoMm < self::MIN_ALTO_MM || $altoMm > self::MAX_ALTO_MM) {
            throw ValidationException::withMessages([
                'alto_mm' => 'El alto debe estar entre ' . self::MIN_ALTO_MM . ' y ' . self::MAX_ALTO_MM . ' mm.',
            ]);
        }

        $pagina = EtiquetaActivoAssets::dimensionesPagina($tamanioHoja, $orientacionHoja);
        $pageAncho = $pagina['page_ancho_mm'];
        $pageAlto = $pagina['page_alto_mm'];

        if ($orientacionEtiqueta === 'vertical') {
            $celdaAnchoMm = $altoMm;
            $celdaAltoMm = $anchoMm;
        } else {
            $celdaAnchoMm = $anchoMm;
            $celdaAltoMm = $altoMm;
        }

        $columnas = $this->calcularCeldasPorEje($pageAncho, $margenMm, $celdaAnchoMm, $gapMm);
        $filas = $this->calcularCeldasPorEje($pageAlto, $margenMm, $celdaAltoMm, $gapMm);

        return [
            'ancho_mm' => $anchoMm,
            'alto_mm' => $altoMm,
            'celda_ancho_mm' => round($celdaAnchoMm, 1),
            'celda_alto_mm' => round($celdaAltoMm, 1),
            'columnas' => $columnas,
            'filas' => $filas,
            'por_pagina' => $columnas * $filas,
            'gap_mm' => $gapMm,
            'margen_mm' => $margenMm,
            'proporcion' => $proporcion,
            'tamanio_hoja' => $pagina['tamanio_hoja'],
            'dompdf_paper' => $pagina['dompdf_paper'],
            'orientacion_hoja' => $orientacionHoja,
            'orientacion_etiqueta' => $orientacionEtiqueta,
            'page_ancho_mm' => $pageAncho,
            'page_alto_mm' => $pageAlto,
        ];
    }

    public function calcularCeldasPorEje(float $pageMm, float $margenMm, float $celdaMm, float $gapMm): int
    {
        $util = $pageMm - ($margenMm * 2);

        if ($celdaMm <= 0) {
            return 1;
        }

        if ($gapMm <= 0) {
            return max(1, (int) floor($util / $celdaMm));
        }

        return max(1, (int) floor(($util + $gapMm) / ($celdaMm + $gapMm)));
    }

    private function clampAncho(float $anchoMm): float
    {
        return min(self::MAX_ANCHO_MM, max(self::MIN_ANCHO_MM, $anchoMm));
    }

    private function normalizarGap(?float $gap): float
    {
        if ($gap === null) {
            return self::DEFAULT_GAP_MM;
        }

        return round(max(0, min(self::MAX_GAP_MM, $gap)), 1);
    }

    private function normalizarProporcion(?string $proporcion): string
    {
        return $proporcion === '1:1' ? '1:1' : '2:1';
    }

    private function normalizarOrientacionHoja(?string $orientacion): string
    {
        return $orientacion === 'portrait' ? 'portrait' : 'landscape';
    }

    private function normalizarOrientacionEtiqueta(?string $orientacion): string
    {
        return $orientacion === 'vertical' ? 'vertical' : 'horizontal';
    }

    private function normalizarTamanioHoja(?string $tamanio): string
    {
        $clave = is_string($tamanio) ? strtolower(trim($tamanio)) : self::DEFAULT_TAMANIO_HOJA;

        return array_key_exists($clave, EtiquetaActivoAssets::TAMANOS_HOJA)
            ? $clave
            : self::DEFAULT_TAMANIO_HOJA;
    }
}
