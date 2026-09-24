<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdvEvidencia;

final class UrlEvidenciaResguardoPdv
{
    public static function url(ResguardoPdvEvidencia $evidencia): ?string
    {
        if ($evidencia->tipo === ResguardoPdvEvidencia::TIPO_FIRMA) {
            return null;
        }

        if ($evidencia->resguardo_id === null) {
            return null;
        }

        return route('punto_venta.resguardos.evidencias.show', [
            'resguardo' => $evidencia->resguardo_id,
            'evidencia' => $evidencia->id,
        ]);
    }
}
