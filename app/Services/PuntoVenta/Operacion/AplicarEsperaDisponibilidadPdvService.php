<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\PuntoVenta\EsperaDisponibilidadPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Services\PuntoVenta\Turnos\PlazosTurnosPdvConfig;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Bus;

class AplicarEsperaDisponibilidadPdvService
{
    public function __construct(
        private readonly PlazosTurnosPdvConfig $plazos,
    ) {}

    public function ejecutar(
        int $userId,
        int $sucursalId,
        CarbonInterface $ahora,
        string $origen,
        ?int $actorId,
        ?int $atencionId = null,
        ?int $intervaloId = null,
    ): void {
        $jornada = JornadaPdv::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->first();

        if (! $jornada instanceof JornadaPdv) {
            return;
        }

        $segundos = max(0, (int) ($this->plazos->obtenerOPredeterminado()['cooldown_segundos'] ?? 0));
        $disponibleDesde = $segundos > 0 ? $ahora->copy()->addSeconds($segundos) : null;
        $anterior = $jornada->disponible_desde;

        $jornada->update(['disponible_desde' => $disponibleDesde]);

        EsperaDisponibilidadPdv::query()->create([
            'jornada_id' => $jornada->id,
            'user_id' => $userId,
            'sucursal_id' => $sucursalId,
            'actor_id' => $actorId,
            'origen' => $origen,
            'cooldown_segundos' => $segundos,
            'disponible_desde_anterior' => $anterior,
            'disponible_desde_nuevo' => $disponibleDesde,
            'atencion_id' => $atencionId,
            'intervalo_id' => $intervaloId,
            'ocurrido_at' => $ahora,
        ]);

        if ($disponibleDesde === null) {
            return;
        }

        $job = new EjecutarMatchmakerTurnosPdvJob(
            $sucursalId,
            'cooldown',
            'cooldown:'.$userId.':'.$disponibleDesde->getTimestamp(),
        );
        $job->delay($disponibleDesde);
        Bus::dispatch($job);
    }
}
