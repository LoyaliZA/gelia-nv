<?php

namespace Tests\Support;

use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Services\Tiendanube\Precios\TiendanubePrecioFuenteDto;

/**
 * Fixtures del contrato de fuentes TN-07A para TN-07B y siguientes.
 */
final class TiendanubePrecioFuenteFixtures
{
    public static function costoLocal330(int $tiendaId = 8004291, int $productoId = 10, int $varianteId = 100): array
    {
        return (new TiendanubePrecioFuenteDto(
            tipo: TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            listaId: null,
            version: 1,
            moneda: 'MXN',
            fecha: '2026-09-11T12:00:00+00:00',
            valorDecimal: '330.00',
            productoId: $productoId,
            varianteId: $varianteId,
            tiendaId: $tiendaId,
            faltante: false,
            predeterminada: true,
        ))->toArray();
    }

    public static function costoLocalCero(int $tiendaId = 8004291, int $productoId = 10, int $varianteId = 101): array
    {
        return (new TiendanubePrecioFuenteDto(
            tipo: TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            listaId: null,
            version: 1,
            moneda: 'MXN',
            fecha: '2026-09-11T12:00:00+00:00',
            valorDecimal: '0.00',
            productoId: $productoId,
            varianteId: $varianteId,
            tiendaId: $tiendaId,
            faltante: false,
            predeterminada: true,
        ))->toArray();
    }

    public static function costoLocalAusente(int $tiendaId = 8004291, int $productoId = 10, int $varianteId = 102): array
    {
        return (new TiendanubePrecioFuenteDto(
            tipo: TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL,
            listaId: null,
            version: null,
            moneda: null,
            fecha: null,
            valorDecimal: null,
            productoId: $productoId,
            varianteId: $varianteId,
            tiendaId: $tiendaId,
            faltante: true,
            predeterminada: false,
        ))->toArray();
    }

    public static function listaReferencia(int $listaId = 1, string $valor = '400.00', int $tiendaId = 8004291, int $productoId = 10, int $varianteId = 100): array
    {
        return (new TiendanubePrecioFuenteDto(
            tipo: TiendanubePrecioFuenteVersion::TIPO_LISTA_REFERENCIA,
            listaId: $listaId,
            version: 1,
            moneda: 'MXN',
            fecha: '2026-09-11T12:00:00+00:00',
            valorDecimal: $valor,
            productoId: $productoId,
            varianteId: $varianteId,
            tiendaId: $tiendaId,
            faltante: false,
            predeterminada: false,
        ))->toArray();
    }
}
