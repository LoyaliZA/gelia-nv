<?php

namespace App\Support\PuntoVenta\Operacion;

enum TipoIntervaloOperativoPdv: string
{
    case Disponible = 'disponible';

    case EnPausa = 'en_pausa';

    case EnAtencion = 'en_atencion';
}
