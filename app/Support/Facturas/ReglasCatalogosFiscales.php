<?php

namespace App\Support\Facturas;

/**
 * Reglas SAT de acoplamiento régimen ↔ uso de CFDI y validación de RFC.
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

    public static function normalizarRfc(?string $rfc): string
    {
        $limpio = preg_replace('/[^A-ZÑ&0-9]/iu', '', strtoupper((string) $rfc)) ?? '';

        return mb_substr($limpio, 0, 13);
    }

    /**
     * Persona moral: 12 chars (3 letras + fecha + homoclave).
     * Persona física: 13 chars (4 letras + fecha + homoclave).
     * El tipo se infiere del 4.º carácter (letra → física, dígito → moral).
     *
     * @return string|null Mensaje de error, o null si es válido / vacío.
     */
    public static function errorRfc(?string $rfc): ?string
    {
        $rfc = self::normalizarRfc($rfc);
        if ($rfc === '') {
            return null;
        }

        $len = mb_strlen($rfc);
        if ($len < 12 || $len > 13) {
            return 'El RFC debe tener 12 caracteres (empresa) o 13 (persona física).';
        }

        $cuarto = mb_substr($rfc, 3, 1);
        $esFisica = (bool) preg_match('/^[A-ZÑ&]$/u', $cuarto);

        if ($esFisica) {
            if ($len !== 13) {
                return 'Persona física: el RFC debe tener 13 caracteres.';
            }
            if (! preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
                return 'El RFC no tiene un formato válido para persona física.';
            }

            return null;
        }

        if ($len !== 12) {
            return 'Empresa: el RFC debe tener 12 caracteres.';
        }
        if (! preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
            return 'El RFC no tiene un formato válido para empresa.';
        }

        return null;
    }

    /**
     * Razón social factura-safe: trim, sin acentos (conserva Ñ), MAYÚSCULAS, charset limitado.
     */
    public static function normalizarRazonSocial(?string $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return '';
        }

        // Proteger Ñ antes de quitar diacríticos.
        $valor = str_replace(['Ñ', 'ñ'], ["\x00N", "\x00N"], $valor);

        $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if ($transliterado !== false) {
            $valor = $transliterado;
        }

        $valor = str_replace("\x00N", 'Ñ', $valor);
        $valor = mb_strtoupper($valor, 'UTF-8');
        $valor = preg_replace('/[^A-Z0-9Ñ&.\-\' ]+/u', '', $valor) ?? '';
        $valor = preg_replace('/\s+/u', ' ', $valor) ?? '';

        return trim($valor);
    }
}
