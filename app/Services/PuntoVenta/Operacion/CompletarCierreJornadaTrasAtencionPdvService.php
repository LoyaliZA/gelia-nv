<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\IntervaloOperativoPdv;
use App\Models\PuntoVenta\JornadaPdv;
use App\Models\PuntoVenta\TurnoPdvAtencion;
use App\Models\User;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CompletarCierreJornadaTrasAtencionPdvService
{
    public function ejecutar(User $actor, int $sucursalId, CarbonInterface $ahora): void
    {
        DB::transaction(function () use ($actor, $sucursalId, $ahora): void {
            $jornada = JornadaPdv::query()
                ->where('user_id', $actor->id)
                ->where('sucursal_id', $sucursalId)
                ->where('estado', EstadoJornadaPdv::CerradaConAtencion)
                ->lockForUpdate()
                ->first();

            if (! $jornada instanceof JornadaPdv) {
                return;
            }

            $tieneAtencionAbierta = TurnoPdvAtencion::query()
                ->where('user_id', $actor->id)
                ->whereNull('fin_at')
                ->exists();

            if ($tieneAtencionAbierta) {
                return;
            }

            IntervaloOperativoPdv::query()
                ->where('jornada_id', $jornada->id)
                ->whereNull('fin_at')
                ->update(['fin_at' => $ahora]);

            $versionAnterior = (int) $jornada->version;

            JornadaPdv::query()
                ->whereKey($jornada->id)
                ->where('version', $versionAnterior)
                ->update([
                    'estado' => EstadoJornadaPdv::Cerrada,
                    'version' => $versionAnterior + 1,
                ]);
        });
    }
}
