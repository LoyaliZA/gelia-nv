<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioOperacion;

final class TiendanubePrecioMotorOperacionCalculador
{
    /**
     * @return array{ok: bool, bruto: ?string, error: ?string}
     */
    public function calcular(TiendanubePrecioMotorReglaDto $regla, ?string $base): array
    {
        if ($regla->operacion === TiendanubePrecioOperacion::Eliminar) {
            return ['ok' => true, 'bruto' => null, 'error' => null];
        }

        if ($regla->operacion === TiendanubePrecioOperacion::Fijar) {
            $parametro = TiendanubePrecioDecimal::parseParametro($regla->parametro);
            if (! $parametro['ok']) {
                return ['ok' => false, 'bruto' => null, 'error' => 'parametro_invalido'];
            }

            return ['ok' => true, 'bruto' => $parametro['valor'], 'error' => null];
        }

        if ($base === null) {
            return ['ok' => false, 'bruto' => null, 'error' => 'fuente_ausente'];
        }

        if ($regla->operacion === TiendanubePrecioOperacion::Copiar) {
            return ['ok' => true, 'bruto' => $base, 'error' => null];
        }

        $parametro = TiendanubePrecioDecimal::parseParametro($regla->parametro);
        if (! $parametro['ok']) {
            return ['ok' => false, 'bruto' => null, 'error' => 'parametro_invalido'];
        }

        $p = $parametro['valor'];

        return match ($regla->operacion) {
            TiendanubePrecioOperacion::AumentarImporte => [
                'ok' => true,
                'bruto' => TiendanubePrecioDecimal::add($base, $p),
                'error' => null,
            ],
            TiendanubePrecioOperacion::ReducirImporte => $this->reducirImporte($base, $p),
            TiendanubePrecioOperacion::AumentarPorcentaje => $this->aumentarPorcentaje($base, $p),
            TiendanubePrecioOperacion::ReducirPorcentaje => $this->reducirPorcentaje($base, $p),
            TiendanubePrecioOperacion::MargenObjetivo => $this->margenObjetivo($base, $p, $regla),
            default => ['ok' => false, 'bruto' => null, 'error' => 'operacion_invalida'],
        };
    }

    /**
     * @return array{ok: bool, bruto: ?string, error: ?string}
     */
    private function reducirImporte(string $base, string $p): array
    {
        $bruto = TiendanubePrecioDecimal::sub($base, $p);
        if (TiendanubePrecioDecimal::cmp($bruto, '0') < 0) {
            return ['ok' => false, 'bruto' => null, 'error' => 'resultado_negativo'];
        }

        return ['ok' => true, 'bruto' => $bruto, 'error' => null];
    }

    /**
     * @return array{ok: bool, bruto: ?string, error: ?string}
     */
    private function aumentarPorcentaje(string $base, string $p): array
    {
        if (TiendanubePrecioDecimal::esCero($base)) {
            return ['ok' => false, 'bruto' => null, 'error' => 'aumento_sobre_cero'];
        }

        $bruto = TiendanubePrecioDecimal::aplicarPorcentaje($base, $p, true);
        if ($bruto === null) {
            return ['ok' => false, 'bruto' => null, 'error' => 'operacion_invalida'];
        }

        return ['ok' => true, 'bruto' => $bruto, 'error' => null];
    }

    /**
     * @return array{ok: bool, bruto: ?string, error: ?string}
     */
    private function reducirPorcentaje(string $base, string $p): array
    {
        if (TiendanubePrecioDecimal::cmp($p, '0') < 0 || TiendanubePrecioDecimal::cmp($p, '100') > 0) {
            return ['ok' => false, 'bruto' => null, 'error' => 'porcentaje_fuera_de_rango'];
        }

        $bruto = TiendanubePrecioDecimal::aplicarPorcentaje($base, $p, false);
        if ($bruto === null) {
            return ['ok' => false, 'bruto' => null, 'error' => 'operacion_invalida'];
        }

        return ['ok' => true, 'bruto' => $bruto, 'error' => null];
    }

    /**
     * @return array{ok: bool, bruto: ?string, error: ?string}
     */
    private function margenObjetivo(string $base, string $p, TiendanubePrecioMotorReglaDto $regla): array
    {
        if (! $regla->base->esCosto() || ! $regla->destino->esVenta()) {
            return ['ok' => false, 'bruto' => null, 'error' => 'margen_solo_desde_costo'];
        }

        if (TiendanubePrecioDecimal::cmp($p, '0') < 0 || TiendanubePrecioDecimal::cmp($p, '100') >= 0) {
            return ['ok' => false, 'bruto' => null, 'error' => 'margen_invalido'];
        }

        $bruto = TiendanubePrecioDecimal::margenObjetivo($base, $p);
        if ($bruto === null) {
            return ['ok' => false, 'bruto' => null, 'error' => 'margen_invalido'];
        }

        return ['ok' => true, 'bruto' => $bruto, 'error' => null];
    }
}
