<?php

namespace App\Services\Tiendanube\Precios;

use App\Support\Tiendanube\Precios\TiendanubePrecioCondicionOperador;

final class TiendanubePrecioMotorCondicionEvaluador
{
    /**
     * Condiciones de una regla combinadas con AND, siempre contra el snapshot inicial.
     *
     * @param  list<TiendanubePrecioMotorCondicionDto>  $condiciones
     */
    public function cumple(array $condiciones, TiendanubePrecioMotorSnapshotDto $snapshot): bool
    {
        foreach ($condiciones as $condicion) {
            if (! $this->cumpleUna($condicion, $snapshot)) {
                return false;
            }
        }

        return true;
    }

    public function cumpleUna(TiendanubePrecioMotorCondicionDto $condicion, TiendanubePrecioMotorSnapshotDto $snapshot): bool
    {
        $actual = $snapshot->valor($condicion->campo, $condicion->listaId);
        $tiene = $actual !== null;

        return match ($condicion->operador) {
            TiendanubePrecioCondicionOperador::TieneValor => $tiene,
            TiendanubePrecioCondicionOperador::NoTieneValor => ! $tiene,
            default => $tiene && $this->compara($actual, $condicion),
        };
    }

    private function compara(string $actual, TiendanubePrecioMotorCondicionDto $condicion): bool
    {
        if ($condicion->operador === TiendanubePrecioCondicionOperador::Entre) {
            return $this->entre($actual, $condicion);
        }

        $umbral = TiendanubePrecioDecimal::parseParametro((string) $condicion->valor);
        if (! $umbral['ok']) {
            return false;
        }

        $cmp = TiendanubePrecioDecimal::cmp($actual, $umbral['valor']);

        return match ($condicion->operador) {
            TiendanubePrecioCondicionOperador::Menor => $cmp < 0,
            TiendanubePrecioCondicionOperador::MenorIgual => $cmp <= 0,
            TiendanubePrecioCondicionOperador::Igual => $cmp === 0,
            TiendanubePrecioCondicionOperador::MayorIgual => $cmp >= 0,
            TiendanubePrecioCondicionOperador::Mayor => $cmp > 0,
            default => false,
        };
    }

    private function entre(string $actual, TiendanubePrecioMotorCondicionDto $condicion): bool
    {
        $desde = TiendanubePrecioDecimal::parseParametro((string) $condicion->valor);
        $hasta = TiendanubePrecioDecimal::parseParametro((string) $condicion->valorHasta);
        if (! $desde['ok'] || ! $hasta['ok']) {
            return false;
        }

        $cmpDesde = TiendanubePrecioDecimal::cmp($actual, $desde['valor']);
        $cmpHasta = TiendanubePrecioDecimal::cmp($actual, $hasta['valor']);

        $okDesde = $condicion->inclusivoDesde ? $cmpDesde >= 0 : $cmpDesde > 0;
        $okHasta = $condicion->inclusivoHasta ? $cmpHasta <= 0 : $cmpHasta < 0;

        return $okDesde && $okHasta;
    }
}
