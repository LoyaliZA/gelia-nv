<?php

namespace App\Http\Requests\PuntoVenta\Operacion;

use App\Http\Requests\PuntoVenta\PdvOperacionPisoRequest;
use App\Services\PuntoVenta\PuntoVentaModulo;

class AbrirJornadaPdvRequest extends PdvOperacionPisoRequest
{
    protected function permisoAccion(): string
    {
        return PuntoVentaModulo::PERMISO_OPERACION_JORNADA_ABRIR;
    }
}
