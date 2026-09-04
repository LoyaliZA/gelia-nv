<?php

namespace App\Support\PuntoVenta\Turnos;

final class MotivosBajaColaTurnoPdv
{
    public const SE_FUE = 'se_fue';

    public const DESISTIO = 'desistio';

    public const OTRO = 'otro';

    /** @return list<string> */
    public static function valores(): array
    {
        // ponytail: ítems concretos pendientes de decisión §16.1
        return [
            self::SE_FUE,
            self::DESISTIO,
            self::OTRO,
        ];
    }

    public static function esValido(string $motivo): bool
    {
        return in_array($motivo, self::valores(), true);
    }
}
