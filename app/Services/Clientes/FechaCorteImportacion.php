<?php

namespace App\Services\Clientes;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** Fecha y hora de un corte declarado por quien importa. No usa la hora de subida. */
class FechaCorteImportacion
{
    public function exigir(string $texto): Carbon
    {
        $normalizado = trim(str_replace('T', ' ', $texto));
        if ($normalizado === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizado)) {
            throw ValidationException::withMessages([
                'fecha_corte' => 'Indica la fecha y la hora del corte. Una fecha sin hora no es válida.',
            ]);
        }

        $fecha = $this->instante($normalizado);
        if (! $fecha) {
            throw ValidationException::withMessages([
                'fecha_corte' => 'La fecha de corte no es válida. Usa fecha y hora.',
            ]);
        }
        if ($fecha->greaterThan(Carbon::now($this->zona()))) {
            throw ValidationException::withMessages([
                'fecha_corte' => 'El corte no puede ser posterior al momento actual.',
            ]);
        }

        return $fecha;
    }

    public function instante(string $texto): ?Carbon
    {
        $zona = $this->zona();
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat('!'.$formato, $texto, $zona);
            } catch (\Throwable) {
                continue;
            }
            if ($fecha instanceof Carbon && $fecha->format($formato) === $texto) {
                return $fecha;
            }
        }

        return null;
    }

    public function zona(): string
    {
        return (string) config('app.timezone');
    }
}
