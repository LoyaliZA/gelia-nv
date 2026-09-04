<?php

namespace App\Jobs\PuntoVenta\Turnos;

use App\Services\PuntoVenta\Turnos\VencerVentanaReatencionTurnoPdvService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VencerVentanaReatencionTurnoPdvJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 7200;

    public function __construct(
        public int $turnoId,
    ) {}

    public function uniqueId(): string
    {
        return 'pdv-ventana-reatencion:'.$this->turnoId;
    }

    public function handle(VencerVentanaReatencionTurnoPdvService $servicio): void
    {
        $servicio->ejecutar($this->turnoId, now());
    }
}
