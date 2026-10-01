<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;

final class RutaAlmacenamientoResguardoPdv
{
    public static function prefijo(ResguardoPdv $resguardo, string $sufijo = ''): string
    {
        $base = $resguardo->es_demo
            ? "demo/resguardos/{$resguardo->id}"
            : "pdv/resguardos/{$resguardo->id}";

        $sufijo = trim($sufijo, '/');

        return $sufijo === '' ? $base : $base.'/'.$sufijo;
    }
}
