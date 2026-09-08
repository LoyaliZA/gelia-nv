<?php

namespace App\Services\PuntoVenta\Turnos;

use App\Jobs\PuntoVenta\Turnos\AlertaEsperaProximoVencerTurnoPdvJob;
use App\Jobs\PuntoVenta\Turnos\AlertaProrrogaProximoVencerTurnoPdvJob;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use Carbon\CarbonInterface;

class ProgramarAlertasPlazosAtencionTurnoPdvService
{
    public function __construct(
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    public function programarEsperaProximoVencer(TurnoPdvAtencion $atencion, CarbonInterface $referencia): void
    {
        if ($atencion->inicio_at === null) {
            return;
        }

        $plazos = $this->plazos->obtener();
        $avisoMinutos = max(1, (int) config('pdv_alertas.aviso_previo_minutos.espera_inicial', 1));
        $minutosHastaAviso = max(0, $plazos['espera_inicial_minutos'] - $avisoMinutos);
        $disparo = $referencia->copy()->addMinutes($minutosHastaAviso);

        AlertaEsperaProximoVencerTurnoPdvJob::dispatch($atencion->id)
            ->delay($disparo);
    }

    public function programarProrrogaProximoVencer(TurnoPdvAtencion $atencion, CarbonInterface $atencionInicioAt): void
    {
        $plazos = $this->plazos->obtener();
        $avisoMinutos = max(1, (int) config('pdv_alertas.aviso_previo_minutos.prorroga', 2));
        $minutosHastaAviso = max(0, $plazos['prorroga_minutos'] - $avisoMinutos);
        $disparo = $atencionInicioAt->copy()->addMinutes($minutosHastaAviso);

        AlertaProrrogaProximoVencerTurnoPdvJob::dispatch($atencion->id)
            ->delay($disparo);
    }
}
