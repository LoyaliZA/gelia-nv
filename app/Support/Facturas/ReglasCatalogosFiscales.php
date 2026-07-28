<?php

namespace App\Support\Facturas;

/**
 * Reglas SAT de acoplamiento régimen ↔ uso de CFDI.
 */
final class ReglasCatalogosFiscales
{
    public const REGIMEN_SUELDOS_SALARIOS = '605';

    public const USO_SIN_EFECTOS_FISCALES = 'S01';

    public static function usoForzadoPorRegimen(?string $regimen): ?string
    {
        return $regimen === self::REGIMEN_SUELDOS_SALARIOS
            ? self::USO_SIN_EFECTOS_FISCALES
            : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function aplicarForzados(array $datos): array
    {
        $forzado = self::usoForzadoPorRegimen(
            isset($datos['regimen_fiscal']) ? (string) $datos['regimen_fiscal'] : null
        );

        if ($forzado !== null) {
            $datos['uso_factura'] = $forzado;
        }

        return $datos;
    }
}
