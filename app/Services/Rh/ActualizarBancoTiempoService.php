<?php

namespace App\Services\Rh;

use App\Models\RhBancoTiempo;
use Illuminate\Validation\ValidationException;

class ActualizarBancoTiempoService
{
    public function ejecutar(RhBancoTiempo $registro, array $datos): RhBancoTiempo
    {
        if (!$registro->estaActiva()) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden editar registros con estado Activa.',
            ]);
        }

        $registro->update([
            'horas_pendientes' => $datos['horas_pendientes'],
            'origen_deuda'     => trim($datos['origen_deuda']),
            'fecha_acuerdo'    => $datos['fecha_acuerdo'],
        ]);

        return $registro->fresh(['colaborador.departamento', 'colaborador.area', 'registradoPor']);
    }
}
