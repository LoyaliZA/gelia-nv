<?php

namespace App\Support\PuntoVenta\Operacion;

enum EstadoJornadaPdv: string
{
    case Cerrada = 'CERRADA';

    case Abierta = 'ABIERTA';

    case CerradaConAtencion = 'CERRADA_CON_ATENCION';

    public function esActiva(): bool
    {
        return $this === self::Abierta || $this === self::CerradaConAtencion;
    }
}
