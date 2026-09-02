<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdvBulto;
use Illuminate\Support\Str;

final class GeneradorCodigoEtiquetaResguardoPdv
{
    /** Alfabeto sin caracteres ambiguos (0/O, 1/l/I). */
    private const ALFABETO = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    public const LONGITUD = 12;

    public static function generar(): string
    {
        do {
            $codigo = self::aleatorio();
        } while (ResguardoPdvBulto::query()->where('codigo_etiqueta', $codigo)->exists());

        return $codigo;
    }

    private static function aleatorio(): string
    {
        $alfabeto = self::ALFABETO;
        $longitud = strlen($alfabeto);
        $codigo = '';

        for ($i = 0; $i < self::LONGITUD; $i++) {
            $codigo .= $alfabeto[random_int(0, $longitud - 1)];
        }

        return $codigo;
    }

    public static function normalizarEntrada(?string $valor): string
    {
        $codigo = trim((string) $valor);

        if ($codigo === '') {
            return '';
        }

        if (preg_match('~(?:etiquetas/resolver/|codigo[=:])([A-Za-z0-9]+)~i', $codigo, $coincidencias) === 1) {
            return $coincidencias[1];
        }

        if (Str::length($codigo) > self::LONGITUD) {
            return Str::substr($codigo, 0, self::LONGITUD);
        }

        return $codigo;
    }
}
