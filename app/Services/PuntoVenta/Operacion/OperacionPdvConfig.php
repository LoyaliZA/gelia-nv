<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Services\PuntoVenta\Turnos\TurnosPdvConfig;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class OperacionPdvConfig
{
    public function __construct(
        private readonly TurnosPdvConfig $turnos,
        private readonly HorarioCierreOperacionPdvConfig $horarioCierre,
    ) {}

    public function zonaHorariaOperativa(?int $sucursalId = null): string
    {
        $horario = $this->horarioCierre->resolverParaSucursal($sucursalId);

        return $horario['zona_horaria'] ?? $this->turnos->zonaHorariaOperativa($sucursalId);
    }

    public function fechaOperativa(int $sucursalId, ?CarbonInterface $momento = null): string
    {
        $zona = $this->zonaHorariaOperativa($sucursalId);
        $referencia = $momento instanceof Carbon
            ? $momento->copy()
            : ($momento ?? now())->copy();

        return $referencia->timezone($zona)->toDateString();
    }
}
