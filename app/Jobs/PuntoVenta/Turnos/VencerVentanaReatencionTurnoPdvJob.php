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

    /**
     * La ventana máxima es 1440 minutos. El candado debe sobrevivir a ese retraso.
     */
    public int $uniqueFor = 90000;

    public function __construct(
        public int $turnoId,
        public ?int $expiraAtTimestamp = null,
    ) {}

    public function uniqueId(): string
    {
        // isset cubre jobs ya encolados antes de existir expiraAtTimestamp.
        $marca = isset($this->expiraAtTimestamp) ? (int) $this->expiraAtTimestamp : 0;

        return 'pdv-ventana-reatencion:'.$this->turnoId.':'.$marca;
    }

    public function handle(VencerVentanaReatencionTurnoPdvService $servicio): void
    {
        $servicio->ejecutar($this->turnoId, now());
    }
}
