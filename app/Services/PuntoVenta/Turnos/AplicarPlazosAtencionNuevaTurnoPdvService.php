<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Jobs\PuntoVenta\Turnos\IniciarAtencionAutomaticaDiferidaTurnoPdvJob;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use Carbon\CarbonInterface;

class AplicarPlazosAtencionNuevaTurnoPdvService
{
    public function __construct(
        private readonly PlazosTurnosPdvConfig $plazos,
        private readonly ProgramarAlertasPlazosAtencionTurnoPdvService $programarAlertas,
        private readonly AplicarInicioAtencionTurnoPdvService $inicioAtencion,
    ) {}

    public function ejecutar(
        TurnoPdvAtencion $atencion,
        CarbonInterface $ahora,
        ?CarbonInterface $habilitarDesde = null,
    ): TurnoPdvAtencion {
        $config = $this->plazos->obtenerOPredeterminado();
        $habilitarDesde = $habilitarDesde ?? $ahora;
        $diferido = $habilitarDesde->greaterThan($ahora);

        if (($config['inicio_atencion_automatico'] ?? false) && ! $diferido) {
            return $this->inicioAtencion->ejecutar($atencion, $ahora);
        }

        if (($config['inicio_atencion_automatico'] ?? false) && $diferido) {
            IniciarAtencionAutomaticaDiferidaTurnoPdvJob::dispatch($atencion->id)
                ->delay($habilitarDesde);

            return $atencion;
        }

        $this->programarAlertas->programarEsperaProximoVencer($atencion, $diferido ? $habilitarDesde : $ahora);

        return $atencion;
    }
}
