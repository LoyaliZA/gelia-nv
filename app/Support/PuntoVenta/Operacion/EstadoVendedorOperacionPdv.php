<?php

namespace App\Support\PuntoVenta\Operacion;

enum EstadoVendedorOperacionPdv: string
{
    case NoActivado = 'no_activado';

    case NoLlego = 'no_llego';

    case Disponible = 'disponible';

    case Atendiendo = 'atendiendo';

    case EnRetencion = 'en_retencion';

    case CierrePendiente = 'cierre_pendiente';

    case JornadaCerrada = 'jornada_cerrada';

    public function recibeTurnos(): bool
    {
        return $this === self::Disponible;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoActivado => 'No activado',
            self::NoLlego => 'No llegó',
            self::Disponible => 'Disponible',
            self::Atendiendo => 'Atendiendo',
            self::EnRetencion => 'En pausa',
            self::CierrePendiente => 'Cierre pendiente',
            self::JornadaCerrada => 'Jornada finalizada',
        };
    }
}
