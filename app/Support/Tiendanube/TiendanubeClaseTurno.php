<?php

namespace App\Support\Tiendanube;

enum TiendanubeClaseTurno: string
{
    case Interactivo = 'interactivo';
    case Recuperacion = 'recuperacion';
    case Publicacion = 'publicacion';
    case Catalogo = 'catalogo';
}
