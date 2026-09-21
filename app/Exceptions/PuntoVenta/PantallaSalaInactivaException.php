<?php

namespace App\Exceptions\PuntoVenta;

use App\Models\PuntoVenta\PdvPantallaSalaToken;
use RuntimeException;

final class PantallaSalaInactivaException extends RuntimeException
{
    public function __construct(
        public readonly PdvPantallaSalaToken $registro,
    ) {
        parent::__construct('La pantalla de sala está desactivada.');
    }
}
