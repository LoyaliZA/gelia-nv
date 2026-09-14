<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioRedondeoDireccion: string
{
    case Arriba = 'arriba';
    case Abajo = 'abajo';
    case Cercano = 'cercano';
}
