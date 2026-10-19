<?php

namespace App\Support\ComisionesEmpaque;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DineroComisionEmpaque
{
    public static function centavos(string $monto): int
    {
        $monto = trim($monto);
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $monto)) {
            throw ValidationException::withMessages([
                'importe' => 'Escriba un importe con hasta dos decimales, por ejemplo 10.00.',
            ]);
        }

        $negativo = str_starts_with($monto, '-');
        $monto = ltrim($monto, '-');
        [$enteros, $decimales] = array_pad(explode('.', $monto, 2), 2, '0');
        $decimales = str_pad(substr($decimales, 0, 2), 2, '0');
        $centavos = ((int) $enteros * 100) + (int) $decimales;

        return $negativo ? -$centavos : $centavos;
    }

    public static function texto(int $centavos): string
    {
        $negativo = $centavos < 0;
        $centavos = abs($centavos);

        return ($negativo ? '-' : '').intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function puntosBase(string $porcentaje): int
    {
        $porcentaje = trim($porcentaje);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $porcentaje)) {
            throw ValidationException::withMessages([
                'porcentaje' => 'Escriba el porcentaje con hasta dos decimales, sin signo negativo.',
            ]);
        }

        [$enteros, $decimales] = array_pad(explode('.', $porcentaje, 2), 2, '0');
        $decimales = str_pad(substr($decimales, 0, 2), 2, '0');

        return ((int) $enteros * 100) + (int) $decimales;
    }

    public static function porcentaje(int $puntos): string
    {
        return intdiv($puntos, 100).'.'.str_pad((string) ($puntos % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function inicioDia(string $fecha, string $zona): Carbon
    {
        return Carbon::parse($fecha, $zona)->startOfDay()->timezone(config('app.timezone'));
    }

    public static function finDia(string $fecha, string $zona): Carbon
    {
        return Carbon::parse($fecha, $zona)->endOfDay()->timezone(config('app.timezone'));
    }
}
