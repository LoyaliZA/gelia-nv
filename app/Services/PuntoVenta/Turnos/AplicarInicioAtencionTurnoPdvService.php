<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Jobs\PuntoVenta\Turnos\AlertaProrrogaAtencionTurnoPdvJob;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use Carbon\CarbonInterface;

class AplicarInicioAtencionTurnoPdvService
{
    public function __construct(
        private readonly PlazosTurnosPdvConfig $plazos,
        private readonly ProgramarAlertasPlazosAtencionTurnoPdvService $programarAlertas,
    ) {}

    public function ejecutar(TurnoPdvAtencion $atencion, CarbonInterface $ahora): TurnoPdvAtencion
    {
        if ($atencion->atencion_inicio_at !== null) {
            return $atencion;
        }

        $atencion->update([
            'atencion_inicio_at' => $ahora,
        ]);

        $atencion = $atencion->fresh();

        $plazos = $this->plazos->obtener();
        $disparo = $ahora->copy()->addMinutes($plazos['prorroga_minutos']);

        $this->programarAlertas->programarProrrogaProximoVencer($atencion, $ahora);

        AlertaProrrogaAtencionTurnoPdvJob::dispatch($atencion->id)
            ->delay($disparo);

        return $atencion;
    }
}
