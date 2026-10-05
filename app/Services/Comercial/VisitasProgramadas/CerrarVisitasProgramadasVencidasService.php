<?php

namespace App\Services\Comercial\VisitasProgramadas;

use App\Models\Comercial\VisitaClienteProgramada;
use Carbon\CarbonInterface;

final class CerrarVisitasProgramadasVencidasService
{
    public function handle(?CarbonInterface $referencia = null): int
    {
        $ahora = $referencia ?? now();
        $corte = $ahora->toDateString();

        return VisitaClienteProgramada::query()
            ->where('estado', VisitaClienteProgramada::ESTADO_PROGRAMADA)
            ->whereDate('fecha', '<', $corte)
            ->update(['estado' => VisitaClienteProgramada::ESTADO_NO_ASISTIO]);
    }
}
