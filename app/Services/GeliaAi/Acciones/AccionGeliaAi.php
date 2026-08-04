<?php

namespace App\Services\GeliaAi\Acciones;

use App\Models\User;

interface AccionGeliaAi
{
    public function id(): string;

    public function permiso(): string;

    /**
     * Schema corto para tool DeepSeek.
     *
     * @return array<string, mixed>
     */
    public function proponerSchema(): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, accion: string, reporte: array<string, mixed>}
     */
    public function ejecutar(User $user, array $payload): array;
}
