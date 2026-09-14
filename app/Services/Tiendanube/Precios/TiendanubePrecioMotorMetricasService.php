<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioCampoCondicion;
use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;

final class TiendanubePrecioMotorMetricasService
{
    /**
     * @param  array<string, TiendanubePrecioMotorResultadoCampoDto>  $campos
     * @return array{
     *     margen_estimado: ?string,
     *     costo_usado: ?string,
     *     diferencia_absoluta: ?string,
     *     variacion_porcentual: ?string,
     *     margen_calculable: bool,
     *     variacion_porcentual_calculable: bool
     * }
     */
    public function calcular(array $campos, TiendanubePrecioMotorSnapshotDto $snapshot): array
    {
        $costo = $snapshot->valor(TiendanubePrecioCampoCondicion::CostoLocal)
            ?? $snapshot->valor(TiendanubePrecioCampoCondicion::CostoRemotoActual);

        $normal = $campos[TiendanubePrecioDestino::Normal->value] ?? null;
        $promo = $campos[TiendanubePrecioDestino::Promocional->value] ?? null;

        $valorNormal = $normal?->valorFinal;
        $valorPromo = $promo?->intencion === TiendanubePrecioIntencion::Eliminar
            ? null
            : $promo?->valorFinal;

        $venta = $this->ventaEfectiva($valorNormal, $valorPromo);
        $origen = $snapshot->valor(TiendanubePrecioCampoCondicion::PrecioNormalActual);

        $margenCalculable = $costo !== null && $venta !== null && ! TiendanubePrecioDecimal::esCero($venta);
        $margenEstimado = null;
        $diferencia = null;

        if ($margenCalculable) {
            $diferencia = TiendanubePrecioDecimal::serializar(TiendanubePrecioDecimal::sub($venta, $costo));
            $ratio = TiendanubePrecioDecimal::div(TiendanubePrecioDecimal::sub($venta, $costo), $venta);
            $margenEstimado = $ratio === null
                ? null
                : TiendanubePrecioDecimal::format(TiendanubePrecioDecimal::roundHalfUp(TiendanubePrecioDecimal::mul($ratio, '100'), 2), 2);
        }

        $variacionCalculable = $origen !== null && ! TiendanubePrecioDecimal::esCero($origen) && $venta !== null;
        $variacion = null;
        if ($variacionCalculable) {
            $delta = TiendanubePrecioDecimal::div(TiendanubePrecioDecimal::sub($venta, $origen), $origen);
            $variacion = $delta === null
                ? null
                : TiendanubePrecioDecimal::format(TiendanubePrecioDecimal::roundHalfUp(TiendanubePrecioDecimal::mul($delta, '100'), 2), 2);
            $variacionCalculable = $variacion !== null;
        }

        return [
            'margen_estimado' => $margenEstimado,
            'costo_usado' => $costo,
            'diferencia_absoluta' => $diferencia,
            'variacion_porcentual' => $variacion,
            'margen_calculable' => $margenCalculable && $margenEstimado !== null,
            'variacion_porcentual_calculable' => $variacionCalculable,
        ];
    }

    private function ventaEfectiva(?string $normal, ?string $promo): ?string
    {
        if ($promo !== null && $normal !== null
            && TiendanubePrecioDecimal::cmp($promo, '0') > 0
            && TiendanubePrecioDecimal::cmp($promo, $normal) < 0) {
            return $promo;
        }

        return $normal;
    }
}
