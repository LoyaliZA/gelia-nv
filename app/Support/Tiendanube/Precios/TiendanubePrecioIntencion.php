<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioIntencion: string
{
    case Conservar = 'conservar';
    case Establecer = 'establecer';
    case Eliminar = 'eliminar';
}
