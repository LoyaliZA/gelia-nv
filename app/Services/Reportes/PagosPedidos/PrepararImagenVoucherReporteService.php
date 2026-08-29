<?php

namespace App\Services\Reportes\PagosPedidos;

use Illuminate\Support\Facades\Storage;

class PrepararImagenVoucherReporteService
{
    /** MIME admitidos para incrustar en PDF. */
    private const MIME_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];

    public function esImagen(?string $mime): bool
    {
        return $mime && in_array(strtolower($mime), self::MIME_IMAGEN, true);
    }

    /**
     * Copia temporal optimizada para DomPDF (máx 1200px ancho).
     */
    public function rutaTemporalParaPdf(?string $rutaPublica, ?string $mime): ?string
    {
        if (! $rutaPublica || ! $this->esImagen($mime)) {
            return null;
        }

        if (! Storage::disk('public')->exists($rutaPublica)) {
            return null;
        }

        $abs = Storage::disk('public')->path($rutaPublica);
        if (! is_readable($abs)) {
            return null;
        }

        $info = @getimagesize($abs);
        if ($info === false) {
            return null;
        }

        [$w, $h] = $info;
        $maxW = 1200;
        if ($w <= $maxW) {
            return $abs;
        }

        $ratio = $maxW / $w;
        $newW = $maxW;
        $newH = (int) round($h * $ratio);

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($abs),
            IMAGETYPE_PNG => @imagecreatefrompng($abs),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : false,
            default => false,
        };

        if ($src === false) {
            return $abs;
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $tmp = storage_path('app/reportes_pagos_pedidos/tmp_'.md5($rutaPublica).'.jpg');
        @mkdir(dirname($tmp), 0755, true);
        imagejpeg($dst, $tmp, 82);
        imagedestroy($src);
        imagedestroy($dst);

        return $tmp;
    }
}
