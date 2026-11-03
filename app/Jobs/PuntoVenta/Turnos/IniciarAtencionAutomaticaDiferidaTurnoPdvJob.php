<?php

namespace App\Jobs\PuntoVenta\Turnos;

use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Services\PuntoVenta\Turnos\AplicarInicioAtencionTurnoPdvService;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IniciarAtencionAutomaticaDiferidaTurnoPdvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $atencionId,
    ) {}

    public function handle(AplicarInicioAtencionTurnoPdvService $inicio): void
    {
        $atencion = TurnoPdvAtencion::query()->find($this->atencionId);
        if (! $atencion instanceof TurnoPdvAtencion || $atencion->fin_at !== null || $atencion->atencion_inicio_at !== null) {
            return;
        }

        $turno = TurnoPdv::query()->find($atencion->turno_id);
        if (! $turno instanceof TurnoPdv) {
            return;
        }

        $jornada = JornadaPdv::query()
            ->where('user_id', $atencion->user_id)
            ->where('sucursal_id', $turno->sucursal_id)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->first();

        if ($jornada?->disponible_desde !== null && now()->lt($jornada->disponible_desde)) {
            return;
        }

        $inicio->ejecutar($atencion, now());
    }
}
