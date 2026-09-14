<?php

namespace App\Support\Tiendanube\Precios;

enum TiendanubePrecioCondicionOperador: string
{
    case Menor = '<';
    case MenorIgual = '<=';
    case Igual = '=';
    case MayorIgual = '>=';
    case Mayor = '>';
    case Entre = 'entre';
    case TieneValor = 'tiene_valor';
    case NoTieneValor = 'no_tiene_valor';

    public function requiereValor(): bool
    {
        return $this !== self::TieneValor && $this !== self::NoTieneValor;
    }

    public function requiereRango(): bool
    {
        return $this === self::Entre;
    }
}
