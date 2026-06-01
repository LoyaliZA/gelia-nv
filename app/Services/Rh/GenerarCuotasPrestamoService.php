<?php

namespace App\Services\Rh;

use App\Models\RhPrestamoPagoFijo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerarCuotasPrestamoService
{
    public function __construct(
        private CrearDeduccionDesdePrestamoService $crearDeduccion,
    ) {}

    public function ejecutar(Carbon $fechaInicio, Carbon $fechaFin, ?User $registrador = null): array
    {
        $registrador = $registrador ?? User::query()->orderBy('id')->first();

        $generadas = 0;
        $omitidas = 0;
        $errores = [];

        $prestamos = RhPrestamoPagoFijo::query()
            ->where('estado', RhPrestamoPagoFijo::ESTADO_ACTIVO)
            ->orderBy('id')
            ->get();

        foreach ($prestamos as $prestamo) {
            if (!$this->esElegible($prestamo, $fechaInicio, $fechaFin)) {
                $omitidas++;

                continue;
            }

            try {
                DB::transaction(function () use ($prestamo, $fechaInicio, $fechaFin, $registrador, &$generadas) {
                    $bloqueado = RhPrestamoPagoFijo::query()
                        ->whereKey($prestamo->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$bloqueado || !$this->esElegible($bloqueado, $fechaInicio, $fechaFin)) {
                        return;
                    }

                    $fechaOcurrencia = $this->resolverFechaOcurrencia($bloqueado, $fechaInicio, $fechaFin);

                    $this->crearDeduccion->ejecutar(
                        $bloqueado,
                        $registrador,
                        $fechaOcurrencia,
                        $fechaFin,
                    );

                    $nuevoPagos = $bloqueado->pagos_realizados + 1;
                    $nuevoEstado = $bloqueado->estado;

                    if ($bloqueado->modalidad === RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ) {
                        $nuevoEstado = RhPrestamoPagoFijo::ESTADO_LIQUIDADO;
                    } elseif ($bloqueado->num_pagos_total !== null && $nuevoPagos >= $bloqueado->num_pagos_total) {
                        $nuevoEstado = RhPrestamoPagoFijo::ESTADO_LIQUIDADO;
                    }

                    $bloqueado->update([
                        'pagos_realizados' => $nuevoPagos,
                        'ultimo_periodo_generado' => $fechaFin->toDateString(),
                        'estado' => $nuevoEstado,
                    ]);

                    $generadas++;
                });
            } catch (\Throwable $e) {
                $errores[] = [
                    'prestamo_id' => $prestamo->id,
                    'folio' => $prestamo->folio,
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return [
            'generadas' => $generadas,
            'omitidas' => $omitidas,
            'errores' => $errores,
        ];
    }

    public function esElegible(RhPrestamoPagoFijo $prestamo, Carbon $fechaInicio, Carbon $fechaFin): bool
    {
        if ($prestamo->estado !== RhPrestamoPagoFijo::ESTADO_ACTIVO) {
            return false;
        }

        if ($prestamo->ultimo_periodo_generado !== null
            && $prestamo->ultimo_periodo_generado->gte($fechaFin)) {
            return false;
        }

        if ($prestamo->modalidad === RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ) {
            if ($prestamo->pagos_realizados > 0) {
                return false;
            }

            if ($prestamo->fecha_ejecucion_programada === null) {
                return $prestamo->fecha_inicio->lte($fechaFin);
            }

            return $prestamo->fecha_ejecucion_programada->between($fechaInicio, $fechaFin);
        }

        if ($prestamo->fecha_inicio->gt($fechaFin)) {
            return false;
        }

        if ($prestamo->num_pagos_total !== null && $prestamo->pagos_realizados >= $prestamo->num_pagos_total) {
            return false;
        }

        return true;
    }

    private function resolverFechaOcurrencia(
        RhPrestamoPagoFijo $prestamo,
        Carbon $fechaInicio,
        Carbon $fechaFin,
    ): Carbon {
        if ($prestamo->modalidad === RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ && $prestamo->fecha_ejecucion_programada) {
            return $prestamo->fecha_ejecucion_programada->copy();
        }

        return $fechaFin->copy();
    }
}
