<?php

namespace App\Casts;

use App\Support\MatrizHorarioTurno;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MatrizHorarioCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $matriz = is_string($value) ? json_decode($value, true) : $value;

        return MatrizHorarioTurno::normalizar(is_array($matriz) ? $matriz : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode(MatrizHorarioTurno::normalizar(is_array($value) ? $value : null));
    }
}
