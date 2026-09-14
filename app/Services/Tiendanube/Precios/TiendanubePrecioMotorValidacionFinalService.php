<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioDestino;
use App\Support\Tiendanube\Precios\TiendanubePrecioIntencion;

final class TiendanubePrecioMotorValidacionFinalService
{
    /**
     * @param  array<string, TiendanubePrecioMotorResultadoCampoDto>  $campos
     * @return array{campos: array<string, TiendanubePrecioMotorResultadoCampoDto>, errores: list<string>}
     */
    public function validar(
        array $campos,
        TiendanubePrecioMotorPoliticaDto $politica,
        ?string $costoUsado
    ): array {
        $errores = [];
        $normal = $campos[TiendanubePrecioDestino::Normal->value] ?? null;
        $promo = $campos[TiendanubePrecioDestino::Promocional->value] ?? null;

        $valorNormal = $normal?->valorFinal;
        $valorPromo = $promo?->intencion === TiendanubePrecioIntencion::Eliminar
            ? null
            : $promo?->valorFinal;

        if ($politica->bloquearVentaCero) {
            $venta = $this->ventaEfectiva($valorNormal, $valorPromo);
            if ($venta !== null && TiendanubePrecioDecimal::esCero($venta)) {
                $errores[] = 'venta_cero';
            }
        }

        if ($valorPromo !== null) {
            if (TiendanubePrecioDecimal::cmp($valorPromo, '0') <= 0) {
                $errores[] = 'promocional_invalido';
            } elseif ($valorNormal === null || TiendanubePrecioDecimal::cmp($valorPromo, $valorNormal) >= 0) {
                $errores[] = 'promocional_invalido';
            }
        }

        if ($politica->margenMinimo !== null) {
            $errores = array_merge($errores, $this->protegerMargen(
                $valorNormal,
                $valorPromo,
                $costoUsado,
                $politica->margenMinimo
            ));
        }

        if ($errores !== []) {
            $campos = $this->anexarErrores($campos, $errores);
        }

        return ['campos' => $campos, 'errores' => $errores];
    }

    /**
     * @return list<string>
     */
    private function protegerMargen(?string $normal, ?string $promo, ?string $costo, string $minimoRaw): array
    {
        $minimo = TiendanubePrecioDecimal::parseParametro($minimoRaw);
        if (! $minimo['ok']) {
            return ['politica_margen_minimo_invalido'];
        }

        if ($costo === null) {
            return ['proteccion_margen'];
        }

        $venta = $this->ventaEfectiva($normal, $promo);
        if ($venta === null || TiendanubePrecioDecimal::esCero($venta)) {
            return ['proteccion_margen'];
        }

        $margen = TiendanubePrecioDecimal::div(
            TiendanubePrecioDecimal::sub($venta, $costo),
            $venta
        );
        if ($margen === null) {
            return ['proteccion_margen'];
        }

        $margenPct = TiendanubePrecioDecimal::mul($margen, '100');
        if (TiendanubePrecioDecimal::cmp($margenPct, $minimo['valor']) < 0) {
            return ['proteccion_margen'];
        }

        return [];
    }

    private function ventaEfectiva(?string $normal, ?string $promo): ?string
    {
        if ($promo !== null && $normal !== null && TiendanubePrecioDecimal::cmp($promo, '0') > 0
            && TiendanubePrecioDecimal::cmp($promo, $normal) < 0) {
            return $promo;
        }

        return $normal;
    }

    /**
     * @param  array<string, TiendanubePrecioMotorResultadoCampoDto>  $campos
     * @param  list<string>  $errores
     * @return array<string, TiendanubePrecioMotorResultadoCampoDto>
     */
    private function anexarErrores(array $campos, array $errores): array
    {
        foreach ($campos as $clave => $campo) {
            $campos[$clave] = new TiendanubePrecioMotorResultadoCampoDto(
                destino: $campo->destino,
                intencion: $campo->intencion,
                valorBruto: $campo->valorBruto,
                valorFinal: $campo->valorFinal,
                reglaId: $campo->reglaId,
                explicacion: $campo->explicacion,
                alertas: $campo->alertas,
                errores: array_values(array_unique(array_merge($campo->errores, $errores))),
            );
        }

        return $campos;
    }
}
