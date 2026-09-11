<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeProductoImagen;
use App\Models\Tiendanube\TiendanubeProductoImagenOperacion;

class TiendanubeProductoImagenCarga
{
    public function __construct(
        public TiendanubeProductoImagen $imagen,
        public TiendanubeProductoImagenOperacion $operacion,
    ) {}
}
