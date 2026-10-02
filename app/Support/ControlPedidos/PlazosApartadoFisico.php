<?php

namespace App\Support\ControlPedidos;

use Carbon\Carbon;

final class PlazosApartadoFisico
{
    public static function cierreOperativo(Carbon $momento, string $zona, string $hora): Carbon
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $hora)), 2, 0);

        return $momento->copy()->timezone($zona)->setTime($h, $m, 0);
    }

    /**
     * Suma días hábiles (lunes a viernes) y conserva la hora del vencimiento.
     * ponytail: no consulta calendario de festivos; el siguiente paso es una tabla de días inhábiles.
     */
    public static function sumarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy();
        $restantes = max(1, $dias);
        while ($restantes > 0) {
            $fecha->addDay();
            if ($fecha->isWeekend()) {
                continue;
            }
            $restantes--;
        }

        return $fecha;
    }
}
