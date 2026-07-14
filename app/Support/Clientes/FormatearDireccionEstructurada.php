<?php

namespace App\Support\Clientes;

use App\Models\ClienteDireccion;

class FormatearDireccionEstructurada
{
    /**
     * @param  array<string, mixed>|ClienteDireccion  $direccion
     */
    public static function ejecutar(array|ClienteDireccion $direccion): ?string
    {
        $d = $direccion instanceof ClienteDireccion
            ? $direccion->toArray()
            : $direccion;

        $calle = trim((string) ($d['calle'] ?? ''));
        $ext = trim((string) ($d['numero_exterior'] ?? ''));
        $int = trim((string) ($d['numero_interior'] ?? ''));

        $linea1Partes = array_filter([
            $calle !== '' ? $calle : null,
            $ext !== '' ? 'Ext. '.$ext : null,
            $int !== '' ? 'Int. '.$int : null,
        ]);
        $linea1 = trim(implode(' ', $linea1Partes));

        $cp = trim((string) ($d['codigo_postal'] ?? ''));

        $partes = array_filter([
            $linea1 !== '' ? $linea1 : null,
            trim((string) ($d['colonia'] ?? '')) ?: null,
            $cp !== '' ? 'C.P. '.$cp : null,
            trim((string) ($d['municipio'] ?? '')) ?: null,
            trim((string) ($d['ciudad'] ?? '')) ?: null,
            trim((string) ($d['estado'] ?? '')) ?: null,
            trim((string) ($d['pais'] ?? '')) ?: null,
            trim((string) ($d['referencias'] ?? '')) ?: null,
        ]);

        if ($partes === []) {
            return null;
        }

        return implode(', ', $partes);
    }

    /**
     * @param  array<string, mixed>|ClienteDireccion  $direccion
     */
    public static function resumida(array|ClienteDireccion $direccion): string
    {
        $completo = self::ejecutar($direccion);

        if ($completo === null) {
            return 'Sin domicilio capturado';
        }

        return mb_strlen($completo) > 120
            ? mb_substr($completo, 0, 117).'...'
            : $completo;
    }
}
