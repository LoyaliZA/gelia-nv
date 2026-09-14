<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoDireccion;
use App\Support\Tiendanube\Precios\TiendanubePrecioRedondeoModo;

final class TiendanubePrecioMotorRedondeoService
{
    public function aplicar(string $bruto, TiendanubePrecioRedondeoModo $modo, TiendanubePrecioRedondeoDireccion $direccion): string
    {
        $redondeado = match ($modo) {
            TiendanubePrecioRedondeoModo::DosDecimalesHalfUp => TiendanubePrecioDecimal::roundHalfUp($bruto, 2),
            TiendanubePrecioRedondeoModo::EnteroArriba => TiendanubePrecioDecimal::ceil($bruto),
            TiendanubePrecioRedondeoModo::EnteroAbajo => TiendanubePrecioDecimal::floor($bruto),
            TiendanubePrecioRedondeoModo::EnteroCercano => TiendanubePrecioDecimal::roundHalfUp($bruto, 0),
            TiendanubePrecioRedondeoModo::Terminacion90,
            TiendanubePrecioRedondeoModo::Terminacion99 => $this->terminacion($bruto, (string) $modo->centavosTerminacion(), $direccion),
        };

        return TiendanubePrecioDecimal::format($redondeado, 2);
    }

    /**
     * Escalón de una unidad monetaria hacia .90/.99.
     * Empate (valor ya en la terminación): siguiente escalón hacia arriba.
     */
    private function terminacion(string $bruto, string $centavos, TiendanubePrecioRedondeoDireccion $direccion): string
    {
        $piso = TiendanubePrecioDecimal::floor($bruto);
        $objetivoActual = TiendanubePrecioDecimal::add($piso, '0.'.$centavos);
        $objetivoAnterior = TiendanubePrecioDecimal::add(TiendanubePrecioDecimal::sub($piso, '1'), '0.'.$centavos);
        $objetivoSiguiente = TiendanubePrecioDecimal::add(TiendanubePrecioDecimal::add($piso, '1'), '0.'.$centavos);

        $cmpActual = TiendanubePrecioDecimal::cmp($bruto, $objetivoActual);

        if ($cmpActual === 0) {
            return $objetivoSiguiente;
        }

        return match ($direccion) {
            TiendanubePrecioRedondeoDireccion::Arriba => $cmpActual < 0 ? $objetivoActual : $objetivoSiguiente,
            TiendanubePrecioRedondeoDireccion::Abajo => $cmpActual > 0 ? $objetivoActual : $objetivoAnterior,
            TiendanubePrecioRedondeoDireccion::Cercano => $this->terminacionCercana(
                $bruto,
                $objetivoAnterior,
                $objetivoActual,
                $objetivoSiguiente
            ),
        };
    }

    private function terminacionCercana(string $bruto, string $anterior, string $actual, string $siguiente): string
    {
        $candidatos = [];
        foreach ([$anterior, $actual, $siguiente] as $candidato) {
            if (TiendanubePrecioDecimal::cmp($candidato, '0') < 0) {
                continue;
            }
            $dist = TiendanubePrecioDecimal::sub($candidato, $bruto);
            if (TiendanubePrecioDecimal::cmp($dist, '0') < 0) {
                $dist = TiendanubePrecioDecimal::mul($dist, '-1');
            }
            $candidatos[] = ['valor' => $candidato, 'dist' => $dist];
        }

        usort($candidatos, function (array $a, array $b) {
            $cmp = TiendanubePrecioDecimal::cmp($a['dist'], $b['dist']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return TiendanubePrecioDecimal::cmp($b['valor'], $a['valor']);
        });

        return $candidatos[0]['valor'];
    }
}
