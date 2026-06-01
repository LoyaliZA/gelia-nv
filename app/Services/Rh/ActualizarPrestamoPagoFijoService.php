<?php

namespace App\Services\Rh;

use App\Models\RhPrestamoPagoFijo;
use Illuminate\Validation\ValidationException;

class ActualizarPrestamoPagoFijoService
{
    public function ejecutar(RhPrestamoPagoFijo $prestamo, array $datos): RhPrestamoPagoFijo
    {
        if (in_array($prestamo->estado, [RhPrestamoPagoFijo::ESTADO_LIQUIDADO, RhPrestamoPagoFijo::ESTADO_CANCELADO], true)) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede editar un convenio liquidado o cancelado.',
            ]);
        }

        $tieneCuotasAplicadas = $prestamo->deducciones()
            ->where('estado_deduccion', 'aplicado')
            ->exists();

        if ($tieneCuotasAplicadas) {
            $prestamo->update([
                'observaciones' => $datos['observaciones'] ?? $prestamo->observaciones,
            ]);

            return $prestamo->fresh(['colaborador.departamento', 'colaborador.area', 'registradoPor']);
        }

        if (($datos['modalidad'] ?? $prestamo->modalidad) === RhPrestamoPagoFijo::MODALIDAD_UNICA_VEZ) {
            $datos['num_pagos_total'] = 1;
            $datos['fecha_ejecucion_programada'] = $datos['fecha_ejecucion_programada'] ?? null;
        } else {
            $datos['fecha_ejecucion_programada'] = null;
            if (array_key_exists('num_pagos_total', $datos) && ($datos['num_pagos_total'] === '' || $datos['num_pagos_total'] === null)) {
                $datos['num_pagos_total'] = null;
            }
        }

        $prestamo->update([
            'rh_colaborador_id' => $datos['rh_colaborador_id'] ?? $prestamo->rh_colaborador_id,
            'concepto' => trim($datos['concepto'] ?? $prestamo->concepto),
            'monto_cuota' => $datos['monto_cuota'] ?? $prestamo->monto_cuota,
            'num_pagos_total' => $datos['num_pagos_total'] ?? $prestamo->num_pagos_total,
            'modalidad' => $datos['modalidad'] ?? $prestamo->modalidad,
            'observaciones' => $datos['observaciones'] ?? $prestamo->observaciones,
            'fecha_ejecucion_programada' => $datos['fecha_ejecucion_programada'] ?? $prestamo->fecha_ejecucion_programada,
            'fecha_inicio' => $datos['fecha_inicio'] ?? $prestamo->fecha_inicio,
        ]);

        return $prestamo->fresh(['colaborador.departamento', 'colaborador.area', 'registradoPor']);
    }
}
