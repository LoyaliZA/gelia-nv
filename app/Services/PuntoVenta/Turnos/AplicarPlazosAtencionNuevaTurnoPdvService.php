<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Models\PuntoVenta\TurnoPdvAtencion;
use Carbon\CarbonInterface;

class AplicarPlazosAtencionNuevaTurnoPdvService
{
    public function __construct(
        private readonly PlazosTurnosPdvConfig $plazos,
        private readonly ProgramarAlertasPlazosAtencionTurnoPdvService $programarAlertas,
        private readonly AplicarInicioAtencionTurnoPdvService $inicioAtencion,
    ) {}

    public function ejecutar(TurnoPdvAtencion $atencion, CarbonInterface $ahora): TurnoPdvAtencion
    {
        $config = $this->plazos->obtenerOPredeterminado();

        if ($config['inicio_atencion_automatico'] ?? false) {
            return $this->inicioAtencion->ejecutar($atencion, $ahora);
        }

        $this->programarAlertas->programarEsperaProximoVencer($atencion, $ahora);

        return $atencion;
    }
}
