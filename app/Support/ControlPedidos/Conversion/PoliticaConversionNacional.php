<?php

namespace App\Support\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;

/** Futuro. Visible y deshabilitada en P0. */
final class PoliticaConversionNacional implements PoliticaConversionModalidad
{
    /** @return list<string> */
    public function origenes(): array
    {
        return [
            CodigosConversionModalidad::ORIGEN_TIENDA,
            CodigosConversionModalidad::DESTINO_MUNICIPAL,
        ];
    }

    public function destino(): string
    {
        return CodigosConversionModalidad::DESTINO_NACIONAL;
    }

    public function activo(): bool
    {
        return false;
    }

    public function evaluar(PedidoBma $pedido): ResultadoPoliticaConversion
    {
        return new ResultadoPoliticaConversion(false, 'El cambio a envío nacional no está habilitado.');
    }
}
