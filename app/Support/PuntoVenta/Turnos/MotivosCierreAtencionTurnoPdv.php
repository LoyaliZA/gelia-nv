<?php

namespace App\Support\PuntoVenta\Turnos;

final class MotivosCierreAtencionTurnoPdv
{
    public const VENTA = 'venta';

    public const SIN_VENTA = 'sin_venta';

    public const NO_SE_PRESENTO = 'no_se_presento';

    public const TRANSFERENCIA = 'transferencia';

    public const OTRO = 'otro';

    /** @return list<string> */
    public static function valoresOperador(): array
    {
        return [
            self::VENTA,
            self::SIN_VENTA,
            self::NO_SE_PRESENTO,
            self::OTRO,
        ];
    }

    public static function esValidoOperador(string $motivo): bool
    {
        return in_array($motivo, self::valoresOperador(), true);
    }
}
