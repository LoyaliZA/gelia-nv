<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioOperacion: string
{
    case Copiar = 'copiar';
    case Fijar = 'fijar';
    case AumentarImporte = 'aumentar_importe';
    case ReducirImporte = 'reducir_importe';
    case AumentarPorcentaje = 'aumentar_porcentaje';
    case ReducirPorcentaje = 'reducir_porcentaje';
    case MargenObjetivo = 'margen_objetivo';
    case Eliminar = 'eliminar';

    public function requiereBase(): bool
    {
        return $this !== self::Fijar && $this !== self::Eliminar;
    }

    public function requiereParametro(): bool
    {
        return $this !== self::Copiar && $this !== self::Eliminar;
    }
}
