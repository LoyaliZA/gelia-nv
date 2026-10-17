<?php

namespace App\Services\GeliaAi\Tools;

use App\Models\User;

interface HerramientaGeliaAi
{
    public function name(): string;

    /**
     * Schema de función para DeepSeek (type=function).
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /** Se ofrece al modelo solo si la persona usuaria tiene acceso; ejecutar() vuelve a validar. */
    public function disponiblePara(User $user): bool;

    /**
     * @param  array<string, mixed>  $args  ya validados contra schema()
     * @param  list<array<string, mixed>>  $archivos  metadatos de archivos disponibles en la conversación
     * @return array<string, mixed>
     */
    public function ejecutar(User $user, array $args, array $archivos = []): array;
}
