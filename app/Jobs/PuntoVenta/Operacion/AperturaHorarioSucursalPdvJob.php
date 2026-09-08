<?php

namespace App\Jobs\PuntoVenta\Operacion;

use App\Services\PuntoVenta\Operacion\AperturaHorarioSucursalPdvService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AperturaHorarioSucursalPdvJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $sucursalId,
        public string $evaluadoAtIso,
    ) {}

    public function uniqueId(): string
    {
        return 'pdv-apertura-horario:'.$this->sucursalId.':'.substr($this->evaluadoAtIso, 0, 16);
    }

    public function handle(AperturaHorarioSucursalPdvService $servicio): void
    {
        $servicio->ejecutar($this->sucursalId, Carbon::parse($this->evaluadoAtIso));
    }
}
