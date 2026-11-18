<?php

namespace App\Support\ControlPedidos\Conversion;

/** Dispatcher por destino. P0 solo Municipal está activa. */
final class DispatcherPoliticasConversion
{
    public function porDestino(string $destino): PoliticaConversionModalidad
    {
        return match ($destino) {
            CodigosConversionModalidad::DESTINO_MUNICIPAL => new PoliticaConversionMunicipal,
            CodigosConversionModalidad::DESTINO_TIENDA => new PoliticaConversionTienda,
            CodigosConversionModalidad::DESTINO_NACIONAL => new PoliticaConversionNacional,
            default => throw new \InvalidArgumentException('Destino de conversión desconocido.'),
        };
    }
}
