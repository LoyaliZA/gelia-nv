<?php

namespace App\Jobs\PuntoVenta\Turnos;

use App\Services\PuntoVenta\Turnos\AlertaEsperaProximoVencerTurnoPdvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AlertaEsperaProximoVencerTurnoPdvJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $atencionId,
    ) {}

    public function uniqueId(): string
    {
        return 'pdv-espera-proximo:'.$this->atencionId;
    }

    public function handle(AlertaEsperaProximoVencerTurnoPdvService $servicio): void
    {
        $servicio->ejecutar($this->atencionId, now());
    }
}
