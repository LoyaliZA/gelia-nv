<?php

namespace App\Services\PuntoVenta\Operacion;

use App\Models\PuntoVenta\JornadaPdv;
use App\Support\PuntoVenta\Operacion\EstadoJornadaPdv;
use Illuminate\Validation\ValidationException;

trait ResuelveConcurrenciaJornadaPdv
{
    protected function assertVersionJornada(JornadaPdv $jornada, int $versionEsperada): void
    {
        if ((int) $jornada->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó la jornada. Actualice la página e intente de nuevo.',
            ]);
        }
    }

    protected function jornadaActivaAbierta(int $userId, int $sucursalId): ?JornadaPdv
    {
        return JornadaPdv::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', EstadoJornadaPdv::Abierta)
            ->first();
    }

    protected function jornadaActiva(int $userId, int $sucursalId): ?JornadaPdv
    {
        return JornadaPdv::query()
            ->where('user_id', $userId)
            ->where('sucursal_id', $sucursalId)
            ->whereIn('estado', [
                EstadoJornadaPdv::Abierta,
                EstadoJornadaPdv::CerradaConAtencion,
            ])
            ->first();
    }
}
