<?php

namespace App\Support\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdv;

final class EstadosActivosTurnoPdv
{
    /** @return list<string> */
    public static function valores(): array
    {
        return [
            TurnoPdv::ESTADO_EN_COLA,
            TurnoPdv::ESTADO_ASIGNADO,
            TurnoPdv::ESTADO_EN_REATENCION,
        ];
    }
}
