<?php

use App\Models\CatalogoTurno;
use App\Support\MatrizHorarioTurno;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CatalogoTurno::query()->each(function (CatalogoTurno $turno): void {
            $raw = $turno->getRawOriginal('matriz_horario');
            $matriz = is_string($raw) ? json_decode($raw, true) : $raw;
            $normalizada = MatrizHorarioTurno::normalizar(is_array($matriz) ? $matriz : null);

            $turno->forceFill(['matriz_horario' => $normalizada])->saveQuietly();
        });
    }

    public function down(): void
    {
        // Sin reversión: las claves en español son el formato canónico.
    }
};
