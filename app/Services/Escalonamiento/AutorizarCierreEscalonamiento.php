<?php

namespace App\Services\Escalonamiento;

use App\Models\Escalonamiento\EscalonamientoCierre;
use App\Models\Escalonamiento\EscalonamientoIncidencia;
use App\Models\Escalonamiento\EscalonamientoPeriodo;
use App\Models\User;
use App\Services\Escalonamiento\Excepciones\CierreEscalonamientoException;
use Illuminate\Support\Facades\DB;

class AutorizarCierreEscalonamiento
{
    /**
     * @return EscalonamientoCierre
     */
    public function autorizar(EscalonamientoPeriodo $periodo, User $usuario): EscalonamientoCierre
    {
        return DB::transaction(function () use ($periodo, $usuario) {
            $periodo = EscalonamientoPeriodo::query()->lockForUpdate()->findOrFail($periodo->id);
            $cierre = $periodo->cierreVigente;
            if (! $cierre || $cierre->id !== $periodo->escalonamiento_cierre_vigente_id) {
                throw new CierreEscalonamientoException('No hay simulación de cierre vigente.');
            }

            if ($periodo->estado !== EscalonamientoPeriodo::ESTADO_EN_REVISION) {
                throw new CierreEscalonamientoException('El período no está en revisión de cierre.');
            }

            if (! $cierre->puedeAutorizarse()) {
                throw new CierreEscalonamientoException('El cierre no puede autorizarse en su estado actual.');
            }

            $codigos = config('escalonamiento.incidencias_bloquean_autorizacion', []);
            $abiertas = EscalonamientoIncidencia::query()
                ->where('escalonamiento_periodo_id', $periodo->id)
                ->where('estado', 'abierta')
                ->when($codigos !== [], fn ($q) => $q->whereIn('codigo', $codigos))
                ->exists();
            if ($abiertas) {
                throw new CierreEscalonamientoException('Resuelva las incidencias abiertas antes de autorizar.');
            }

            $cierre->estado = EscalonamientoCierre::ESTADO_AUTORIZADO;
            $cierre->autorizado_en = now();
            $cierre->autorizado_por_user_id = $usuario->id;
            $cierre->save();

            $periodo->estado = EscalonamientoPeriodo::ESTADO_AUTORIZADO;
            $periodo->save();

            return $cierre->fresh('detalles');
        });
    }
}
