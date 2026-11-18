<?php

namespace App\Support\ControlPedidos\Conversion;

use App\Models\ControlPedidos\PedidoBma;

/** P0 activa. No veta por pago ni remisión. No duplica el límite de cierre. */
final class PoliticaConversionMunicipal implements PoliticaConversionModalidad
{
    /** @return list<string> */
    public function origenes(): array
    {
        return [CodigosConversionModalidad::ORIGEN_TIENDA];
    }

    public function destino(): string
    {
        return CodigosConversionModalidad::DESTINO_MUNICIPAL;
    }

    public function activo(): bool
    {
        return true;
    }

    public function evaluar(PedidoBma $pedido): ResultadoPoliticaConversion
    {
        $origen = self::origenDe($pedido);
        if (! in_array($origen, $this->origenes(), true)) {
            return new ResultadoPoliticaConversion(false, 'Este pedido no admite el cambio a envío municipal.');
        }

        return new ResultadoPoliticaConversion(true);
    }

    private static function origenDe(PedidoBma $pedido): string
    {
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];

        return (string) ($json['modalidad_entrega'] ?? '');
    }
}
