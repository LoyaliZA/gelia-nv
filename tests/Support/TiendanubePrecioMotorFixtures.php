<?php

namespace Tests\Support;

use App\Models\Tiendanube\TiendanubePrecioFuenteVersion;
use App\Services\Tiendanube\Precios\TiendanubePrecioFuenteDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorPoliticaDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorReglaDto;
use App\Services\Tiendanube\Precios\TiendanubePrecioMotorSnapshotDto;

/**
 * Fixtures del motor TN-07B reutilizables por C/D/E/F.
 */
final class TiendanubePrecioMotorFixtures
{
    public static function snapshot(
        array $fuentes,
        int $tiendaId = 8004291,
        int $productoId = 10,
        int $varianteId = 100
    ): array {
        return [
            'tienda_id' => $tiendaId,
            'producto_id' => $productoId,
            'variante_id' => $varianteId,
            'fuentes' => $fuentes,
        ];
    }

    public static function snapshotDto(array $fuentes, int $varianteId = 100): TiendanubePrecioMotorSnapshotDto
    {
        return TiendanubePrecioMotorSnapshotDto::fromArray(self::snapshot($fuentes, varianteId: $varianteId));
    }

    public static function fuente(string $tipo, ?string $valor, int $varianteId = 100, bool $faltante = false, ?int $listaId = null): array
    {
        return (new TiendanubePrecioFuenteDto(
            tipo: $tipo,
            listaId: $listaId,
            version: $faltante ? null : 1,
            moneda: $faltante ? null : 'MXN',
            fecha: $faltante ? null : '2026-09-11T12:00:00+00:00',
            valorDecimal: $valor,
            productoId: 10,
            varianteId: $varianteId,
            tiendaId: 8004291,
            faltante: $faltante,
            predeterminada: $tipo === TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL && ! $faltante,
        ))->toArray();
    }

    public static function snapshotCosto330(?string $normal = null, ?string $promo = null): array
    {
        $fuentes = [
            TiendanubePrecioFuenteFixtures::costoLocal330(),
        ];
        if ($normal !== null) {
            $fuentes[] = self::fuente(TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL, $normal);
        }
        if ($promo !== null) {
            $fuentes[] = self::fuente(TiendanubePrecioFuenteVersion::TIPO_PRECIO_PROMOCIONAL_ACTUAL, $promo);
        }

        return self::snapshot($fuentes);
    }

    public static function snapshotNormal(string $normal, ?string $costo = null, ?string $promo = null): array
    {
        $fuentes = [
            self::fuente(TiendanubePrecioFuenteVersion::TIPO_PRECIO_NORMAL_ACTUAL, $normal),
        ];
        if ($costo !== null) {
            $fuentes[] = self::fuente(TiendanubePrecioFuenteVersion::TIPO_COSTO_LOCAL, $costo);
        }
        if ($promo !== null) {
            $fuentes[] = self::fuente(TiendanubePrecioFuenteVersion::TIPO_PRECIO_PROMOCIONAL_ACTUAL, $promo);
        }

        return self::snapshot($fuentes);
    }

    /**
     * @param  list<array<string, mixed>>  $condiciones
     * @return array<string, mixed>
     */
    public static function regla(
        string $id,
        string $base,
        string $operacion,
        string $destino,
        ?string $parametro = null,
        array $condiciones = [],
        string $redondeo = 'dos_decimales_half_up',
        string $redondeoDireccion = 'arriba',
        ?int $baseListaId = null
    ): array {
        return [
            'id' => $id,
            'condiciones' => $condiciones,
            'base' => $base,
            'base_lista_id' => $baseListaId,
            'operacion' => $operacion,
            'parametro' => $parametro,
            'destino' => $destino,
            'redondeo' => $redondeo,
            'redondeo_direccion' => $redondeoDireccion,
        ];
    }

    public static function reglaDto(array $datos): TiendanubePrecioMotorReglaDto
    {
        return TiendanubePrecioMotorReglaDto::fromArray($datos);
    }

    public static function politicaPorDefecto(): TiendanubePrecioMotorPoliticaDto
    {
        return new TiendanubePrecioMotorPoliticaDto;
    }
}
