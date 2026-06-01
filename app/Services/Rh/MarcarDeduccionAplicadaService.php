<?php

namespace App\Services\Rh;

use App\Models\RhDeduccion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarcarDeduccionAplicadaService
{
    public function ejecutar(User $usuario, RhDeduccion $registro): RhDeduccion
    {
        if ($registro->estado_deduccion === RhDeduccion::ESTADO_APLICADO) {
            throw ValidationException::withMessages([
                'estado_deduccion' => 'Esta deducción ya fue aplicada.',
            ]);
        }

        $registro->update([
            'estado_deduccion' => RhDeduccion::ESTADO_APLICADO,
            'fecha_aplicacion_deduccion' => now()->toDateString(),
        ]);

        return $registro->fresh(['colaborador', 'reglaIncidencia', 'registradoPor']);
    }
}
