<?php

namespace App\Services\Rh;

use App\Models\RhBancoTiempo;
use Illuminate\Validation\ValidationException;

class SaldarBancoTiempoService
{
    public function ejecutar(RhBancoTiempo $registro, ?string $fechaDevolucion = null): RhBancoTiempo
    {
        if (!$registro->estaActiva()) {
            throw ValidationException::withMessages([
                'estado' => 'Este registro ya fue saldado anteriormente.',
            ]);
        }

        $registro->update([
            'estado'           => RhBancoTiempo::ESTADO_SALDADA,
            'fecha_devolucion' => $fechaDevolucion ?? now()->toDateString(),
        ]);

        return $registro->fresh(['colaborador', 'registradoPor']);
    }
}
