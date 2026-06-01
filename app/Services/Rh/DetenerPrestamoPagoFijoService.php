<?php

namespace App\Services\Rh;

use App\Models\RhPrestamoPagoFijo;
use Illuminate\Validation\ValidationException;

class DetenerPrestamoPagoFijoService
{
    public function pausar(RhPrestamoPagoFijo $prestamo): RhPrestamoPagoFijo
    {
        if ($prestamo->estado !== RhPrestamoPagoFijo::ESTADO_ACTIVO) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden pausar convenios activos.',
            ]);
        }

        $prestamo->update(['estado' => RhPrestamoPagoFijo::ESTADO_PAUSADO]);

        return $prestamo->fresh();
    }

    public function reanudar(RhPrestamoPagoFijo $prestamo): RhPrestamoPagoFijo
    {
        if ($prestamo->estado !== RhPrestamoPagoFijo::ESTADO_PAUSADO) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden reanudar convenios pausados.',
            ]);
        }

        $prestamo->update(['estado' => RhPrestamoPagoFijo::ESTADO_ACTIVO]);

        return $prestamo->fresh();
    }

    public function cancelar(RhPrestamoPagoFijo $prestamo): RhPrestamoPagoFijo
    {
        if (in_array($prestamo->estado, [RhPrestamoPagoFijo::ESTADO_LIQUIDADO, RhPrestamoPagoFijo::ESTADO_CANCELADO], true)) {
            throw ValidationException::withMessages([
                'estado' => 'El convenio ya está cerrado.',
            ]);
        }

        $prestamo->update(['estado' => RhPrestamoPagoFijo::ESTADO_CANCELADO]);

        return $prestamo->fresh();
    }
}
