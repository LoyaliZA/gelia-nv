<?php

namespace App\Support\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;

/** P1. Visible y deshabilitada en P0. */
final class PoliticaConversionTienda implements PoliticaConversionModalidad
{
    /** @return list<string> */
    public function origenes(): array
    {
        return [CodigosConversionModalidad::DESTINO_MUNICIPAL];
    }

    public function destino(): string
    {
        return CodigosConversionModalidad::DESTINO_TIENDA;
    }

    public function activo(): bool
    {
        return false;
    }

    public function evaluar(PedidoBma $pedido): ResultadoPoliticaConversion
    {
        return new ResultadoPoliticaConversion(false, 'El cambio a Tienda no está habilitado.');
    }
}
