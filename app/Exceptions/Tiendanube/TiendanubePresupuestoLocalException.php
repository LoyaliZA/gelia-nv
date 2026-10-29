<?php

namespace App\Exceptions\Tiendanube;

use App\Services\Tiendanube\Gobernador\TiendanubeTurnoResultado;
use RuntimeException;

class TiendanubePresupuestoLocalException extends RuntimeException
{
    public function __construct(public readonly TiendanubeTurnoResultado $turno)
    {
        parent::__construct('Sin presupuesto local para la fase '.$turno->fase.'.');
    }
}
