<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoPeriodo;

class InvalidarCierreSiDatosCambiaron
{
    public function __construct(
        private HashSnapshotPeriodoEscalonamiento $hashSnapshot,
    ) {}

    public function revisar(EscalonamientoPeriodo $periodo): bool
    {
        $periodo->refresh();
        $cierre = $periodo->cierreVigente;
        if (! $cierre) {
            return false;
        }

        if (! in_array($cierre->estado, [
            EscalonamientoCierre::ESTADO_BORRADOR,
            EscalonamientoCierre::ESTADO_AUTORIZADO,
        ], true)) {
            return false;
        }

        $hashActual = $this->hashSnapshot->calcular($periodo);
        if ($hashActual === $cierre->hash_snapshot) {
            return false;
        }

        $cierre->estado = EscalonamientoCierre::ESTADO_INVALIDADO;
        $cierre->save();

        $periodo->estado = EscalonamientoPeriodo::ESTADO_ABIERTO;
        $periodo->escalonamiento_cierre_vigente_id = null;
        $periodo->save();

        return true;
    }
}
