<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoPeriodo;
use InvalidArgumentException;

class AsegurarPeriodoFuturoEscalonamiento
{
    public function __construct(private AbrirPeriodoEscalonamiento $abrirPeriodo) {}

    public function asegurar(int $anio, int $mes): EscalonamientoPeriodo
    {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Mes inválido.');
        }

        $existente = EscalonamientoPeriodo::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if ($existente) {
            if ($existente->estaCerradoOficialmente()) {
                return $existente;
            }

            return $existente;
        }

        $periodo = $this->abrirPeriodo->abrir($anio, $mes);
        if ($periodo->wasRecentlyCreated && $periodo->estado === EscalonamientoPeriodo::ESTADO_ABIERTO) {
            $periodo->estado = EscalonamientoPeriodo::ESTADO_HISTORIAL;
            $periodo->save();
        }

        return $periodo->fresh();
    }
}
