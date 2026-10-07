<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use Illuminate\Support\Facades\DB;

class CancelarSimulacionCierreEscalonamiento
{
    public function cancelar(EscalonamientoPeriodo $periodo): void
    {
        DB::transaction(function () use ($periodo) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $cierre = $periodo->cierreVigente;
            if (! $cierre) {
                throw new CierreEscalonamientoException('No hay simulación de cierre para cancelar.');
            }

            if ($cierre->estaAplicado()) {
                throw new CierreEscalonamientoException('No se puede cancelar un cierre ya aplicado.');
            }

            $cierre->estado = EscalonamientoCierre::ESTADO_INVALIDADO;
            $cierre->save();

            $periodo->estado = $cierre->reconstruccion
                ? EscalonamientoPeriodo::ESTADO_HISTORIAL
                : EscalonamientoPeriodo::ESTADO_ABIERTO;
            $periodo->escalonamiento_cierre_vigente_id = null;
            $periodo->save();
        });
    }
}
