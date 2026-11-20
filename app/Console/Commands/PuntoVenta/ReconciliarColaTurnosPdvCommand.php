<?php

namespace App\Console\Commands\PuntoVenta;

use App\Jobs\PuntoVenta\Turnos\EjecutarMatchmakerTurnosPdvJob;
use App\Models\PuntoVenta\TurnoPdv;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pdv:reconciliar-cola-turnos')]
#[Description('Reintenta el reparto en sucursales con turnos en cola, por si se perdió algún disparo del matchmaker')]
class ReconciliarColaTurnosPdvCommand extends Command
{
    public function handle(): int
    {
        // Los turnos con más de un minuto en cola ya tuvieron su disparo normal (alta, cierre, cooldown).
        $sucursales = TurnoPdv::query()
            ->where('estado', TurnoPdv::ESTADO_EN_COLA)
            ->whereNull('atencion_actual_id')
            ->where('es_demo', false)
            ->where('alta_at', '<=', now()->subMinute())
            ->distinct()
            ->pluck('sucursal_id');

        foreach ($sucursales as $sucursalId) {
            EjecutarMatchmakerTurnosPdvJob::dispatch((int) $sucursalId, 'reconciliacion');
        }

        $this->info(sprintf('Sucursales con cola reconciliadas=%d', $sucursales->count()));

        return self::SUCCESS;
    }
}
