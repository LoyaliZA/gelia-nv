<?php

namespace App\Services\Rh;

use App\Models\RhIncidencia;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarcarIncidenciaAplicadaService
{
    public function ejecutar(User $usuario, RhIncidencia $registro): RhIncidencia
    {
        if ($registro->estado_deduccion === 'aplicado') {
            throw ValidationException::withMessages([
                'estado_deduccion' => 'La incidencia ya fue aplicada en nómina.',
            ]);
        }

        $registro->update(['estado_deduccion' => 'aplicado']);

        return $registro->fresh(['colaborador.departamento', 'colaborador.area', 'tipoFalta', 'registradoPor']);
    }
}
