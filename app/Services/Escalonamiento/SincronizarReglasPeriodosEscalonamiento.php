<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoPeriodo;

class SincronizarReglasPeriodosEscalonamiento
{
    public function __construct(
        private ConstruirSnapshotListasEscalonamiento $construir,
    ) {}

    /**
     * Actualiza el snapshot de reglas en períodos que aún admiten carga o movimientos.
     */
    public function sincronizarPeriodosEditables(): int
    {
        $snapshot = $this->construir->desdeCatalogo();
        $actualizados = 0;

        EscalonamientoPeriodo::query()
            ->with('reglaVersion')
            ->orderBy('anio')
            ->orderBy('mes')
            ->each(function (EscalonamientoPeriodo $periodo) use ($snapshot, &$actualizados): void {
                if ($periodo->estaCerradoOficialmente()) {
                    return;
                }

                $version = $periodo->reglaVersion;
                if ($version === null) {
                    return;
                }

                $version->snapshot = $snapshot;
                $version->save();
                $actualizados++;
            });

        return $actualizados;
    }
}
