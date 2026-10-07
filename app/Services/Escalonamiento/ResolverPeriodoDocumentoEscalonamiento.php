<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoPeriodo;

class ResolverPeriodoDocumentoEscalonamiento
{
    /**
     * @return array{anio: int, mes: int}|null
     */
    public function mesDesdeFecha(?string $fecha): ?array
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $partes = explode('-', $fecha);
        $anio = (int) ($partes[0] ?? 0);
        $mes = (int) ($partes[1] ?? 0);
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            return null;
        }

        return ['anio' => $anio, 'mes' => $mes];
    }

    /**
     * -1 pasado, 0 mismo mes, 1 futuro respecto al período de referencia.
     */
    public function compararConPeriodo(?string $fecha, EscalonamientoPeriodo $periodo): ?int
    {
        $destino = $this->mesDesdeFecha($fecha);
        if ($destino === null) {
            return null;
        }

        $claveFecha = $destino['anio'] * 100 + $destino['mes'];
        $clavePeriodo = (int) $periodo->anio * 100 + (int) $periodo->mes;

        return $claveFecha <=> $clavePeriodo;
    }

    public function periodoPorFecha(?string $fecha): ?EscalonamientoPeriodo
    {
        $destino = $this->mesDesdeFecha($fecha);
        if ($destino === null) {
            return null;
        }

        return EscalonamientoPeriodo::query()
            ->where('anio', $destino['anio'])
            ->where('mes', $destino['mes'])
            ->first();
    }
}
